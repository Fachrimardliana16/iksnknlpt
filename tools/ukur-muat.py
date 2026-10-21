"""Ukur waktu muat setiap halaman di Chromium nyata.

Yang diukur, bukan dikira-kira:
  - TTFB (Respons pertama server)
  - FCP  (konten pertama tampil)
  - DCL  (DOM selesai diurai)
  - Load (seluruh aset selesai)

Diukur DUA kali per halaman: pertama tanpa cache (kunjungan baru), kedua dengan
cache (kunjungan ulang). Keduanya penting — angka tanpa cache menunjukkan biaya
server dan ukuran aset, angka dengan cache menunjukkan pengalaman pengguna
sehari-hari.

Satu Chrome per halaman: headless berhenti memuat lembar gaya setelah pemuatan
pertama, dan angka yang keluar tanpa CSS justru terlihat lebih baik daripada
kenyataannya.

Pakai: python tools/ukur-muat.py [basis]
"""

from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

import websocket

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
BATAS_DETIK = 3.0

HALAMAN = [
    ('/', 'Beranda'),
    ('/produk', 'Katalog'),
    ('/produk?q=knalpot', 'Katalog (cari)'),
    ('/produk/knalpot-full-system-nmax-v2-titanium', 'Detail produk'),
    ('/keranjang', 'Keranjang'),
    ('/lacak', 'Lacak'),
    ('/kontak', 'Kontak'),
    ('/tentang', 'Tentang'),
    ('/halaman/syarat-ketentuan', 'Syarat'),
]

SKRIP_UKUR = r"""
(() => {
  const nav = performance.getEntriesByType('navigation')[0] || {};
  const fcp = performance.getEntriesByName('first-contentful-paint')[0];
  const sumber = performance.getEntriesByType('resource');

  let byte = 0;
  let js = 0;
  let css = 0;
  let img = 0;

  for (const r of sumber) {
    byte += r.transferSize || 0;
    if (r.initiatorType === 'script') js += r.transferSize || 0;
    if (r.initiatorType === 'link' || r.initiatorType === 'css') css += r.transferSize || 0;
    if (r.initiatorType === 'img' || r.initiatorType === 'css') img += r.transferSize || 0;
  }

  return JSON.stringify({
    ttfb: Math.round(nav.responseStart || 0),
    fcp: Math.round(fcp ? fcp.startTime : 0),
    dcl: Math.round(nav.domContentLoadedEventEnd || 0),
    load: Math.round(nav.loadEventEnd || 0),
    bita: byte,
    js: js,
    css: css,
    jumlahAset: sumber.length,
    html: document.documentElement.outerHTML.length
  });
})()
"""


def tunggu(port: int, batas: float = 25.0) -> bool:
    akhir = time.time() + batas
    while time.time() < akhir:
        try:
            with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/version', timeout=1):
                return True
        except (urllib.error.URLError, OSError):
            time.sleep(0.25)
    return False


def ukur(url: str, port: int, ulang: bool) -> dict:
    akar = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'out', 'profil-chrome')
    os.makedirs(akar, exist_ok=True)
    profil = tempfile.mkdtemp(prefix='muat-', dir=akar)

    proses = subprocess.Popen(
        [CHROME, '--headless=new', f'--remote-debugging-port={port}', f'--user-data-dir={profil}',
         '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--remote-allow-origins=*',
         '--window-size=1366,900', 'about:blank'],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )

    try:
        if not tunggu(port):
            raise RuntimeError('Chrome tidak membuka port debugging.')

        with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/list', timeout=5) as r:
            target = next(t for t in json.load(r) if t['type'] == 'page')

        ws = websocket.create_connection(target['webSocketDebuggerUrl'], timeout=90, suppress_origin=True)
        nomor = [0]

        def kirim(metode: str, **params):
            nomor[0] += 1
            ws.send(json.dumps({'id': nomor[0], 'method': metode, 'params': params}))
            while True:
                pesan = json.loads(ws.recv())
                if pesan.get('id') == nomor[0]:
                    return pesan.get('result', pesan)

        kirim('Page.enable')
        kirim('Runtime.enable')
        kirim('Network.enable')
        kirim('Network.setCacheDisabled', cacheDisabled=not ulang)

        # Kunjungan baru: cache kosong dan dimatikan. Kunjungan ulang: cache
        # dipakai seperti peramban biasa.
        if ulang:
            kirim('Page.navigate', url=url)
            akhir = time.time() + 25
            while time.time() < akhir:
                siap = kirim('Runtime.evaluate', expression='document.readyState', returnByValue=True)
                if siap.get('result', {}).get('value') == 'complete':
                    break
                time.sleep(0.2)
            time.sleep(1.0)

        kirim('Page.navigate', url=url)
        akhir = time.time() + 30
        while time.time() < akhir:
            siap = kirim('Runtime.evaluate', expression='document.readyState', returnByValue=True)
            if siap.get('result', {}).get('value') == 'complete':
                break
            time.sleep(0.15)

        # loadEventEnd baru terisi sesudah semua aset selesai; beri jeda.
        time.sleep(1.5)

        hasil = kirim('Runtime.evaluate', expression=SKRIP_UKUR, returnByValue=True)
        data = json.loads(hasil['result']['value'])

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
    basis = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8100').rstrip('/')

    print(f'\nWAKTU MUAT HALAMAN (batas {BATAS_DETIK:.0f} detik)\nBasis: {basis}\n')

    print(f"{'Halaman':<22} {'TTFB':>7} {'FCP':>7} {'DCL':>7} {'Load':>7} {'Bita':>9}  Status")
    print('-' * 78)

    gagal = 0
    port = 9900

    for jalur, nama in HALAMAN:
        try:
            baru = ukur(basis + jalur, port, ulang=False)
            port += 1
            ulang = ukur(basis + jalur, port, ulang=True)
            port += 1
        except Exception as e:  # noqa: BLE001 — Chrome kadang gagal bangun
            print(f"{nama:<22} GAGAL DIUKUR: {type(e).__name__}")
            port += 2
            gagal += 1
            continue

        # Yang dinilai adalah kunjungan baru: itu yang dialami pembeli pertama.
        lama = baru['load'] / 1000

        if lama > BATAS_DETIK:
            status = f'LAMBAT ({lama:.1f}s)'
            gagal += 1
        else:
            status = 'OK'

        print(
            f"{nama:<22} {baru['ttfb']:>6}ms {baru['fcp']:>6}ms {baru['dcl']:>6}ms "
            f"{baru['load']:>6}ms {baru['bita']:>8}  {status}  (ulang {ulang['load']}ms)"
        )

    print(f'\nHalaman melewati 3 detik: {gagal}\n')

    return 0 if gagal == 0 else 1


if __name__ == '__main__':
    sys.exit(main())
