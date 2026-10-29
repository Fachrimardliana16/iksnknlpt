/* Bar aksi lengket halaman detail produk.
 *
 * Bar ini hanya berguna ketika tombol "Tambah ke Keranjang" di panel sudah
 * tergulir keluar layar. Sebelumnya ia selalu tampil di bawah 1024px,
 * sehingga pengguna ponsel melihat DUA tombol primer yang sama persis di
 * satu layar — dan bar-nya menutupi isi halaman sejak gulir pertama.
 *
 * Perilaku sekarang:
 *
 *   1. Bar disembunyikan dengan atribut `data-tersembunyi` (bukan kelas
 *      Tailwind `hidden`) supaya statusnya terbaca dari DOM: `hidden` dari
 *      utilitas Tailwind tidak bisa dibedakan dari `hidden` milik layar lebar.
 *   2. Bar muncul ketika tombol utama sudah TIDAK terlihat lagi.
 *   3. Pemicunya dua, keduanya perlu:
 *        IntersectionObserver — tombol keluar layar karena tergulir;
 *        posisi gulir > 0        — tombol tidak pernah terlihat sama sekali
 *                                  (mis. tautan #jangkar, halaman dibuka
 *                                  dalam keadaan sudah tergulir).
 *      Tanpa syarat kedua, pengguna yang mendarat di tengah halaman tidak
 *      pernah mendapat jalan menambah barang.
 *   4. Saat bar belum muncul, floating WhatsApp TIDAK perlu diberi jarak
 *      ekstra. Jaraknya dihitung dari ATRIBUT yang sama, sehingga keduanya
 *      tidak mungkin berbeda pendapat soal apakah bar sedang tampil.
 *   5. Tanpa IntersectionObserver (peramban lama), bar langsung ditampilkan —
 *      perilakunya kembali seperti versi yang selalu tampil, bukan hilang.
 *
 * Posisi tombol utama dibaca dari atribut `data-aksi-utama` pada elemen
 * tombol itu sendiri, bukan dari posisi bar: bar sendiri fixed di bawah,
 * jadi ia tidak bisa dijadikan patokan gulir.
 */

const BAR = '.bottom-bar-cart';
const PEMICU = '[data-aksi-utama]';
const AMBANG_GULIR = 8;

/** Alpine (Livewire) bisa mengganti markup setelah navigasi; selalu ambil ulang. */
function pasangBarAksi(akar = document) {
    const bar = akar.querySelector(BAR);
    if (!bar) return;

    const pemicu = akar.querySelector(PEMICU);
    const redup = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let terlihat = false;
    let gulirLewat = window.scrollY > AMBANG_GULIR;

    // `inert` (bukan `hidden`) yang menahan fokus keyboard masuk ke bar yang
    // belum tampil: elemennya tetap dirender, jadi tanpa ini Tab bisa
    // mendarat di tombol tak terlihat. Atribut ini dilepas saat bar tampil.
    const perbarui = () => {
        const tampil = !terlihat && gulirLewat;
        bar.toggleAttribute('data-tersembunyi', !tampil);
        bar.toggleAttribute('inert', !tampil);
        // Status yang sama ditulis ke <html> supaya elemen lain (tombol
        // WhatsApp melayang) bisa menyesuaikan jarak lewat CSS saja — tanpa
        // Alpine, tanpa atribut dinamis di elemen yang memuat x-data.
        document.documentElement.toggleAttribute('data-bar-tampil', tampil);
    };

    // Nama event sengaja TANPA titik dua. Di Alpine, `x-on:bar-aksi:berubah`
    // tidak pernah mendengarkan event bernama `bar-aksi:berubah` — bagian
    // sebelum titik dua dibaca sebagai nama direktif, sehingga pendengarnya
    // hanya menunggu event `berubah`. Nama berstrip tidak punya arti khusus
    // bagi Alpine maupun peramban.
    const NAMA_EVENT = 'bar-aksi-berubah';

    const kabari = () =>
        bar.dispatchEvent(new CustomEvent(NAMA_EVENT, { bubbles: true }));

    if (!('IntersectionObserver' in window)) {
        bar.removeAttribute('data-tersembunyi');
        bar.removeAttribute('inert');
        kabari();
        return;
    }

    // Posisi gulir diperiksa sendiri, tidak bergantung pada hasil pengamat:
    // saat halaman dibuka dengan #jangkar, pengamat melaporkan "tidak
    // terlihat" tanpa pernah ada gulir, dan bar tidak boleh muncul mendadak.
    const pengamat = new IntersectionObserver(
        (entri) => {
            terlihat = entri[0].isIntersecting;
            gulirLewat = window.scrollY > AMBANG_GULIR;
            perbarui();
            kabari();
        },
        { threshold: 0 },
    );

    if (pemicu) pengamat.observe(pemicu);

    window.addEventListener(
        'scroll',
        () => {
            const baru = window.scrollY > AMBANG_GULIR;
            if (baru === gulirLewat) return;
            gulirLewat = baru;
            // Saat bar muncul di tengah gulir, animasikan masuknya. Saat
            // tergulir balik sebelum tombol utama hilang, ia hilang diam-diam
            // supaya tidak terlihat berkedip.
            bar.style.transition = redup() ? 'none' : 'transform 180ms ease, opacity 180ms ease';
            perbarui();
            kabari();
        },
        { passive: true },
    );

    perbarui();
}

document.addEventListener('DOMContentLoaded', () => pasangBarAksi());
document.addEventListener('livewire:navigated', () => pasangBarAksi());
