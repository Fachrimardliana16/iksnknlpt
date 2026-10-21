"""Ukur tinggi sebenarnya tiap bagian `.tunda-render` (content-visibility mati).

Dipakai untuk menala `contain-intrinsic-size`: taksiran yang melenceng jauh
membuat bilah gulir melompat dan tata letak dihitung ulang.

Pakai: python tools/ukur-tinggi-bagian.py [url]
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

MATIKAN_CV = """
(() => {
  const s = document.createElement('style');
  s.textContent = '.tunda-render{content-visibility:visible !important;contain-intrinsic-size:auto !important}';
  document.head.appendChild(s);
  return true;
})()
"""

UKUR = """
(() => [...document.querySelectorAll('.tunda-render')].map((e, i) => ({
    urutan: i,
    tinggi: Math.round(e.getBoundingClientRect().height),
    tanda: (e.className.match(/part-no|container-page|carbon-panel/) || ['?'])[0],
})))()
"""


def ukur(w: int, h: int, mobile: bool):
    d = _profil_sementara("tinggi-")
    port = 9377
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

    def cmd(m, **kw):
        i[0] += 1
        ws.send(json.dumps({"id": i[0], "method": m, "params": kw}))
        while True:
            r = json.loads(ws.recv())
            if r.get("id") == i[0]:
                return r.get("result", {})

    try:
        cmd("Emulation.setDeviceMetricsOverride", width=w, height=h,
            deviceScaleFactor=1, mobile=mobile)
        cmd("Network.setCacheDisabled", cacheDisabled=True)
        cmd("Page.navigate", url=URL)
        time.sleep(3.0)
        cmd("Runtime.evaluate", expression=MATIKAN_CV, returnByValue=True)
        time.sleep(0.8)
        hasil = cmd("Runtime.evaluate", expression=UKUR, returnByValue=True)["result"]["value"]
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


if __name__ == "__main__":
    print(json.dumps({
        "desktop_1440x900": ukur(1440, 900, False),
        "mobile_390x844": ukur(390, 844, True),
    }, indent=1, ensure_ascii=False))
