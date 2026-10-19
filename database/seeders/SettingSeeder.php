<?php

namespace Database\Seeders;

use App\Enums\GrupPengaturan;
use App\Models\Setting;
use App\Support\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Seluruh kunci pengaturan pada PRD Bagian 7.4.
 *
 * Idempoten: memakai updateOrCreate berdasarkan key, jadi dapat dijalankan
 * berulang tanpa menggandakan baris.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $G = fn (GrupPengaturan $g) => $g->value;

        $baris = [
            // ── Tab 1: Identitas ──────────────────────────────────────
            ['site_name', 'Iksan Knalpot', $G(GrupPengaturan::Identitas), 'text', 'Nama toko', false, true],
            ['site_tagline', 'Knalpot presisi, diukur sebelum dipotong.', $G(GrupPengaturan::Identitas), 'text', 'Tagline', false, true],
            ['site_logo_light', null, $G(GrupPengaturan::Identitas), 'image', 'Logo tema terang', false, true],
            ['site_logo_dark', null, $G(GrupPengaturan::Identitas), 'image', 'Logo tema gelap', false, true],
            ['site_favicon', null, $G(GrupPengaturan::Identitas), 'image', 'Favicon', false, true],
            ['site_description', 'Toko knalpot custom dan racing di Yogyakarta. Full system, slip-on, header, dan muffler untuk motor harian maupun kompetisi.', $G(GrupPengaturan::Identitas), 'textarea', 'Meta description', false, true],

            // ── Tab 2: Kontak dan Lokasi ──────────────────────────────
            ['whatsapp_number', '628123456789', $G(GrupPengaturan::Kontak), 'text', 'Nomor WhatsApp', false, true],
            ['whatsapp_message_template', 'Halo Iksan Knalpot, saya ingin bertanya tentang knalpot.', $G(GrupPengaturan::Kontak), 'textarea', 'Template sapaan WhatsApp', false, true],
            ['contact_email', 'halo@iksanknalpot.test', $G(GrupPengaturan::Kontak), 'email', 'Email publik', false, true],
            ['contact_email_cc', null, $G(GrupPengaturan::Kontak), 'email', 'Email salinan notifikasi', false, true],
            ['phone_number', '(0274) 123456', $G(GrupPengaturan::Kontak), 'text', 'Telepon kantor', false, true],
            ['operational_hours', 'Senin - Sabtu, 08.00 - 17.00 WIB', $G(GrupPengaturan::Kontak), 'text', 'Jam operasional', false, true],
            ['address_full', 'Jl. Ringroad Utara No. 128, Condongcatur, Depok, Sleman, DI Yogyakarta 55283', $G(GrupPengaturan::Kontak), 'textarea', 'Alamat lengkap', false, true],
            ['maps_embed', null, $G(GrupPengaturan::Kontak), 'textarea', 'Sematan Google Maps', false, true],
            ['maps_lat', '-7.7626', $G(GrupPengaturan::Kontak), 'number', 'Lintang', false, true],
            ['maps_lng', '110.3942', $G(GrupPengaturan::Kontak), 'number', 'Bujur', false, true],

            // ── Tab 3: Media Sosial ───────────────────────────────────
            ['social_instagram', 'https://instagram.com/iksanknalpot', $G(GrupPengaturan::Sosial), 'url', 'Instagram', false, true],
            ['social_instagram_visible', '1', $G(GrupPengaturan::Sosial), 'boolean', 'Tampilkan Instagram', false, true],
            ['social_facebook', null, $G(GrupPengaturan::Sosial), 'url', 'Facebook', false, true],
            ['social_facebook_visible', '0', $G(GrupPengaturan::Sosial), 'boolean', 'Tampilkan Facebook', false, true],
            ['social_tiktok', 'https://tiktok.com/@iksanknalpot', $G(GrupPengaturan::Sosial), 'url', 'TikTok', false, true],
            ['social_tiktok_visible', '1', $G(GrupPengaturan::Sosial), 'boolean', 'Tampilkan TikTok', false, true],
            ['social_youtube', null, $G(GrupPengaturan::Sosial), 'url', 'YouTube', false, true],
            ['social_youtube_visible', '0', $G(GrupPengaturan::Sosial), 'boolean', 'Tampilkan YouTube', false, true],
            ['social_shopee', 'https://shopee.co.id/iksanknalpot', $G(GrupPengaturan::Sosial), 'url', 'Shopee', false, true],
            ['social_shopee_visible', '1', $G(GrupPengaturan::Sosial), 'boolean', 'Tampilkan Shopee', false, true],
            ['social_tokopedia', null, $G(GrupPengaturan::Sosial), 'url', 'Tokopedia', false, true],
            ['social_tokopedia_visible', '0', $G(GrupPengaturan::Sosial), 'boolean', 'Tampilkan Tokopedia', false, true],

            // ── Tab 4: Pembayaran ─────────────────────────────────────
            ['payment_mode', 'manual', $G(GrupPengaturan::Pembayaran), 'select', 'Mode pembayaran', false, false, ['paypal', 'manual', 'both']],
            ['paypal_client_id', 'SANDBOX-CLIENT-ID-CONTOH', $G(GrupPengaturan::Pembayaran), 'text', 'PayPal Client ID', true, false],
            ['paypal_client_secret', 'SANDBOX-SECRET-CONTOH', $G(GrupPengaturan::Pembayaran), 'text', 'PayPal Client Secret', true, false],
            ['paypal_mode', 'sandbox', $G(GrupPengaturan::Pembayaran), 'select', 'Mode PayPal', false, false, ['sandbox', 'live']],
            ['paypal_currency', 'IDR', $G(GrupPengaturan::Pembayaran), 'select', 'Mata uang', false, false, ['IDR', 'USD']],
            ['manual_bank_accounts', json_encode([
                ['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Iksan Knalpot'],
                ['bank' => 'Mandiri', 'number' => '0987654321', 'holder' => 'Iksan Knalpot'],
            ]), $G(GrupPengaturan::Pembayaran), 'json', 'Rekening transfer manual', true, false],
            ['tax_percent', '0', $G(GrupPengaturan::Pembayaran), 'number', 'Pajak (%)', false, false],
            ['shipping_flat_rate', '0', $G(GrupPengaturan::Pembayaran), 'number', 'Ongkir datar', false, false],

            // ── Tab 5: Lanjutan ───────────────────────────────────────
            ['maintenance_mode', '0', $G(GrupPengaturan::Lanjutan), 'boolean', 'Mode pemeliharaan', false, true],
            ['maintenance_message', 'Situs sedang dalam pemeliharaan singkat.', $G(GrupPengaturan::Lanjutan), 'textarea', 'Pesan pemeliharaan', false, true],
            ['analytics_id', null, $G(GrupPengaturan::Lanjutan), 'text', 'ID Analytics', false, true],
            ['meta_pixel_id', null, $G(GrupPengaturan::Lanjutan), 'text', 'ID Meta Pixel', false, true],
            ['order_prefix', 'IK', $G(GrupPengaturan::Lanjutan), 'text', 'Prefiks nomor pesanan', false, true],
            ['low_stock_threshold', '3', $G(GrupPengaturan::Lanjutan), 'number', 'Ambang stok menipis', false, true],
            ['enable_pwa', '0', $G(GrupPengaturan::Lanjutan), 'boolean', 'Aktifkan PWA', false, true],
            ['currency_symbol', 'Rp', $G(GrupPengaturan::Lanjutan), 'text', 'Simbol mata uang', false, true],
            ['date_format', 'd M Y', $G(GrupPengaturan::Lanjutan), 'text', 'Format tanggal', false, true],
        ];

        foreach ($baris as $b) {
            [$key, $value, $group, $type, $label, $encrypted, $public] = $b;
            $options = $b[7] ?? null;

            // is_encrypted HARUS diisi sebelum value.
            //
            // Cast NilaiPengaturan memutuskan enkripsi dengan membaca
            // $attributes['is_encrypted']. Laravel mengisi atribut sesuai urutan
            // larik, jadi bila `value` lebih dulu, cast belum melihat tanda
            // enkripsi dan nilainya tersimpan sebagai teks biasa — sementara
            // `is_encrypted` tetap tercatat true. Akibatnya baris itu tidak
            // dapat dibaca lagi: cast mencoba mendekripsi teks biasa, gagal,
            // lalu mengembalikan null. Rekening transfer tampak kosong padahal
            // isinya ada di basis data.
            $setting = Setting::query()->firstOrNew(['key' => $key]);

            $setting->fill([
                'group_name' => $group,
                'type' => $type,
                'label' => $label,
                'options_json' => $options,
                'is_encrypted' => $encrypted,
                'is_public' => $public,
            ]);

            $setting->value = $value;
            $setting->save();
        }

        Pengaturan::lupakan();

        $this->command?->info('SettingSeeder: '.count($baris).' kunci pengaturan.');
    }
}
