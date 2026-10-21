"""Perbaiki berkas yang rusak oleh penulisan baris ganda (CR CR LF).

Beberapa berkas uji tertulis dengan pemisah baris ganda karena patch diterapkan
pada berkas ber-CRLF lalu disimpan ulang dengan "\n" yang ikut terkonversi.
Hasilnya muncul "\r\r\n": baris kosong di antara setiap baris, dan PHP tetap
membacanya — tetapi diff menjadi tidak terbaca dan beberapa pola pencarian gagal.

Pakai: python tools/rapikan-crlf.py [--uji]
"""

import glob
import sys

POLA = [
    r'D:\Projects\iksanknalpot\tests\**\*.php',
]

rusak = []
for pola in POLA:
    for f in glob.glob(pola, recursive=True):
        biner = open(f, 'rb').read()
        if b'\r\r\n' in biner:
            rusak.append(f)

print(f'berkas dengan CR CR LF: {len(rusak)}')
for f in rusak:
    print(' ', f)

if '--uji' in sys.argv:
    sys.exit(0)

for f in rusak:
    biner = open(f, 'rb').read()
    biner = biner.replace(b'\r\r\n', b'\r\n')
    open(f, 'wb').write(biner)
    print('dirapikan:', f)
