"""Ukur apakah hero benar-benar mengisi viewport di desktop, tablet, mobile.

Mengukur, bukan mengira-kira: tinggi <header>, tinggi <section> hero, apakah
hero menempel tepat di bawah header, apakah hero melampaui layar (harus
scroll untuk melihat seluruhnya), dan apakah teks CTA terpotong keluar layar.

Satu Chrome per viewport: emulasi ukuran pada satu instans membuat Chrome
mempertahankan scroll dan cache tata letak, angka jadi tidak jujur.

Pakai: python tools/ukur-hero.py [basis]
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

VIEWPORT = [
    ("desktop", 1440, 900, False),
    ("desktop-pendek", 1280, 720, False),
    ("tablet", 834, 1112, True),
    ("tablet-lanskap", 1112, 834, True),
    ("mobile", 390, 844, True),
    ("mobile-kecil", 360, 640, True),
]

JS_UKUR = r"""
(() => {
  const sec = document.querySelector('main section');
  const hd = document.querySelector('header');
  const b = sec.getBoundingClientRect();
  const h = hd.getBoundingClientRect();
  // Elemen teks terakhir di dalam hero (baris CTA) — apakah terlihat penuh.
  const cta = sec.querySelector('.btn');
  const cb = cta ? cta.getBoundingClientRect() : null;
  const nav = document.querySelector('body > nav.fixed, nav.fixed');
  const nb = nav ? nav.getBoundingClientRect() : null;
  return {
    vh: window.innerHeight,
    header_h: Math.round(h.height),
    header_bottom: Math.round(h.bottom),
    hero_top: Math.round(b.top),
    hero_h: Math.round(b.height),
    hero_bottom: Math.round(b.bottom),
    sisa_bawah: Math.round(window.innerHeight - b.bottom),
    melebihi_layar: b.height > window.innerHeight,
    navbawah_h: nb ? Math.round(nb.height) : 0,
    navbawah_menutup_hero: nb ? Math.round(Math.max(0, b.bottom - nb.top)) : 0,
    cta_bawah: cb ? Math.round(cb.bottom) : null,
    cta_terlihat: cb ? (cb.bottom <= window.innerHeight && cb.top >= 0) : null,
    cta_tertutup_nav: (cb && nb) ? cb.bottom > nb.top : false,
    body_scroll: document.documentElement.scrollHeight,
    gaya: sec.className
  };
})()
"""


class Chrome:
    def __init__(self):
        self.dir = _profil_sementara("hero-")
        self.port = 9333
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
        self.ws = websocket.create_connection(page["webSocketDebuggerUrl"],
                                              timeout=90, suppress_origin=True)
        self.i = 0

    def cmd(self, method, **kw):
        self.i += 1
        self.ws.send(json.dumps({"id": self.i, "method": method, "params": kw}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get("id") == self.i:
                return m.get("result", {})

    def ukur(self, url, w, h, mobile):
        self.cmd("Emulation.setDeviceMetricsOverride", width=w, height=h,
                 deviceScaleFactor=1, mobile=mobile)
        self.cmd("Page.navigate", url=url)
        time.sleep(3.0)
        r = self.cmd("Runtime.evaluate", expression=JS_UKUR, returnByValue=True)
        return r["result"].get("value")

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


def main():
    basis = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8100"
    hasil = {}
    for nama, w, h, mob in VIEWPORT:
        c = Chrome()
        try:
            hasil[nama] = c.ukur(basis + "/", w, h, mob)
            hasil[nama]["layar"] = f"{w}x{h}"
        finally:
            c.tutup()
    print(json.dumps(hasil, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
