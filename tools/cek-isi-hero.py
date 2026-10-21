"""Periksa apakah ISI hero muat tanpa terpotong di setiap viewport.

Melengkapi ukur-hero.py: berkas itu memeriksa tinggi kotak hero, berkas ini
memeriksa isinya — apakah judul/CTA meluber keluar kotak, dan berapa sisa
ruang di bawah baris tombol.
"""

from __future__ import annotations

import importlib.util
import json
import time

spec = importlib.util.spec_from_file_location("uh", r"D:\Projects\iksanknalpot\tools\ukur-hero.py")
uh = importlib.util.module_from_spec(spec)
spec.loader.exec_module(uh)

JS = """
(() => {
  const sec = document.querySelector('main section');
  const b = sec.getBoundingClientRect();
  const h1 = sec.querySelector('h1');
  const hb = h1.getBoundingClientRect();

  // Slide yang tidak aktif disembunyikan Alpine (rect 0) — abaikan, kalau
  // tidak "tombol terakhir" selalu milik slide tersembunyi.
  const terlihat = [...sec.querySelectorAll('.btn, h1, p')].filter(e => {
    const r = e.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  });

  const palingBawah = terlihat.length
    ? Math.max(...terlihat.map(e => e.getBoundingClientRect().bottom))
    : b.top;
  const palingAtas = terlihat.length
    ? Math.min(...terlihat.map(e => e.getBoundingClientRect().top))
    : b.top;

  return {
    hero: Math.round(b.height),
    isi_tinggi: Math.round(palingBawah - palingAtas),
    isi_meluber: palingBawah > b.bottom + 1,
    h1_ukuran: getComputedStyle(h1).fontSize,
    h1_atas: Math.round(hb.top),
    cta_bawah: Math.round(palingBawah),
    ruang_bawah: Math.round(b.bottom - palingBawah)
  };
})()
"""

hasil = {}
for nama, w, h, mob in uh.VIEWPORT:
    c = uh.Chrome()
    try:
        c.cmd("Emulation.setDeviceMetricsOverride", width=w, height=h,
              deviceScaleFactor=1, mobile=mob)
        c.cmd("Page.navigate", url="http://127.0.0.1:8100/")
        time.sleep(3.0)
        hasil[nama] = c.cmd("Runtime.evaluate", expression=JS,
                            returnByValue=True)["result"]["value"]
        hasil[nama]["layar"] = f"{w}x{h}"
    finally:
        c.tutup()

print(json.dumps(hasil, indent=1, ensure_ascii=False))
