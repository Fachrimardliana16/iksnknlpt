"""Ukur berat beranda: waktu, jumlah aset, byte, dan jumlah simpul DOM.

Yang diukur, bukan dikira-kira:
  - TTFB, FCP, DCL, Load
  - jumlah permintaan & byte per jenis (dokumen, css, js, gambar, font)
  - jumlah simpul DOM dan elemen gambar
  - waktu skrip utama (app.js + livewire)

Pakai: python tools/ukur-beranda.py [url]
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

JS = r"""
(() => {
  const nav = performance.getEntriesByType('navigation')[0] || {};
  const res = performance.getEntriesByType('resource');

  const per = {};
  for (const r of res) {
    let jenis = r.initiatorType || 'lain';
    if (/\.css/.test(r.name)) jenis = 'css';
    else if (/\.js(\?|$)/.test(r.name)) jenis = 'js';
    else if (/\.(woff2?|ttf)(\?|$)/.test(r.name)) jenis = 'font';
    else if (/\.(png|jpe?g|webp|avif|gif|svg)(\?|$)/.test(r.name)) jenis = 'gambar';
    else if (/build\/assets/.test(r.name)) jenis = 'aset';
    per[jenis] = per[jenis] || { n: 0, byte: 0 };
    per[jenis].n += 1;
    per[jenis].byte += r.transferSize || 0;
  }

  const skrip = res.filter(r => /\.js(\?|$)/.test(r.name));

  return {
    ttfb_ms: Math.round(nav.responseStart || 0),
    fcp_ms: Math.round((performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0),
    dcl_ms: Math.round(nav.domContentLoadedEventEnd || 0),
    load_ms: Math.round(nav.loadEventEnd || 0),
    simpul_dom: document.querySelectorAll('*').length,
    jumlah_gambar: document.querySelectorAll('img').length,
    gambar_belum_dimuat: [...document.querySelectorAll('img')].filter(i => !i.complete).length,
    permintaan: res.length,
    per_jenis: per,
    total_byte: Object.values(per).reduce((a, b) => a + b.byte, 0) +
                (nav.transferSize || 0),
    skrip_js: skrip.map(r => ({ nama: r.name.split('/').pop(), kb: Math.round((r.transferSize || 0) / 1024) })),
    animasi_aktif: document.getAnimations ? document.getAnimations().length : -1,
  };
})()
"""


def ukur(url: str, ulang: bool) -> dict:
    d = _profil_sementara("berat-")
    port = 9344
    p = subprocess.Popen([
        CHROME, "--headless=new", f"--remote-debugging-port={port}",
        f"--user-data-dir={d}", "--no-first-run", "--no-default-browser-check",
        "--disable-gpu", "--hide-scrollbars", "about:blank",
    ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    for _ in range(80):
        try:
            urllib.request.urlopen(f"http://127.0.0.1:{port}/json/version", timeout=1)
            break
        except Exception:
            time.sleep(0.25)

    t = json.load(urllib.request.urlopen(f"http://127.0.0.1:{port}/json"))
    page = [x for x in t if x["type"] == "page"][0]
    ws = websocket.create_connection(page["webSocketDebuggerUrl"], timeout=90, suppress_origin=True)

    i = [0]

    def cmd(method, **kw):
        i[0] += 1
        ws.send(json.dumps({"id": i[0], "method": method, "params": kw}))
        while True:
            m = json.loads(ws.recv())
            if m.get("id") == i[0]:
                return m.get("result", {})

    cmd("Emulation.setDeviceMetricsOverride", width=1440, height=900, deviceScaleFactor=1, mobile=False)
    try:
        cmd("Network.setCacheDisabled", cacheDisabled=not ulang)

        if ulang:
            # Kunjungan ulang: muat sekali dulu supaya cache terisi.
            cmd("Page.navigate", url=url)
            time.sleep(4)

        cmd("Page.navigate", url=url)
        time.sleep(5)

        hasil = cmd("Runtime.evaluate", expression=JS, returnByValue=True)["result"]["value"]
    finally:
        try:
            ws.close()
        except Exception:
            pass
        p.terminate()

        try:
            p.wait(timeout=15)
        except Exception:
            p.kill()

        _bersihkan_profil(d)

    return hasil


def main():
    url = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8100/"
    hasil = {"kunjungan_baru": ukur(url, ulang=False), "kunjungan_ulang": ukur(url, ulang=True)}
    print(json.dumps(hasil, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
