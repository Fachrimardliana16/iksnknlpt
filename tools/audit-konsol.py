"""Audit browser: galat konsol dan pelanggaran CSP di setiap halaman.

Kerangka kerja ini dipakai karena satu kelas masalah tidak terlihat dari kode
jawaban HTTP: CSP yang memblokir skrip sumbernya sendiri, dan ekspresi Alpine
yang gagal diurai. Keduanya membuat tombol diam tanpa galat server.

Metodenya sama dengan tools/ukur-f6.py: satu Chrome per halaman (headless
berhenti memuat lembar gaya setelah pemuatan pertama), penampung galat dipasang
sebelum navigasi, lalu hasilnya dibaca dari window.__galat.

Pakai: python tools/audit-konsol.py [basis]
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

HALAMAN = [
    ('/', 'Beranda'),
    ('/produk', 'Katalog'),
    ('/produk/knalpot-full-system-nmax-v2-titanium', 'Detail produk'),
    ('/keranjang', 'Keranjang'),
    ('/lacak', 'Lacak'),
    ('/kontak', 'Kontak'),
    ('/tentang', 'Tentang'),
    ('/halaman/syarat-ketentuan', 'Syarat'),
]

PENAMPUNG = (
    "window.__galat = [];"
    "window.addEventListener('error', function (e) {"
    "  window.__galat.push('error: ' + (e.message || 'tanpa pesan'));"
    "});"
    "window.addEventListener('unhandledrejection', function (e) {"
    "  window.__galat.push('promise: ' + ((e.reason && e.reason.message) || e.reason));"
    "});"
    "window.addEventListener('securitypolicyviolation', function (e) {"
    "  window.__galat.push('csp: ' + e.violatedDirective + ' ' + e.blockedURI);"
    "});"
)

BACA = "(() => JSON.stringify(window.__galat || []))()"

# Galat yang bukan cacat aplikasi: kegagalan jaringan ke layanan luar pada
# mesin tanpa internet, dan pembatalan permintaan saat navigasi.
ABAIAKAN = ('net::ERR_', 'Failed to fetch', 'The user aborted', 'Load failed', 'favicon')


def tunggu(port: int, batas: float = 25.0) -> bool:
    akhir = time.time() + batas
    while time.time() < akhir:
        try:
            with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/version', timeout=1):
                return True
        except (urllib.error.URLError, OSError):
            time.sleep(0.25)
    return False


def periksa(url: str, lebar: int, tinggi: int, port: int) -> list[str]:
    akar = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'out', 'profil-chrome')
    os.makedirs(akar, exist_ok=True)
    profil = tempfile.mkdtemp(prefix='konsol-', dir=akar)

    proses = subprocess.Popen(
        [CHROME, '--headless=new', f'--remote-debugging-port={port}', f'--user-data-dir={profil}',
         '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--remote-allow-origins=*',
         f'--window-size={lebar},{tinggi}', 'about:blank'],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )

    try:
        if not tunggu(port):
            raise RuntimeError('Chrome tidak membuka port debugging.')

        with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/list', timeout=5) as r:
            target = next(t for t in json.load(r) if t['type'] == 'page')

        ws = websocket.create_connection(target['webSocketDebuggerUrl'], timeout=30, suppress_origin=True)
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
        kirim('Page.addScriptToEvaluateOnNewDocument', source=PENAMPUNG)
        kirim('Emulation.setDeviceMetricsOverride', width=lebar, height=tinggi, deviceScaleFactor=1, mobile=lebar < 1024)
        kirim('Page.navigate', url=url)

        akhir = time.time() + 20
        while time.time() < akhir:
            siap = kirim('Runtime.evaluate', expression='document.readyState', returnByValue=True)
            if siap.get('result', {}).get('value') == 'complete':
                break
            time.sleep(0.2)

        # Livewire memasang dirinya sesudah DOM siap; beri waktu agar ekspresi
        # Alpine sempat dievaluasi.
        time.sleep(2.0)

        hasil = kirim('Runtime.evaluate', expression=BACA, returnByValue=True)
        data = json.loads(hasil.get('result', {}).get('value') or '[]')

        ws.close()

        return [g for g in data if not any(a in g for a in ABAIAKAN)]
    finally:
        proses.terminate()
        try:
            proses.wait(timeout=10)
        except subprocess.TimeoutExpired:
            proses.kill()
        shutil.rmtree(profil, ignore_errors=True)


def main() -> int:
    basis = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8100').rstrip('/')

    print(f'\nAUDIT KONSOL & CSP\nBasis: {basis}\n')

    total = 0
    port = 9800

    for jalur, nama in HALAMAN:
        galat = periksa(basis + jalur, 1440, 900, port)
        port += 1

        total += len(galat)

        status = 'BERSIH' if not galat else f'{len(galat)} GALAT'
        print(f'  {status:<10} {nama:<22} {jalur}')

        for g in galat[:4]:
            print(f'             · {g[:120]}')

    print(f'\nTotal galat konsol/CSP: {total}\n')

    return 0 if total == 0 else 1


if __name__ == '__main__':
    sys.exit(main())
