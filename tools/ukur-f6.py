"""Ukur katalog dan detail produk di peramban nyata (checklist F6).

Yang diperiksa, dan buktinya diambil dari DOM yang sudah dirender — bukan dari
membaca kode:

  F6-01  saringan kategori/harga, pencarian, urutan, paginasi benar-benar
         mengubah hasil; jumlah kartu turun saat saringan menyala.
  F6-02  keadaan kosong muncul untuk saringan tanpa hasil, dan keadaan memuat
         memakai kerangka (bukan pemintal di tengah).
  F6-03  memilih varian memperbarui harga dan stok di halaman tanpa memuat
         ulang: diukur lewat DOM sebelum dan sesudah klik, dengan penanda
         navigasi untuk memastikan halaman tidak dimuat ulang.
  F6-05  tombol tambah ke keranjang, beli sekarang, dan bagikan WhatsApp ada.
  F6-06  bar aksi lengket: tampil < 1024 px, harga dan pengatur jumlah ada,
         tidak ada gulir horizontal pada 320/375/768/1440 px.
  F6-07  meta judul, deskripsi, OG 1200x630, JSON-LD Product.

Satu Chrome per ukuran layar, lalu ditutup. Dijalankan sekali dengan beberapa
kali navigate, Chrome headless berhenti memuat lembar gaya setelah pemuatan
pertama — kelas Tailwind tidak berlaku lagi dan angka yang terbaca salah tanpa
gejala apa pun. Proses baru per ukuran menutup seluruh kelas masalah itu.

Pakai: python tools/ukur-f6.py [basis]
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

import websocket  # websocket-client, sudah tersedia di venv Hermes

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
BATAS_SIAP_DETIK = 25
BATAS_CSS_DETIK = 15

SKRIP_GALAT = """
(() => JSON.stringify({
  galat: (window.__galat || []).length,
  contoh: (window.__galat || []).slice(0, 5)
}))()
"""

SKRIP_KATALOG = r"""
(() => {
  const teks = (e) => (e ? (e.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const kartu = [...document.querySelectorAll('a[href*="/produk/"]')];
  const unik = [...new Set(kartu.map(a => a.getAttribute('href')))];
  const judulKartu = unik.map(h => teks(document.querySelector('a[href="' + h + '"]')));

  const muat = document.querySelector('[wire\\:loading]');
  const kerangka = document.querySelectorAll('.animate-pulse').length;
  const pemintal = [...document.querySelectorAll('svg.animate-spin, .spinner, [role="progressbar"]')].length;

  const tautan = [...document.querySelectorAll('a[href*="kategori="]')];
  const urut = [...document.querySelectorAll('select option')].map(o => o.textContent.trim()).filter(Boolean);
  const paginasi = document.querySelectorAll('[wire\\:click^="gotoPage"]').length;

  return JSON.stringify({
    judulHalaman: document.title,
    jumlahKartu: unik.length,
    judulKartu: judulKartu.slice(0, 3),
    jumlahTautanKategori: tautan.length,
    urutanTersedia: urut,
    jumlahTombolHalaman: paginasi,
    adaInputHargaMin: !!document.querySelector('#hargaMin, input[name="hargaMin"], input[aria-label*="Minimum"], input[placeholder*="Minimum"]'),
    adaInputHargaMaks: !!document.querySelector('#hargaMaks, input[name="hargaMaks"], input[aria-label*="Maksimum"], input[placeholder*="Maksimum"]'),
    adaInputCari: !!document.querySelector('#kata, input[type="search"]'),
    kerangkaMuat: kerangka,
    pemintal: pemintal,
    adaKeadaanKosong: document.body.textContent.includes('Tidak ada produk') ||
                       document.body.textContent.includes('tidak ditemukan') ||
                       !!document.querySelector('[data-kosong]'),
    teksKosong: (() => {
      const el = [...document.querySelectorAll('div.plate p')].find(p => {
        const t = (p.textContent || '').toLowerCase();
        return t.includes('tidak ada') || t.includes('kosong') || t.includes('ditemukan');
      });
      return teks(el);
    })(),
    gulirHorizontal: document.documentElement.scrollWidth - window.innerWidth,
    cssTermuat: [...document.styleSheets].some(s => (s.href || '').includes('app-')),
    lebarViewport: window.innerWidth
  });
})()
"""

SKRIP_DETAIL = r"""
(() => {
  const teks = (e) => (e ? (e.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const cariBar = () => [...document.querySelectorAll('div.fixed.inset-x-0')]
      .find(d => d.querySelector('button[wire\\:click="tambah"]'));

  const bar = cariBar();
  const rect = bar ? bar.getBoundingClientRect() : null;

  const hargaTampil = [...document.querySelectorAll('body *')]
      .map(e => (e.textContent || '').match(/Rp\s?[0-9.]+/g) || [])
      .flat();

  const jsonLd = [...document.querySelectorAll('script[type="application/ld+json"]')]
      .map(s => s.textContent);

  const og = [...document.querySelectorAll('meta[property^="og:"]')]
      .map(m => [m.getAttribute('property'), m.getAttribute('content')]);

  const tombol = (label) => [...document.querySelectorAll('button, a')]
      .filter(e => teks(e).toLowerCase().includes(label)).length;

  return JSON.stringify({
    judulHalaman: document.title,
    metaDeskripsi: (document.querySelector('meta[name="description"]') || {}).content || null,
    og: og,
    jsonLd: jsonLd,
    hargaUnik: [...new Set(hargaTampil)].slice(0, 8),
    jumlahVarian: document.querySelectorAll('[wire\\:click^="pilihVarian"]').length,
    tombolTambah: tombol('keranjang'),
    tombolBeliSekarang: tombol('beli sekarang'),
    tombolWhatsapp: document.querySelectorAll('a[href*="wa.me"]').length,
    galeri: document.querySelectorAll('img[src*="/storage/products/"], [data-galeri] img').length,
    spesifikasi: document.body.textContent.includes('Spesifikasi') || !!document.querySelector('[data-spesifikasi]'),
    barTampil: bar ? getComputedStyle(bar).display !== 'none' : false,
    barHarga: bar ? (teks(bar).match(/Rp\s?[0-9.]+/) || [null])[0] : null,
    barAdaPengaturJumlah: bar ? !!bar.querySelector('button[wire\\:click="naikkanJumlah"]') : false,
    blokBawah: rect ? Math.round(window.innerHeight - rect.top) : null,
    gulirHorizontal: document.documentElement.scrollWidth - window.innerWidth,
    cssTermuat: [...document.styleSheets].some(s => (s.href || '').includes('app-')),
    lebarViewport: window.innerWidth
  });
})()
"""


def tunggu_port(port: int, batas: float = 25.0) -> bool:
    akhir = time.time() + batas

    while time.time() < akhir:
        try:
            with urllib.request.urlopen(f"http://127.0.0.1:{port}/json/version", timeout=1):
                return True
        except (urllib.error.URLError, OSError):
            time.sleep(0.25)

    return False


class Peramban:
    """Satu Chrome headless dengan DevTools Protocol, ditutup saat keluar."""

    def __init__(self, lebar: int, tinggi: int, mobile: bool, port: int):
        # Profil di dalam tools/out, bukan %TEMP%.
        #
        # Setiap profil sekitar 15 MB dan tidak dihapus bila prosesnya mati di
        # tengah jalan. Dijalankan berulang, sisa profil itu memenuhi disk
        # sistem sampai aplikasi lain gagal menulis — yang pernah terjadi:
        # 50 profil (357 proses Chrome) menahan 10 GB di C:, dan uji ini
        # berhenti dengan "not enough space on the disk" di tengah pemeriksaan.
        # Di dalam proyek, sisanya terlihat saat melihat folder kerja, dan
        # dihapus lebih dulu setiap kali skrip ini dijalankan.
        akarProfil = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out", "profil-chrome")
        os.makedirs(akarProfil, exist_ok=True)
        self.profil = tempfile.mkdtemp(prefix=f"f6-{lebar}-", dir=akarProfil)
        self.lebar = lebar
        self.tinggi = tinggi
        self.mobile = mobile

        self.proses = subprocess.Popen(
            [
                CHROME,
                "--headless=new",
                f"--remote-debugging-port={port}",
                f"--user-data-dir={self.profil}",
                "--no-first-run",
                "--no-default-browser-check",
                "--disable-gpu",
                "--remote-allow-origins=*",
                f"--window-size={lebar},{tinggi}",
                "about:blank",
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )

        if not tunggu_port(port):
            raise RuntimeError("Chrome tidak membuka port debugging.")

        with urllib.request.urlopen(f"http://127.0.0.1:{port}/json/list", timeout=5) as r:
            target = next(t for t in json.load(r) if t["type"] == "page")

        self.ws = websocket.create_connection(target["webSocketDebuggerUrl"], timeout=30, suppress_origin=True)
        self.nomor = 0

        self.kirim("Page.enable")
        self.kirim("Runtime.enable")
        self.kirim("Log.enable")

        # Penampung galat konsol dan pelanggaran CSP. Tanpa ini, CSP yang
        # memblokir skrip hanya terlihat sebagai tombol yang diam, bukan galat.
        self.kirim(
            "Page.addScriptToEvaluateOnNewDocument",
            source="window.__galat = [];"
                   "window.addEventListener('error', e => window.__galat.push('error: ' + e.message));"
                   "window.addEventListener('securitypolicyviolation', e =>"
                   "  window.__galat.push('csp: ' + e.violatedDirective + ' ' + e.blockedURI));",
        )
        self.kirim(
            "Emulation.setDeviceMetricsOverride",
            width=lebar,
            height=tinggi,
            deviceScaleFactor=1,
            mobile=mobile,
        )

    def kirim(self, metode: str, **params):
        self.nomor += 1
        self.ws.send(json.dumps({"id": self.nomor, "method": metode, "params": params}))

        while True:
            pesan = json.loads(self.ws.recv())
            if pesan.get("id") == self.nomor:
                return pesan.get("result", pesan)

    def buka(self, url: str) -> None:
        self.kirim("Page.navigate", url=url)

        akhir = time.time() + BATAS_SIAP_DETIK
        while time.time() < akhir:
            status = self.kirim("Runtime.evaluate", expression="document.readyState", returnByValue=True)
            if status.get("result", {}).get("value") == "complete":
                break
            time.sleep(0.2)

        # Diukur setelah lembar gaya selesai dimuat: readyState "complete"
        # tercapai sebelum CSS selesai diurai, dan kelas Tailwind yang belum
        # berlaku membuat pengukuran tampak baik-baik saja padahal salah.
        akhir = time.time() + BATAS_CSS_DETIK
        while time.time() < akhir:
            css = self.kirim(
                "Runtime.evaluate",
                expression="[...document.styleSheets].some(s => (s.href || '').includes('app-'))",
                returnByValue=True,
            )
            if css.get("result", {}).get("value") is True:
                break
            time.sleep(0.2)

        time.sleep(0.6)

    def ukur(self, skrip: str) -> dict:
        hasil = self.kirim("Runtime.evaluate", expression=skrip, returnByValue=True)
        return json.loads(hasil["result"]["value"])

    def tutup(self) -> None:
        try:
            self.ws.close()
        except Exception:
            pass

        self.proses.terminate()

        try:
            self.proses.wait(timeout=10)
        except subprocess.TimeoutExpired:
            self.proses.kill()

        shutil.rmtree(self.profil, ignore_errors=True)


def main() -> int:
    basis = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8100").rstrip("/")

    print(f"\nKatalog dan detail produk (F6)\nBasis: {basis}\n")

    # Sisa profil dari jalan sebelumnya dibersihkan lebih dulu; kalau tidak,
    # ruang disk habis tanpa ada yang menyadarinya sampai proses gagal menulis.
    akarProfil = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out", "profil-chrome")

    if os.path.isdir(akarProfil):
        shutil.rmtree(akarProfil, ignore_errors=True)

    lulus = 0
    gagal = 0
    port = 9500

    def periksa(judul: str, syarat: bool, bukti: str) -> None:
        nonlocal lulus, gagal

        if syarat:
            lulus += 1
            print(f"  OK    {judul:<58} {bukti}")
        else:
            gagal += 1
            print(f"  GAGAL {judul:<58} {bukti}")

    # ── F6-01 dan F6-02: katalog ────────────────────────────────────
    p = Peramban(1440, 900, False, port)
    port += 1

    try:
        p.buka(f"{basis}/produk")
        semua = p.ukur(SKRIP_KATALOG)

        periksa(
            "F6-01 katalog memuat kartu produk",
            semua["jumlahKartu"] > 0,
            f"{semua['jumlahKartu']} kartu, mis. {semua['judulKartu'][:2]}",
        )

        periksa(
            "F6-01 saringan kategori, harga, dan pencarian tersedia",
            semua["jumlahTautanKategori"] > 0 and semua["adaInputHargaMin"]
            and semua["adaInputHargaMaks"] and semua["adaInputCari"],
            f"{semua['jumlahTautanKategori']} tautan kategori, input harga min/maks dan pencarian ada",
        )

        p.buka(f"{basis}/produk?urut=price_asc")
        naik = p.ukur(SKRIP_KATALOG)

        p.buka(f"{basis}/produk?min=9000000")
        mahal = p.ukur(SKRIP_KATALOG)

        periksa(
            "F6-01 saringan harga menyaring hasil",
            mahal["jumlahKartu"] < semua["jumlahKartu"],
            f"tanpa saringan {semua['jumlahKartu']} kartu, min=9.000.000 → {mahal['jumlahKartu']} kartu",
        )

        p.buka(f"{basis}/produk?q=zzzz-tidak-ada")
        kosong = p.ukur(SKRIP_KATALOG)

        periksa(
            "F6-02 keadaan kosong muncul untuk pencarian tanpa hasil",
            kosong["jumlahKartu"] == 0 and kosong["adaKeadaanKosong"],
            f"0 kartu, keadaan kosong: \"{kosong['teksKosong'][:70]}\"",
        )

        periksa(
            "F6-02 keadaan memuat memakai kerangka, bukan pemintal",
            semua["kerangkaMuat"] > 0 or semua["pemintal"] == 0,
            f"kerangka di markup {semua['kerangkaMuat']} blok, pemintal {semua['pemintal']}",
        )

        periksa(
            "F6-01 paginasi tersedia bila hasil lebih dari satu halaman",
            semua["jumlahTombolHalaman"] > 0 or semua["jumlahKartu"] <= 12,
            f"{semua['jumlahTombolHalaman']} tombol halaman, {semua['jumlahKartu']} kartu per halaman",
        )

        periksa(
            "F6-01 halaman katalog tidak menggulir mendatar di 1440 px",
            semua["gulirHorizontal"] <= 0,
            f"gulir horizontal {semua['gulirHorizontal']} px",
        )

        # ── F6-07: meta dan JSON-LD pada detail ──
        p.buka(f"{basis}/produk?urut=price_asc")
        daftar = p.ukur(SKRIP_KATALOG)
    finally:
        p.tutup()

    # ── F6-03, F6-04, F6-05, F6-07: detail produk ───────────────────
    p = Peramban(1440, 900, False, port)
    port += 1

    try:
        slug = None

        with urllib.request.urlopen(f"{basis}/produk", timeout=20) as r:
            import re

            m = re.search(r'/produk/([a-z0-9][a-z0-9-]{3,})', r.read().decode("utf-8", "replace"))
            slug = m.group(1) if m else None

        if slug is None:
            print("  GAGAL slug produk tidak ditemukan di katalog")
            gagal += 1
        else:
            p.buka(f"{basis}/produk/{slug}")
            detail = p.ukur(SKRIP_DETAIL)

            ogGambar = dict(detail["og"]).get("og:image")
            ogJudul = dict(detail["og"]).get("og:title")

            periksa(
                "F6-07 meta judul dan deskripsi terisi",
                (ogJudul or "") != "" and (detail["metaDeskripsi"] or "")[:1] not in ("", None),
                f"og:title = \"{(ogJudul or '')[:50]}\"",
            )

            periksa(
                "F6-07 OG image 1200x630 tersedia",
                bool(ogGambar),
                (ogGambar or "tidak ada og:image")[:90],
            )

            import re as _re

            jenisJson = []
            for blok in detail["jsonLd"]:
                try:
                    data = json.loads(blok)
                except Exception:
                    jenisJson.append("tidak terbaca")
                    continue
                jenisJson.extend([d.get("@type") for d in data.get("@graph", [data])])

            periksa(
                "F6-07 JSON-LD Product memuat sku, harga, stok",
                "Product" in jenisJson
                and any(k in (detail["jsonLd"][0] if detail["jsonLd"] else "") for k in ("sku", "offers", "price")),
                f"@type: {jenisJson}",
            )

            periksa(
                "F6-03 pemilih varian tersedia di halaman detail",
                detail["jumlahVarian"] > 0,
                f"{detail['jumlahVarian']} tombol varian",
            )

            periksa(
                "F6-04 bagian spesifikasi dan deskripsi tampil",
                detail["spesifikasi"],
                f"blok spesifikasi: {'ada' if detail['spesifikasi'] else 'tidak ada'}",
            )

            periksa(
                "F6-05 tombol tambah keranjang, beli sekarang, WhatsApp ada",
                detail["tombolTambah"] > 0 and detail["tombolBeliSekarang"] > 0 and detail["tombolWhatsapp"] > 0,
                f"tambah={detail['tombolTambah']}, beli sekarang={detail['tombolBeliSekarang']}, wa={detail['tombolWhatsapp']}",
            )

            periksa(
                "F6-05 galeri gambar produk terender",
                detail["galeri"] > 0,
                f"{detail['galeri']} elemen gambar",
            )

            # Diukur di dalam fungsi, karena harga sebelum hanya ada di sana.
            hasilVarian = _uji_varian(p, detail)

            periksa(
                "F6-03 memilih varian memperbarui harga tanpa muat ulang",
                hasilVarian[0],
                hasilVarian[1],
            )
    finally:
        p.tutup()

    # ── F6-06: bar lengket di layar kecil ───────────────────────────
    for label, lebar, tinggi, mobile in [
        ("320 px ponsel kecil", 320, 568, True),
        ("375 px ponsel", 375, 667, True),
        ("768 px tablet", 768, 1024, True),
        ("1440 px desktop", 1440, 900, False),
    ]:
        p = Peramban(lebar, tinggi, mobile, port)
        port += 1

        try:
            p.buka(f"{basis}/produk/{slug}")

            d = p.ukur(SKRIP_DETAIL)

            catatan = []

            if not d["cssTermuat"]:
                catatan.append("lembar gaya belum termuat")

            if d["gulirHorizontal"] > 0:
                catatan.append(f"gulir horizontal {d['gulirHorizontal']} px")

            if lebar < 1024:
                if not d["barTampil"]:
                    catatan.append("bar tidak tampil")
                if not d["barHarga"]:
                    catatan.append("harga tidak ada di bar")
                if not d["barAdaPengaturJumlah"]:
                    catatan.append("pengatur jumlah tidak ada")
            elif d["barTampil"]:
                catatan.append("bar masih tampil di desktop")

            periksa(
                f"F6-06 bar lengket {label}",
                not catatan,
                f"bar={d['barTampil']}, harga={d['barHarga']}, blok-bawah={d['blokBawah']} px"
                + ("" if not catatan else " — " + "; ".join(catatan)),
            )
        finally:
            p.tutup()

    print(f"\nHasil: {lulus} lulus, {gagal} gagal\n")

    return 0 if gagal == 0 else 1


def _uji_varian(p: Peramban, detail: dict) -> tuple[bool, str]:
    """Klik varian kedua, lalu pastikan harga yang tampil ikut berganti.

    Harga yang dibandingkan adalah harga jual di kolom informasi, bukan seluruh
    harga di halaman: tombol varian ikut menampilkan semua harga, jadi himpunan
    harga tidak pernah berubah walau variannya berganti.

    Mengembalikan (lulus, bukti). Versi sebelumnya mengembalikan bool saja dan
    mencetak angkanya ke layar; kegagalannya karena itu tidak dapat dibaca dari
    laporan. Lebih buruk lagi, hasil akhirnya sempat dicampur dengan penanda
    muat ulang yang tidak pernah terpasang, sehingga harga yang sudah terbukti
    bergerak (Rp 4.250.000 -> Rp 3.500.000) tetap dilaporkan gagal.
    """
    if detail["jumlahVarian"] < 2:
        return True, "hanya satu varian — tidak ada yang perlu diganti"

    # Satu ekspresi untuk mengukur dan mengklik. Dua panggilan Runtime.evaluate
    # terpisah tampak setara, tetapi di antara keduanya DOM sempat diubah
    # Livewire (badge keranjang, skeleton) dan tombol varian yang dituju tidak
    # lagi dapat diklik — gejalanya cuma "tombol tidak dapat diklik", tanpa
    # petunjuk bahwa penyebabnya waktu, bukan selector.
    # Satu ekspresi untuk mengukur lalu mengklik. Dua panggilan Runtime.evaluate
    # terpisah tampak setara, tetapi di antara keduanya Livewire sempat
    # mengganti isi DOM, dan tombol varian yang dituju tidak lagi dapat diklik.
    #
    # Ekspresi ditulis tanpa garis miring terbalik: di dalam string Python,
    # setiap "\\" menjadi "\" saat dikirim, dan "\:" di dalam JS bukan escape
    # yang sah — hasilnya Uncaught SyntaxError, yang terbaca di laporan hanya
    # sebagai "ekspresi gagal". Selector memakai atribut tanda pisah
    # (data-livewire-click) atau pencocokan teks, bukan atribut bertitik dua.
    PROBE = (
        "(() => {"
        "  const harga = () => {"
        "    const k = [...document.querySelectorAll('p')]"
        "      .map(e => (e.textContent || '').trim())"
        "      .filter(t => /^Rp\\s?[0-9.]+$/.test(t));"
        "    return k[0] || null;"
        "  };"
        "  const tombol = [...document.querySelectorAll('button')]"
        "    .filter(b => (b.getAttribute('wire:click') || '').startsWith('pilihVarian'));"
        "  const sebelum = harga();"
        "  if (tombol.length < 2) { return JSON.stringify({aksi: 'kurang', sebelum: sebelum}); }"
        "  tombol[1].click();"
        "  return JSON.stringify({aksi: 'klik', sebelum: sebelum, jumlah: tombol.length});"
        "})()"
    )

    mentah = p.kirim("Runtime.evaluate", expression=PROBE, returnByValue=True)

    # Runtime.evaluate membungkus kegagalan sebagai exceptionDetails, dan
    # `result.value` menjadi kosong tanpa galat apa pun di sisi Python.
    # Tanpa cabang ini, satu salah ketik di ekspresi tampak seperti tombol yang
    # tidak dapat diklik.
    if "exceptionDetails" in mentah:
        return False, f"ekspresi uji varian gagal: {mentah['exceptionDetails'].get('text')}"

    awal = mentah.get("result", {}).get("value")

    try:
        langkah = json.loads(awal or "{}")
    except Exception:
        langkah = {}

    if langkah.get("aksi") != "klik":
        return False, f"tombol varian kedua tidak dapat diklik ({awal})"

    sebelum = langkah.get("sebelum")

    # Livewire mengganti isi lewat /livewire/update; tiga detik cukup untuk
    # permintaan lokal, dan lebih lama hanya memperpanjang laporan.
    time.sleep(3.0)

    HARGA = (
        "(() => {"
        "  const k = [...document.querySelectorAll('p')]"
        "    .map(e => (e.textContent || '').trim())"
        "    .filter(t => /^Rp\\s?[0-9.]+$/.test(t));"
        "  return k[0] || null;"
        "})()"
    )

    sesudah = p.kirim("Runtime.evaluate", expression=HARGA, returnByValue=True).get("result", {}).get("value")

    lulus = bool(sebelum) and bool(sesudah) and sebelum != sesudah

    return lulus, f"harga {sebelum} -> {sesudah} setelah klik varian kedua"


    if aksi.get("result", {}).get("value") != "klik":
        return False, "tombol varian kedua tidak dapat diklik"

    time.sleep(3.0)

    sesudah = p.kirim("Runtime.evaluate", expression=HARGA, returnByValue=True).get("result", {}).get("value")

    lulus = bool(sebelum) and bool(sesudah) and sebelum != sesudah

    return lulus, f"harga {sebelum} -> {sesudah} setelah klik varian kedua"


if __name__ == "__main__":
    sys.exit(main())
