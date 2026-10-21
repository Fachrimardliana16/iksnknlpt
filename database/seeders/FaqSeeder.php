<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * FAQ bawaan toko.
 *
 * Isinya bukan contoh asal-asalan: semuanya pertanyaan yang benar-benar
 * ditanyakan pembeli knalpot — soal legalitas suara, pengiriman luar negeri,
 * dan cara bayar — dan jawabannya ditulis supaya bisa langsung dipakai, bukan
 * "lorem ipsum" yang harus diganti sebelum situs diluncurkan.
 *
 * Dua bahasa untuk semuanya, karena pembeli luar negeri membuka situs ini
 * dalam bahasa Inggris dan jawaban Indonesia hanya akan menolong sebagian.
 *
 * IDEMPOTEN: memakai updateOrCreate pada question_id. Menjalankan seeder dua
 * kali tidak menggandakan FAQ, dan tidak menghapus butir yang sudah diubah
 * admin lewat panel.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $butir = [
            [
                'group_key' => 'pembayaran',
                'question_id' => 'Metode pembayaran apa saja yang tersedia?',
                'question_en' => 'What payment methods do you accept?',
                'answer_id' => "Transfer bank manual dan PayPal.\n\nUntuk transfer manual, pesanan menunggu pembayaran sampai 24 jam. Setelah membayar, unggah bukti transfer lewat halaman pesanan supaya admin dapat memverifikasi. Pesanan yang belum dibayar dalam 24 jam dibatalkan otomatis dan stoknya dikembalikan.",
                'answer_en' => "Manual bank transfer and PayPal.\n\nFor manual transfer the order waits for payment for 24 hours. Once you have paid, upload the transfer receipt from the order page so we can verify it. Unpaid orders are cancelled automatically after 24 hours and the stock is released.",
            ],
            [
                'group_key' => 'pembayaran',
                'question_id' => 'Apakah harga sudah termasuk pajak dan ongkir?',
                'question_en' => 'Are prices inclusive of tax and shipping?',
                'answer_id' => 'Harga produk belum termasuk ongkir dan pajak. Keduanya dihitung terpisah di halaman checkout dan ditampilkan sebelum Anda menekan tombol pesan, sehingga jumlah akhir selalu terlihat lebih dulu.',
                'answer_en' => 'Product prices exclude shipping and tax. Both are calculated separately on the checkout page and shown before you place the order, so the final amount is always visible first.',
            ],
            [
                'group_key' => 'pengiriman',
                'question_id' => 'Apakah bisa kirim ke luar negeri?',
                'question_en' => 'Do you ship internationally?',
                'answer_id' => "Bisa. Pilih negara tujuan di halaman checkout, lalu isi alamat lengkap dalam huruf Latin.\n\nOngkir internasional dihitung manual oleh admin setelah pesanan masuk, karena tarifnya bergantung pada berat paket dan negara tujuan. Anda akan dihubungi lewat surel atau WhatsApp sebelum pesanan diproses, dan pembayaran baru dimulai setelah ongkir disepakati.\n\nBea masuk dan pajak impor di negara tujuan menjadi tanggungan pembeli.",
                'answer_en' => "Yes. Choose your destination country on the checkout page and write the full address in Latin characters.\n\nInternational shipping is quoted manually by our team after the order arrives, because the rate depends on parcel weight and destination. You will be contacted by email or WhatsApp before the order is processed, and payment only starts once the shipping cost is agreed.\n\nImport duties and taxes in the destination country are the buyer's responsibility.",
            ],
            [
                'group_key' => 'pengiriman',
                'question_id' => 'Berapa lama pesanan dikirim?',
                'question_en' => 'How long does delivery take?',
                'answer_id' => 'Pesanan dikirim pada hari kerja berikutnya setelah pembayaran terverifikasi. Barang ready stock dikirim dari Sleman, Yogyakarta. Lama pengiriman tergantung kurir dan kota tujuan; nomor resi dikirim lewat surel setelah paket diserahkan ke kurir.',
                'answer_en' => 'Orders ship on the next business day after payment is verified. Ready stock is dispatched from Sleman, Yogyakarta, Indonesia. Transit time depends on the courier and destination; the tracking number is emailed once the parcel is handed over.',
            ],
            [
                'group_key' => 'pengiriman',
                'question_id' => 'Bagaimana cara melacak pesanan saya?',
                'question_en' => 'How do I track my order?',
                'answer_id' => 'Buka halaman Lacak Pesanan dan masukkan nomor pesanan beserta email yang Anda pakai saat memesan. Nomor resi kurir juga dikirim ke email Anda begitu paket diserahkan.',
                'answer_en' => 'Open the Track Order page and enter your order number with the email you used when ordering. The courier tracking number is also emailed to you as soon as the parcel is handed over.',
            ],
            [
                'group_key' => 'produk',
                'question_id' => 'Apakah knalpotnya legal untuk dipakai di jalan raya?',
                'question_en' => 'Are the exhausts street legal?',
                'answer_id' => 'Produk yang kami jual ditujukan untuk penggunaan tertutup (sirkuit) kecuali dinyatakan lain pada halaman produk. Ambang kebisingan berbeda-beda di setiap daerah, jadi periksa aturan setempat sebelum memakai di jalan umum. Kami dapat menyediakan baffle peredam bila tersedia untuk model terkait.',
                'answer_en' => 'Products are intended for closed-course use unless the product page states otherwise. Noise limits differ by region, so check your local regulation before riding on public roads. A baffle insert may be available for the relevant model.',
            ],
            [
                'group_key' => 'produk',
                'question_id' => 'Apakah bisa dipasang sendiri?',
                'question_en' => 'Can I install it myself?',
                'answer_id' => 'Sebagian besar produk bolt-on dan dapat dipasang dengan perkakas tangan biasa. Untuk model tertentu diperlukan penyetelan ulang (remap) ECU agar campuran bahan bakar tetap benar. Bila ragu, pasang di bengkel langganan Anda — pemasangan yang salah dapat merusak mesin dan tidak tertutup garansi.',
                'answer_en' => 'Most products are bolt-on and can be fitted with hand tools. Some models require an ECU remap so the fuel mixture stays correct. If in doubt, have it installed by a workshop you trust — incorrect installation can damage the engine and is not covered by warranty.',
            ],
            [
                'group_key' => 'umum',
                'question_id' => 'Bagaimana cara menghubungi admin?',
                'question_en' => 'How do I contact you?',
                'answer_id' => 'Lewat halaman Kontak, WhatsApp, atau balasan surel pesanan Anda. Kami membalas pada jam kerja, Senin sampai Sabtu. Sebutkan nomor pesanan bila pertanyaan Anda menyangkut pesanan yang sudah dibuat.',
                'answer_en' => 'Through the Contact page, WhatsApp, or by replying to your order email. We answer during business hours, Monday to Saturday. Please include your order number if the question is about an existing order.',
            ],
        ];

        foreach ($butir as $i => $b) {
            Faq::query()->updateOrCreate(
                ['question_id' => $b['question_id']],
                $b + [
                    'display_order' => $i + 1,
                    'is_active' => true,
                    'tampil_di_beranda' => true,
                ],
            );
        }
    }
}
