"""Cari permintaan yang paling lambat pada satu halaman.

`ukur-muat2.py` memberi angka total. Skrip ini memberi SEBAB: setiap permintaan
(termasuk yang memblokir render) dicetak dengan durasinya, urut dari terlama,
lewat Resource Timing di Chromium.

Pakai: python tools/lambat.py /produk/slug
"""

import json
import subprocess
import sys
import time
import urllib.request

BASIS = 'http://127.0.0.1:8100'
JALUR = sys.argv[1] if len(sys.argv) > 1 else '/keranjang'
CHROME = r'C:\Program Files\Google\Chrome\Application\chrome.exe'
PROFIL = r'D:\Projects\iksanknalpot\tools\out\profil-chrome'

SKRIP = r"""
(() => {
  const nav = performance.getEntriesByType('navigation')[0] || {};
  const sumber = [];
  for (const r of performance.getEntriesByType('resource')) {
    sumber.push({
      nama: r.name.replace(location.origin, ''),
      jenis: r.initiatorType,
      mulai: Math.round(r.startTime),
      durasi: Math.round(r.duration),
      bita: r.transferSize || 0,
      kode: r.responseStatus || 0
    });
  }
  sumber.sort((a, b) => b.durasi - a.durasi);
  return JSON.stringify({
    ttfb: Math.round(nav.responseStart || 0),
    dcl: Math.round(nav.domContentLoadedEventEnd || 0),
    load: Math.round(nav.loadEventEnd || 0),
    jumlah: sumber.length,
    sumber: sumber.slice(0, 14)
  });
})()
"""


def matikan_chrome():
    subprocess.run(
        ['powershell', '-NoProfile', '-Command',
         "Get-CimInstance Win32_Process -Filter \"Name='chrome.exe'\" | "
         "Where-Object { $_.CommandLine -like '*profil-chrome*' } | "
         "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"],
        capture_output=True,
    )
    time.sleep(1)


def main():
    matikan_chrome()

    proses = subprocess.Popen(
        [CHROME, '--headless=new', '--remote-debugging-port=9333',
         f'--user-data-dir={PROFIL}', '--no-first-run', '--no-default-browser-check',
         '--disable-extensions', '--disable-background-networking',
         '--disable-gpu', '--remote-allow-origins=*', '--window-size=1366,900', 'about:blank'],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )

    target = None
    for _ in range(40):
        try:
            with urllib.request.urlopen('http://127.0.0.1:9333/json', timeout=2) as r:
                for t in json.load(r):
                    if t.get('type') == 'page':
                        target = t
                        break
            if target:
                break
        except Exception:
            pass
        time.sleep(0.5)

    if not target:
        print('Chrome tidak siap')
        proses.kill()
        return 1

    from websocket import create_connection  # noqa: PLC0415

    ws = create_connection(target['webSocketDebuggerUrl'], timeout=120)
    ws.send(json.dumps({'id': 1, 'method': 'Page.enable'}))
    ws.recv()

    ws.send(json.dumps({'id': 2, 'method': 'Page.navigate', 'params': {'url': BASIS + JALUR}}))
    for _ in range(400):
        pesan = json.loads(ws.recv())
        if pesan.get('method') == 'Page.loadEventFired':
            break

    time.sleep(2.5)

    ws.send(json.dumps({'id': 3, 'method': 'Runtime.evaluate',
                        'params': {'expression': SKRIP, 'returnByValue': True}}))
    hasil = None
    for _ in range(60):
        pesan = json.loads(ws.recv())
        if pesan.get('id') == 3:
            hasil = pesan['result']['result']['value']
            break

    ws.close()
    proses.kill()
    matikan_chrome()

    if not hasil:
        print('Tidak ada hasil')
        return 1

    d = json.loads(hasil)
    print(f'\n{JALUR}')
    print(f'TTFB {d["ttfb"]}ms · DCL {d["dcl"]}ms · Load {d["load"]}ms · {d["jumlah"]} permintaan\n')
    print(f'{"permintaan":52} {"jenis":12} {"mulai":>7} {"durasi":>8} {"bita":>9} kode')
    print('-' * 96)
    for s in d['sumber']:
        print(f'{s["nama"][:52]:52} {s["jenis"][:12]:12} {s["mulai"]:>7} {s["durasi"]:>8} {s["bita"]:>9} {s["kode"]}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
