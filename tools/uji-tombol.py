"""Uji tombol + dan − di halaman detail produk, TANPA Chrome/CDP.

Kenapa bukan lewat peramban: pengukuran berulang di mesin ini menunjukkan
Chromium headless (baik lewat CDP maupun DevTools) sering menggantung dan
meninggalkan ratusan proses, sehingga hasilnya tidak dapat dipercaya dan
mengaburkan penyebab sebenarnya.

Yang diuji di sini adalah RANTAI yang membuat tombol bekerja, dengan bukti
yang dapat diperiksa ulang:

  1. livewire.js benar-benar dapat diunduh (HTTP 200, isi JavaScript).
  2. Rute POST /livewire/update ada (bukan 404/419 tanpa sesi).
  3. Memanggil naikkanJumlah/turunkanJumlah lewat Livewire MENGGESER nilai
     jumlah, dan itu terbaca pada snapshot yang dikembalikan server.

Langkah 3 memakai sesi + token CSRF yang sama seperti peramban, sehingga
kegagalan CSRF pun ikut terdeteksi.

Pakai: python tools/uji-tombol.py [/produk/slug]
"""

import html as H
import http.cookiejar
import json
import re
import sys
import urllib.parse
import urllib.request

BASIS = 'http://127.0.0.1:8100'
JALUR = sys.argv[1] if len(sys.argv) > 1 else '/produk/slip-on-racing-vario-160'


def gagal(pesan):
    print('  GAGAL:', pesan)
    return False


def main():
    cj = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

    print(f'\nJALUR: {JALUR}')

    # ── 1. livewire.js dapat diunduh? ────────────────────────────────
    halaman = op.open(BASIS + JALUR, timeout=30).read().decode('utf-8', 'replace')

    cocok = re.search(r'<script[^>]*src="(/livewire/livewire\.js[^"]*)"', halaman)
    if not cocok:
        return gagal('tag livewire.js tidak ada di HTML — @livewireScripts belum terpasang')

    jalur_lw = H.unescape(cocok.group(1))
    try:
        r = op.open(BASIS + jalur_lw, timeout=30)
        isi = r.read()
    except Exception as e:
        return gagal(f'livewire.js tidak dapat diunduh ({e}) — tombol wire:click pasti mati')

    print(f'  livewire.js   {r.status} · {len(isi)} bita · {r.headers.get("Content-Type")}')
    # Livewire 3 mengirim bundel minified yang diawali "(() => {", kadang
    # dengan komentar lisensi di depannya. Yang diperiksa: ada isi JavaScript,
    # bukan panjang yang tepat.
    if len(isi) < 50_000 or b'Livewire' not in isi:
        return gagal('isi livewire.js tidak tampak seperti bundel Livewire')

    # ── 2. rute update ada? ─────────────────────────────────────────
    token = None
    m = re.search(r'<meta[^>]*name="csrf-token"[^>]*content="([^"]+)"', halaman)
    if m:
        token = m.group(1)

    if not token:
        return gagal('meta csrf-token tidak ada di HTML')

    # ── 3. komponen detail-produk & nilai jumlah ────────────────────
    snaps = re.findall(r'wire:snapshot="([^"]+)"', halaman)
    comp = None
    for s in snaps:
        d = json.loads(H.unescape(s))
        if d.get('memo', {}).get('name') == 'front.detail-produk':
            comp = d
            break

    if comp is None:
        return gagal('komponen front.detail-produk tidak ditemukan di halaman')

    awal = comp['data']['jumlah']
    print(f'  jumlah awal   {awal}')

    # Snapshot HARUS diperbarui setiap panggilan, persis seperti yang dilakukan
    # Livewire di peramban. Versi pertama skrip ini selalu mengirim snapshot
    # awal, sehingga klik kedua dan seterusnya dihitung dari nilai yang sama
    # dan hasilnya tampak "berhenti" — itu cacat uji, bukan cacat tombol.
    keadaan = {'snap': comp}

    def kirim(metode):
        muatan = {'components': [{
            'snapshot': json.dumps(keadaan['snap'], separators=(',', ':')),
            'updates': {},
            'calls': [{'path': '', 'method': metode, 'params': []}],
        }]}
        req = urllib.request.Request(
            BASIS + '/livewire/update',
            data=json.dumps(muatan).encode(),
            headers={
                'Content-Type': 'application/json',
                'X-Livewire': '1',
                'X-CSRF-TOKEN': token,
                'Referer': BASIS + JALUR,
                'X-Requested-With': 'XMLHttpRequest',
            },
            method='POST')
        jawab = json.loads(op.open(req, timeout=30).read().decode())
        baru = jawab['components'][0]['snapshot']
        d = json.loads(baru) if isinstance(baru, str) else baru
        keadaan['snap'] = d
        return d['data']['jumlah']

    try:
        naik1 = kirim('naikkanJumlah')
        print(f'  klik +        {awal} -> {naik1}')
        naik2 = kirim('naikkanJumlah')
        print(f'  klik + lagi   {naik1} -> {naik2}')
        turun = kirim('turunkanJumlah')
        print(f'  klik −        {naik2} -> {turun}')
    except Exception as e:
        return gagal(f'panggilan Livewire gagal: {type(e).__name__} {e}')

    naik_benar = naik1 == awal + 1 and naik2 == awal + 2
    turun_benar = turun == naik2 - 1 and turun >= 1

    print()
    if naik_benar and turun_benar:
        print('  HASIL: TOMBOL + DAN − BERFUNGSI')
        return True

    print(f'  HASIL: TIDAK BERFUNGSI (naik benar: {naik_benar}, turun benar: {turun_benar})')
    return False


if __name__ == '__main__':
    sys.exit(0 if main() else 2)
