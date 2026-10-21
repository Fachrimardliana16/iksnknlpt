"""Verifikasi animasi reveal beranda: elemen muncul, hero tidak berkedip.

Yang diperiksa:
  1. Hero TIDAK beranimasi (tidak redup saat muat pertama) — hero adalah isi
     layar pertama, menganimasikannya justru membuat lambat terasa.
  2. Bagian di bawah layar mulai redup, lalu menjadi .tampak setelah digulir.
  3. Menggulir ke bawah dengan cepat tidak meninggalkan bagian yang tetap
     redup (isi tak terbaca).
  4. Bila prefers-reduced-motion aktif, semuanya langsung tampak.

Pakai: python tools/uji-reveal.py [url]
"""

from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.request

import websocket

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"


def _bersihkan_profil(jalur: str) -> None:
    """Hapus profil Chrome sementara, setelah semua prosesnya benar-benar mati.

    Di Windows terminate() hanya membunuh proses INDUK; anak-anak Chrome
    (renderer, gpu, utility) tetap hidup dan MENGUNCI berkas di dalam profil.
    Ini yang dulu membuat folder 50-150 MB menumpuk di TEMP sampai disk penuh —
    dan gejalanya muncul jauh kemudian sebagai "No space left on device" di
    dalam suite test PHP, bukan di skrip yang menyebabkannya.
    """
    # Yang dicocokkan cukup NAMA foldernya, bukan jalur lengkap: Chrome
    # menuliskan --user-data-dir dalam bentuk 8.3 ("C:\\Users\\IT-LAP~1\\...")
    # sementara tempfile.mkdtemp() mengembalikan bentuk panjang, sehingga
    # filter jalur lengkap tidak pernah cocok dan tidak ada proses yang dibunuh.
    # Nama foldernya unik (akhiran acak), jadi aman.
    polos = os.path.basename(jalur)

    perintah = (
        'Get-CimInstance Win32_Process -Filter "Name=\'chrome.exe\'" | '
        "Where-Object { $_.CommandLine -like '*" + polos + "*' } | "
        "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
    )

    try:
        subprocess.run(
            ["powershell", "-NoProfile", "-Command", perintah],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            timeout=60,
        )
    except Exception:
        pass

    for _ in range(20):
        shutil.rmtree(jalur, ignore_errors=True)

        if not os.path.isdir(jalur):
            return

        time.sleep(0.25)



def _profil_sementara(prefix: str) -> str:
    """Profil Chrome sementara untuk satu pengukuran.

    Setiap pemanggilan Chrome butuh profil sendiri, tetapi profil yang tidak
    dibersihkan menumpuk: sekali pengukuran meninggalkan ~50-150 MB, dan folder
    TEMP di mesin ini pernah menyentuh kapasitas disk — galatnya muncul jauh
    kemudian sebagai "No space left on device" di dalam suite test PHP, bukan
    di skrip yang menyebabkannya.

    Folder dihapus di akhiri(run)() tiap kelas, tetapi jalur ini menjaga
    pemakaian tetap kecil walaupun skrip dihentikan di tengah jalan.
    """
    return tempfile.mkdtemp(prefix=prefix)
URL = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8100/"

JS_KEADAAN = r"""
(() => {
  const semua = [...document.querySelectorAll('[data-reveal]')];
  return {
    jumlah_reveal: semua.length,
    tampak: semua.filter(e => e.classList.contains('tampak')).length,
    redup: semua
      .map((e, i) => ({ i, op: getComputedStyle(e).opacity,
                        y: Math.round(e.getBoundingClientRect().top) }))
      .filter(x => Number(x.op) < 0.99)
      .slice(0, 6),
    hero_redup: (() => {
      const h = document.querySelector('main section');
      if (!h) return null;
      return { punya_attr: h.hasAttribute('data-reveal'),
               op: getComputedStyle(h).opacity };
    })(),
    tunda_render: [...document.querySelectorAll('.tunda-render')].length,
    js_aktif: document.documentElement.classList.contains('js'),
  };
})()
"""


class Chrome:
    def __init__(self, kurangi_gerak: bool = False):
        self.dir = _profil_sementara("reveal-")
        self.port = 9355
        self.p = subprocess.Popen([
            CHROME, "--headless=new", f"--remote-debugging-port={self.port}",
            f"--user-data-dir={self.dir}", "--no-first-run", "--no-default-browser-check",
            "--disable-gpu", "--hide-scrollbars", "about:blank",
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        for _ in range(80):
            try:
                urllib.request.urlopen(f"http://127.0.0.1:{self.port}/json/version", timeout=1)
                break
            except Exception:
                time.sleep(0.25)

        t = json.load(urllib.request.urlopen(f"http://127.0.0.1:{self.port}/json"))
        page = [x for x in t if x["type"] == "page"][0]
        self.ws = websocket.create_connection(page["webSocketDebuggerUrl"], timeout=90,
                                              suppress_origin=True)
        self.i = 0

        self.cmd("Emulation.setDeviceMetricsOverride", width=1440, height=900,
                 deviceScaleFactor=1, mobile=False)

        if kurangi_gerak:
            self.cmd("Emulation.setEmulatedMedia", features=[
                {"name": "prefers-reduced-motion", "value": "reduce"}])

    def cmd(self, method, **kw):
        self.i += 1
        self.ws.send(json.dumps({"id": self.i, "method": method, "params": kw}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get("id") == self.i:
                return m.get("result", {})

    def eval(self, js):
        return self.cmd("Runtime.evaluate", expression=js, returnByValue=True)["result"]["value"]

    def tutup(self):
        try:
            self.ws.close()
        except Exception:
            pass
        self.p.terminate()

        try:
            self.p.wait(timeout=15)
        except Exception:
            self.p.kill()

        _bersihkan_profil(self.dir)


def jalankan():
    hasil = {}

    # ── A. muat pertama, tanpa menggulir ──
    c = Chrome()
    try:
        c.cmd("Network.setCacheDisabled", cacheDisabled=True)
        c.cmd("Page.navigate", url=URL)
        time.sleep(1.0)
        awal = c.eval(JS_KEADAAN)

        time.sleep(2.5)
        diam = c.eval(JS_KEADAAN)

        # ── B. gulir bertahap ke bawah ──
        for _ in range(8):
            c.eval("window.scrollBy(0, Math.round(window.innerHeight * 0.9))")
            time.sleep(0.35)

        c.eval("window.scrollTo(0, document.body.scrollHeight)")
        time.sleep(1.2)
        digulir = c.eval(JS_KEADAAN)
    finally:
        c.tutup()

    hasil["tanpa_gulir_1detik"] = awal
    hasil["tanpa_gulir_3detik"] = diam
    hasil["setelah_gulir_penuh"] = digulir

    # ── C. prefierte reduced-motion: semua harus langsung tampak ──
    c = Chrome(kurangi_gerak=True)
    try:
        c.cmd("Page.navigate", url=URL)
        time.sleep(1.2)
        hasil["kurangi_gerak"] = c.eval(JS_KEADAAN)
    finally:
        c.tutup()

    print(json.dumps(hasil, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    jalankan()
