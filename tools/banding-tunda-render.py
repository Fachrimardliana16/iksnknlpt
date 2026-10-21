"""A/B berulang: apakah `.tunda-render` (content-visibility) benar-benar hemat.

Satu pengukuran mudah menipu — mesin peramban tidak deterministik dan bg
lain bisa berjalan bersamaan. Berkas ini mengulang A dan B bergantian, lalu
melaporkan median tiap metrik. Yang dipakai untuk memutuskan adalah median,
bukan satu angka.

Pakai: python tools/banding-tunda-render.py [url] [ulangan]
"""

from __future__ import annotations

import json
import os
import statistics
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
ULANGAN = int(sys.argv[2]) if len(sys.argv) > 2 else 3

MATIKAN = """
(() => {
  const s = document.createElement('style');
  s.textContent = '.tunda-render{content-visibility:visible !important;contain-intrinsic-size:auto !important}';
  document.head.appendChild(s);
  return true;
})()
"""

GULIR = """
(async () => {
  const langkah = Math.round(window.innerHeight * 0.8);
  for (let y = 0; y < document.body.scrollHeight; y += langkah) {
    window.scrollTo(0, y);
    await new Promise(r => requestAnimationFrame(() => setTimeout(r, 60)));
  }
  window.scrollTo(0, document.body.scrollHeight);
  await new Promise(r => setTimeout(r, 300));
  return true;
})()
"""

METRIK = ["LayoutDuration", "RecalcStyleDuration", "ScriptDuration", "TaskDuration"]


class Chrome:
    def __init__(self):
        self.dir = _profil_sementara("ab-")
        self.port = 9388
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
        self.ws = websocket.create_connection(page["webSocketDebuggerUrl"], timeout=120,
                                              suppress_origin=True)
        self.i = 0
        self.cmd("Emulation.setDeviceMetricsOverride", width=1440, height=900,
                 deviceScaleFactor=1, mobile=False)
        self.cmd("Performance.enable")

    def cmd(self, method, **kw):
        self.i += 1
        self.ws.send(json.dumps({"id": self.i, "method": method, "params": kw}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get("id") == self.i:
                return m.get("result", {})

    def eval(self, js, await_promise=False):
        return self.cmd("Runtime.evaluate", expression=js, returnByValue=True,
                        awaitPromise=await_promise).get("result", {}).get("value")

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


def satu_kali(mati: bool) -> dict:
    c = Chrome()
    try:
        c.cmd("Network.setCacheDisabled", cacheDisabled=True)
        c.cmd("Page.navigate", url=URL)
        time.sleep(1.2)

        if mati:
            c.eval(MATIKAN)

        c.eval(GULIR, await_promise=True)
        time.sleep(0.5)

        m = {x["name"]: x["value"] for x in c.cmd("Performance.getMetrics")["metrics"]}
    finally:
        c.tutup()

    return {k: m.get(k, 0) * 1000 for k in METRIK}


def main():
    a, b = [], []

    for n in range(ULANGAN):
        a.append(satu_kali(mati=False))
        b.append(satu_kali(mati=True))
        print(f"  putaran {n + 1}/{ULANGAN} selesai", file=sys.stderr)

    med = lambda xs, k: round(statistics.median([x[k] for x in xs]), 1)

    hasil = {
        "A_aktif_median": {k: med(a, k) for k in METRIK},
        "B_mati_median": {k: med(b, k) for k in METRIK},
        "selisih_B_minus_A": {k: round(med(b, k) - med(a, k), 1) for k in METRIK},
        "sebaran_A": {k: [round(x[k], 1) for x in a] for k in METRIK},
        "sebaran_B": {k: [round(x[k], 1) for x in b] for k in METRIK},
        "ulangan": ULANGAN,
    }

    print(json.dumps(hasil, indent=1))


if __name__ == "__main__":
    main()
