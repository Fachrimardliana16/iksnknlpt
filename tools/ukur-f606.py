"""Ukur bar aksi lengket detail produk (checklist F6-06) di peramban nyata.

Yang diukur, bukan yang dikira-kira:
  - lebar dokumen melebihi viewport (gulir horizontal) pada 320/375/768/1440 px;
  - apakah bar lengket memang tampil di bawah 1024 px dan tersembunyi di atasnya;
  - tinggi blok bawah yang menutupi isi, dihitung dari bar + jarak ke dasar layar;
  - ukuran tombol tambah pada bar itu.

Satu Chrome dijalankan untuk SETIAP ukuran, lalu ditutup.
Ini bukan pemborosan: dijalankan sekali dengan beberapa kali navigate, Chrome
headless berhenti memuat lembar gaya setelah pemuatan pertama — kelas Tailwind
tidak berlaku lagi, sehingga bar yang seharusnya tersembunyi di desktop terbaca
tampil dan tinggi tombol terbaca 25 px. Gejalanya tidak terlihat sebagai galat,
hanya angka yang salah. Proses baru per ukuran menutup seluruh kelas masalah itu.

Chrome dijalankan headless dengan remote debugging, diukur lewat DevTools
Protocol memakai websocket-client. Tidak ada pustaka baru yang dipasang.

Pakai: python tools/ukur-f606.py [URL]
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

import websocket  # websocket-client, sudah tersedia di venv Hermes

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
BATAS_SIAP_DETIK = 25
BATAS_CSS_DETIK = 15

SKRIP_UKUR = r"""
(() => {
  const cariBar = () => [...document.querySelectorAll('div.fixed.inset-x-0')]
      .find(d => d.querySelector('button[wire\\:click="tambah"]'));

  const bar = cariBar();
  const tombol = bar ? bar.querySelector('button.btn-primary') : null;
  const rect = bar ? bar.getBoundingClientRect() : null;
  const gaya = bar ? getComputedStyle(bar) : null;

  return JSON.stringify({
    lebarViewport: window.innerWidth,
    gulirHorizontal: document.documentElement.scrollWidth - window.innerWidth,
    barTampil: bar ? gaya.display !== 'none' : false,
    barTinggiIsi: rect ? Math.round(rect.height) : null,
    jarakKeBawah: rect ? Math.round(window.innerHeight - rect.bottom) : null,
    blokBawahTotal: rect ? Math.round(window.innerHeight - rect.top) : null,
    adaPengaturJumlah: bar ? !!bar.querySelector('button[wire\\:click="naikkanJumlah"]') : false,
    adaTombolKurangi: bar ? !!bar.querySelector('button[wire\\:click="turunkanJumlah"]') : false,
    hargaDiBar: bar ? (bar.textContent.match(/Rp [0-9.]+/) || [null])[0] : null,
    tombolTambahTinggi: tombol ? Math.round(tombol.getBoundingClientRect().height) : null,
    cssTermuat: [...document.styleSheets].some(s => (s.href || '').includes('app-')),
    panjangHalaman: document.documentElement.scrollHeight
  });
})()
"""


def tunggu_port(port: int, batas: float = 25.0) -> bool:
    akhir = time.time() + batas

    while time.time() < akhir:
        try:
            with urllib.request.urlopen(f"http://127.0.0.1:{port}/json/version", timeout=1):
                return True
        except (urllib.error.URLError, OSError):
            time.sleep(0.25)

    return False


def ukur(url: str, lebar: int, tinggi: int, mobile: bool, port: int) -> dict:
    """Satu ukuran, satu peramban, lalu peramban itu ditutup."""
    profil = tempfile.mkdtemp(prefix=f"chrome-ukur-{lebar}-")

    proses = subprocess.Popen(
        [
            CHROME,
            "--headless=new",
            f"--remote-debugging-port={port}",
            f"--user-data-dir={profil}",
            "--no-first-run",
            "--no-default-browser-check",
            "--disable-gpu",
            "--remote-allow-origins=*",
            f"--window-size={lebar},{tinggi}",
            "about:blank",
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    try:
        if not tunggu_port(port):
            raise RuntimeError("Chrome tidak membuka port debugging.")

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
        kirim("Emulation.setDeviceMetricsOverride", width=lebar, height=tinggi, deviceScaleFactor=1, mobile=mobile)
        kirim("Page.navigate", url=url)

        akhir = time.time() + BATAS_SIAP_DETIK
        while time.time() < akhir:
            status = kirim("Runtime.evaluate", expression="document.readyState", returnByValue=True)
            if status.get("result", {}).get("value") == "complete":
                break
            time.sleep(0.2)

        # Diukur SETELAH lembar gaya selesai dimuat, bukan setelah DOM selesai.
        # document.readyState menjadi "complete" sebelum CSS selesai diurai.
        akhir = time.time() + BATAS_CSS_DETIK
        while time.time() < akhir:
            css = kirim(
                "Runtime.evaluate",
                expression="[...document.styleSheets].some(s => (s.href || '').includes('app-'))",
                returnByValue=True,
            )
            if css.get("result", {}).get("value") is True:
                break
            time.sleep(0.2)

        time.sleep(0.5)

        hasil = kirim("Runtime.evaluate", expression=SKRIP_UKUR, returnByValue=True)
        data = json.loads(hasil["result"]["value"])

        ws.close()

        return data
    finally:
        proses.terminate()

        try:
            proses.wait(timeout=10)
        except subprocess.TimeoutExpired:
            proses.kill()

        shutil.rmtree(profil, ignore_errors=True)


def main() -> int:
    url = sys.argv[1] if len(sys.argv) > 1 else (
        "http://127.0.0.1:8100/produk/knalpot-full-system-nmax-v2-titanium"
    )

    ukuran = [
        ("320 px ponsel kecil", 320, 568, True),
        ("375 px ponsel", 375, 667, True),
        ("768 px tablet", 768, 1024, True),
        ("1440 px desktop", 1440, 900, False),
    ]

    print(f"\nBar aksi lengket detail produk (F6-06)\nURL: {url}\n")

    gagal = 0
    port = 9400

    for label, lebar, tinggi, mobile in ukuran:
        h = ukur(url, lebar, tinggi, mobile, port)
        port += 1

        catatan = []

        if not h["cssTermuat"]:
            catatan.append("lembar gaya belum termuat — pengukuran tidak sah")

        if h["lebarViewport"] != lebar:
            catatan.append(f"viewport {h['lebarViewport']} px, diminta {lebar} px")

        if h["gulirHorizontal"] > 0:
            catatan.append(f"gulir horizontal {h['gulirHorizontal']} px")

        if lebar < 1024:
            if not h["barTampil"]:
                catatan.append("bar lengket TIDAK tampil")
            if not h["adaPengaturJumlah"] or not h["adaTombolKurangi"]:
                catatan.append("pengatur jumlah tidak lengkap")
            if not h["hargaDiBar"]:
                catatan.append("harga tidak ada di bar")
            if (h["tombolTambahTinggi"] or 0) < 44:
                catatan.append(f"tombol tambah hanya {h['tombolTambahTinggi']} px")
        elif h["barTampil"]:
            catatan.append("bar lengket masih tampil di desktop")

        gagal += len(catatan)

        status = "OK   " if not catatan else "GAGAL"

        print(
            f"  {status} {label:<20} "
            f"gulir={h['gulirHorizontal']:>3}px  bar={str(h['barTampil']):<5} "
            f"blok-bawah={h['blokBawahTotal']:>5}px "
            f"(isi {h['barTinggiIsi']} + jarak {h['jarakKeBawah']})  "
            f"tombol={h['tombolTambahTinggi']}px",
        )

        if catatan:
            print(f"        -> {'; '.join(catatan)}")

    print(f"\nHasil: {'semua ukuran sesuai' if gagal == 0 else str(gagal) + ' temuan'}\n")

    return 0 if gagal == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
