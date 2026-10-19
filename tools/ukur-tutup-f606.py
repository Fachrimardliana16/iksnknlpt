"""Periksa apakah bar lengket + bar navigasi bawah menutupi isi terbawah
halaman detail produk (checklist F6-06).

Yang diukur: setelah digulir sampai dasar halaman, jarak antara dasar elemen
terakhir yang punya teks di dalam <footer> dengan puncak bar lengket. Nilai
negatif berarti isinya tertutup.

Pakai: python tools/ukur-tutup-f606.py [URL]
"""

from __future__ import annotations

import json
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

import websocket

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

SKRIP = r"""
(async () => {
  // Digulir dengan pengulangan, bukan sekali panggil.
  //
  // Gaya situs memakai scroll-behavior: smooth, sehingga satu kali scrollTo
  // hanya memulai animasi dan pengukuran yang dilakukan setelahnya membaca
  // posisi yang masih jauh dari dasar halaman — angkanya lalu tampak seolah
  // seluruh isi tertutup.
  const dasar = () => document.documentElement.scrollHeight - window.innerHeight;

  for (let i = 0; i < 40; i++) {
    window.scrollTo(0, dasar());
    await new Promise(r => setTimeout(r, 100));
    if (Math.abs(window.scrollY - dasar()) < 2) break;
  }

  const bar = [...document.querySelectorAll('div.fixed.inset-x-0')]
      .find(d => d.querySelector('button[wire\\:click="tambah"]'));

  if (!bar) return JSON.stringify({ada: false});

  const puncakBar = bar.getBoundingClientRect().top;

  // Elemen berteks terakhir di dalam footer: inilah yang paling mungkin
  // tertutup, karena bar-nya melayang di atas seluruh halaman.
  const kandidat = [...document.querySelectorAll('footer *')]
      .filter(e => e.children.length === 0 && e.textContent.trim().length > 0);

  const akhir = kandidat[kandidat.length - 1];
  const bawah = akhir ? akhir.getBoundingClientRect().bottom : null;

  return JSON.stringify({
    ada: true,
    puncakBar: Math.round(puncakBar),
    teksTerakhir: akhir ? akhir.textContent.trim().slice(0, 40) : null,
    bawahTeksTerakhir: bawah === null ? null : Math.round(bawah),
    celah: bawah === null ? null : Math.round(puncakBar - bawah),
    gulirKe: Math.round(window.scrollY),
    dasarMaks: Math.round(dasar())
  });
})()
"""


def main() -> int:
    url = sys.argv[1] if len(sys.argv) > 1 else (
        "http://127.0.0.1:8100/produk/knalpot-full-system-nmax-v2-titanium"
    )

    profil = tempfile.mkdtemp(prefix="chrome-tutup-")
    port = 9450

    proses = subprocess.Popen(
        [
            CHROME, "--headless=new", f"--remote-debugging-port={port}",
            f"--user-data-dir={profil}", "--no-first-run", "--disable-gpu",
            "--remote-allow-origins=*", "--window-size=375,667", "about:blank",
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    try:
        for _ in range(100):
            try:
                urllib.request.urlopen(f"http://127.0.0.1:{port}/json/version", timeout=1)
                break
            except (urllib.error.URLError, OSError):
                time.sleep(0.25)

        with urllib.request.urlopen(f"http://127.0.0.1:{port}/json/list", timeout=5) as r:
            target = next(t for t in json.load(r) if t["type"] == "page")

        ws = websocket.create_connection(target["webSocketDebuggerUrl"], timeout=30, suppress_origin=True)
        nomor = [0]

        def kirim(metode: str, **params):
            nomor[0] += 1
            ws.send(json.dumps({"id": nomor[0], "method": metode, "params": params}))

            while True:
                pesan = json.loads(ws.recv())
                if pesan.get("id") == nomor[0]:
                    return pesan.get("result", pesan)

        kirim("Page.enable")
        kirim("Runtime.enable")
        kirim("Emulation.setDeviceMetricsOverride", width=375, height=667, deviceScaleFactor=1, mobile=True)
        kirim("Page.navigate", url=url)
        time.sleep(6)

        for _ in range(60):
            siap = kirim(
                "Runtime.evaluate",
                expression="[...document.styleSheets].some(s => (s.href || '').includes('app-')) && document.readyState === 'complete'",
                returnByValue=True,
            )
            if siap.get("result", {}).get("value") is True:
                break
            time.sleep(0.25)

        hasil = kirim("Runtime.evaluate", expression=SKRIP, returnByValue=True, awaitPromise=True)
        data = json.loads(hasil["result"]["value"])
        ws.close()

        print("\nTumpang tindih di dasar halaman (375 px)\n")

        if not data["ada"]:
            print("  Bar lengket tidak ditemukan.")
            return 2

        print(f"  gulir tercapai      : {data['gulirKe']} px (dasar {data['dasarMaks']} px)")
        print(f"  teks terakhir footer : \"{data['teksTerakhir']}\"")
        print(f"  dasar teks tsb       : {data['bawahTeksTerakhir']} px dari atas viewport")
        print(f"  puncak bar lengket   : {data['puncakBar']} px")
        print(f"  celah                : {data['celah']} px")

        if data["celah"] is None or data["celah"] < 0:
            print("\n  GAGAL: isi terbawah tertutup bar lengket.\n")
            return 1

        print("\n  OK: isi terbawah masih di atas bar.\n")
        return 0

    finally:
        proses.terminate()

        try:
            proses.wait(timeout=10)
        except subprocess.TimeoutExpired:
            proses.kill()

        shutil.rmtree(profil, ignore_errors=True)


if __name__ == "__main__":
    sys.exit(main())
