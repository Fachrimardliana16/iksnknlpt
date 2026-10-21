"""Ukur waktu muat halaman SATU per satu, dengan Chrome yang dimatikan bersih.

Versi sebelumnya menjalankan dua Chrome berurutan per halaman tanpa memastikan
proses lama benar-benar mati. Di mesin ini pernah tersisa 447 proses chrome.exe;
CPU habis dipakai proses zombie, dan angka yang keluar (33–42 detik) mengukur
kemacetan mesin, bukan aplikasi.

Skrip ini:
  - mematikan proses chrome sisa sebelum mulai;
  - satu Chrome per pengukuran, ditunggu sampai benar-benar keluar;
  - dua kali per halaman (cache kosong, lalu cache terisi).

Pakai: python tools/ukur-muat2.py [basis]
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

SKRIP = r"""
(() => {
  const nav = performance.getEntriesByType('navigation')[0] || {};
  const fcp = performance.getEntriesByName('first-contentful-paint')[0];
  let bita = 0;
  for (const r of performance.getEntriesByType('resource')) { bita += r.transferSize || 0; }
  return JSON.stringify({
    ttfb: Math.round(nav.responseStart || 0),
    fcp: Math.round(fcp ? fcp.startTime : 0),
    dcl: Math.round(nav.domContentLoadedEventEnd || 0),
    load: Math.round(nav.loadEventEnd || 0),
    bita: bita
  });
})()
"""


def bersihkan_chrome() -> int:
    """Matikan chrome sisa pengukuran sebelumnya."""
    try:
        hasil = subprocess.run(
            ['powershell', '-NoProfile', '-Command',
             "(Get-CimInstance Win32_Process -Filter \"Name='chrome.exe'\" | "
             "Where-Object { $_.CommandLine -like '*profil-chrome*' -or $_.CommandLine -like '*muat-*' } | "
             "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }).Count"],
            capture_output=True, text=True, timeout=90,
        )
        return int((hasil.stdout or '0').strip() or 0)
    except Exception:
        return 0


def tunggu_port(port: int, batas: float = 30.0) -> bool:
    akhir = time.time() + batas
    while time.time() < akhir:
        try:
            with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/version', timeout=1):
                return True
        except (urllib.error.URLError, OSError):
            time.sleep(0.2)
    return False


def sekali(url: str, port: int, pakai_cache: bool) -> dict:
    akar = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'out', 'profil-chrome')
    os.makedirs(akar, exist_ok=True)
    profil = tempfile.mkdtemp(prefix='m2-', dir=akar)

    proses = subprocess.Popen(
        [CHROME, '--headless=new', f'--remote-debugging-port={port}', f'--user-data-dir={profil}',
         '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--disable-dev-shm-usage',
         '--remote-allow-origins=*', '--window-size=1366,900', 'about:blank'],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )

    ws = None

    try:
        if not tunggu_port(port):
            raise RuntimeError('Chrome tidak membuka port debugging.')

        with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/list', timeout=5) as r:
            target = next(t for t in json.load(r) if t['type'] == 'page')

        ws = websocket.create_connection(target['webSocketDebuggerUrl'], timeout=60, suppress_origin=True)
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
        kirim('Network.setCacheDisabled', cacheDisabled=not pakai_cache)

        if pakai_cache:
            kirim('Page.navigate', url=url)

            akhir = time.time() + 25
            while time.time() < akhir:
                s = kirim('Runtime.evaluate', expression='document.readyState', returnByValue=True)
                if s.get('result', {}).get('value') == 'complete':
                    break
                time.sleep(0.15)

            time.sleep(0.8)

        kirim('Page.navigate', url=url)

        akhir = time.time() + 30
        while time.time() < akhir:
            s = kirim('Runtime.evaluate', expression='document.readyState', returnByValue=True)
            if s.get('result', {}).get('value') == 'complete':
                break
            time.sleep(0.15)

        time.sleep(1.2)

        hasil = kirim('Runtime.evaluate', expression=SKRIP, returnByValue=True)

        return json.loads(hasil['result']['value'])
    finally:
        if ws is not None:
            try:
                ws.close()
            except Exception:
                pass

        proses.terminate()

        try:
            proses.wait(timeout=15)
        except subprocess.TimeoutExpired:
            proses.kill()
            proses.wait(timeout=10)

        # Tunggu sampai profil benar-benar dapat dihapus: tanda prosesnya sudah
        # melepas berkas, bukan sekadar sudah menerima sinyal mati.
        for _ in range(20):
            try:
                shutil.rmtree(profil)
                break
            except OSError:
                time.sleep(0.3)


def main() -> int:
    basis = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8100').rstrip('/')

    sisa = bersihkan_chrome()
    print(f'\nWAKTU MUAT (batas {BATAS_DETIK:.0f} detik) — chrome sisa dimatikan: {sisa}\n')
    print(f"{'Halaman':<22} {'TTFB':>7} {'FCP':>7} {'DCL':>7} {'Load':>7} {'Bita':>9}  Status")
    print('-' * 80)

    gagal = 0
    port = 9100

    for jalur, nama in HALAMAN:
        try:
            baru = sekali(basis + jalur, port, pakai_cache=False)
            port += 1
            ulang = sekali(basis + jalur, port, pakai_cache=True)
            port += 1
        except Exception as e:  # noqa: BLE001
            print(f'{nama:<22} GAGAL DIUKUR: {type(e).__name__}: {e}')
            gagal += 1
            port += 2
            continue

        detik = baru['load'] / 1000
        status = f'LAMBAT ({detik:.1f}s)' if detik > BATAS_DETIK else 'OK'
        gagal += 1 if detik > BATAS_DETIK else 0

        print(
            f"{nama:<22} {baru['ttfb']:>6}ms {baru['fcp']:>6}ms {baru['dcl']:>6}ms "
            f"{baru['load']:>6}ms {baru['bita']:>8}  {status}  (ulang {ulang['load']}ms)"
        )

    print(f'\nHalaman melewati {BATAS_DETIK:.0f} detik: {gagal}\n')

    return 0 if gagal == 0 else 1


if __name__ == '__main__':
    sys.exit(main())
