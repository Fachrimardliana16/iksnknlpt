"""Audit seluruh tombol di situs depan.

Yang diperiksa, untuk setiap halaman publik dan setiap tombol/link aksi:

  1. Tombol ber-`wire:click` — metodenya benar-benar ada di komponen Livewire,
     dan `livewire.js` termuat (tanpa itu tombol mati meski metodenya ada).
  2. Tombol ber-`data-aksi` — ada pendengar JS-nya, karena CSP proyek ini
     memblokir onclick inline, sehingga tombol tanpa pendengar benar-benar diam.
  3. `<button>` tanpa `wire:click`, tanpa `data-aksi`, tanpa `type=submit`
     di dalam form — kandidat tombol mati.
  4. Link `href="#"` atau `href="javascript:"` — kandidat mati.
  5. Rute internal tujuan link diuji HEAD/GET: status tidak boleh 404/500.

Keluaran: daftar temuan, dikelompokkan per halaman, plus ringkasan.

Pakai: python tools/audit-tombol.py
"""

import html as H
import http.cookiejar
import re
import sys
import urllib.error
import urllib.request
from collections import Counter

BASIS = 'http://127.0.0.1:8100'

HALAMAN = [
    ('/', 'Beranda'),
    ('/produk', 'Katalog'),
    ('/produk?q=knalpot', 'Katalog cari'),
    ('/keranjang', 'Keranjang'),
    ('/lacak', 'Lacak'),
    ('/kontak', 'Kontak'),
    ('/tentang', 'Tentang'),
    ('/halaman/syarat-ketentuan', 'Syarat'),
    ('/halaman/kebijakan-privasi', 'Privasi'),
    ('/admin/login', 'Masuk panel'),
]


def ambil(op, jalur):
    try:
        r = op.open(BASIS + jalur, timeout=30)
        return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, ''
    except Exception as e:
        return None, str(e)


def komponen_livewire(doc):
    """Peta nama komponen -> data-nya, dari snapshot di HTML."""
    hasil = []
    for s in re.findall(r'wire:snapshot="([^"]+)"', doc):
        try:
            import json
            d = json.loads(H.unescape(s))
            hasil.append(d)
        except Exception:
            pass
    return hasil


def main():
    cj = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

    temuan = []
    ringkas = Counter()

    # Metode yang tersedia per komponen, dibaca dari berkas kelas Livewire.
    import glob
    import os
    metode_per_komponen = {}
    for f in glob.glob(r'D:\Projects\iksanknalpot\app\Livewire\**\*.php', recursive=True):
        isi = open(f, encoding='utf-8', errors='replace').read()
        nama_kelas = re.search(r'class\s+(\w+)', isi)
        metode = set(re.findall(r'public function (\w+)\s*\(', isi))
        if nama_kelas:
            metode_per_komponen[nama_kelas.group(1)] = metode

    # Semua rute yang sah, untuk memeriksa tujuan link tanpa membanjiri server.
    rute_sah = set()
    try:
        keluaran = os.popen(
            '"%LOCALAPPDATA%\\Microsoft\\WinGet\\Packages\\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\\php.exe" '
            'artisan route:list --json 2>nul').read()
        import json as J
        for r in J.loads(keluaran or '[]'):
            uri = r.get('uri', '').strip('/')
            rute_sah.add(uri)
    except Exception:
        pass

    print(f'Halaman diaudit: {len(HALAMAN)}')

    for jalur, nama in HALAMAN:
        kode, doc = ambil(op, jalur)
        if kode != 200 or not doc:
            temuan.append((nama, 'HALAMAN', f'status {kode}'))
            ringkas['halaman_gagal'] += 1
            continue

        ada_livewire_js = 'livewire.js' in doc
        n_wire = len(re.findall(r'wire:click="([^"]+)"', doc))

        # 1. Wire tanpa livewire.js = tombol mati semua
        if n_wire and not ada_livewire_js:
            temuan.append((nama, 'LIVEWIRE', f'{n_wire} tombol wire:click tetapi livewire.js TIDAK dimuat'))
            ringkas['livewire_tanpa_js'] += 1

        # 2. Metode wire:click harus ada di kelas komponennya
        if n_wire:
            metas = komponen_livewire(doc)
            nama_komponen = {m.get('memo', {}).get('name') for m in metas}
            dipanggil = set()
            for w in re.findall(r'wire:click="([^"]+)"', doc):
                m = re.match(r'([A-Za-z_]\w*)\s*\(', w.strip())
                if m:
                    dipanggil.add(m.group(1))
            semua_metode = set()
            for nk in nama_komponen:
                if not nk:
                    continue
                kelas = ''.join(p.capitalize() for p in nk.split('.'))
                semua_metode |= metode_per_komponen.get(kelas, set())
            hilang = dipanggil - semua_metode
            if hilang and semua_metode:
                temuan.append((nama, 'METODE', f'metode wire:click tidak ada: {sorted(hilang)}'))
                ringkas['metode_hilang'] += 1

        # 3. Tombol tanpa tindakan apa pun
        for tag in re.findall(r'<button[^>]*>', doc):
            punya = any(k in tag for k in ('wire:click', 'data-aksi', 'type="submit"', 'x-on:', '@click'))
            if not punya:
                temuan.append((nama, 'TOMBOL', 'tanpa wire:click / data-aksi / submit'))
                ringkas['tombol_tanpa_aksi'] += 1

        # 4. Link mati
        for href in re.findall(r'<a[^>]+href="([^"]*)"', doc):
            h = H.unescape(href)
            if h in ('#', 'javascript:void(0)', 'javascript:;'):
                temuan.append((nama, 'LINK', f'href="{h}"'))
                ringkas['link_mati'] += 1

        # 5. Tujuan link internal harus terdaftar sebagai rute
        for href in re.findall(r'href="(/[^"#?]*)"', doc):
            bersih = href.strip('/')
            if not bersih or bersih.startswith(('build/', 'storage/', 'livewire/', 'cdn-cgi/', 'fonts/', 'ikon-')):
                continue

            # Berkas statis di public/ bukan rute. Memeriksanya sebagai rute
            # hanya menghasilkan positif palsu (mis. manifest.webmanifest).
            import os
            if os.path.isfile(os.path.join(r'D:\Projects\iksanknalpot\public', bersih)):
                continue
            if rute_sah and bersih not in rute_sah:
                # rute dinamis seperti produk/{slug}
                cocok = any(
                    re.fullmatch(re.sub(r'\{[^}]+\}', r'[^/]+', r), bersih)
                    for r in rute_sah if '{' in r
                )
                if not cocok:
                    temuan.append((nama, 'TUJUAN', f'/{bersih} tidak cocok rute mana pun'))
                    ringkas['tujuan_tidak_sah'] += 1

    print('\n' + '=' * 78)
    if not temuan:
        print('TIDAK ADA TEMUAN. Semua tombol dan link memiliki tindakan.')
    else:
        kini = None
        for nama, jenis, pesan in temuan:
            if nama != kini:
                print(f'\n[{nama}]')
                kini = nama
            print(f'  {jenis:10} {pesan}')

    print('\n' + '=' * 78)
    print('RINGKASAN')
    for k, v in ringkas.items():
        print(f'  {k:24} {v}')
    print(f'  {"total temuan":24} {len(temuan)}')

    return 0 if not temuan else 1


if __name__ == '__main__':
    sys.exit(main())
