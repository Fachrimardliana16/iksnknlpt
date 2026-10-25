<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use Illuminate\Database\Seeder;

class ContactMessageSeeder extends Seeder
{
    private const PESAN = [
        ['Rizky Ananda', 'rizky@example.test', '628130000001', 'Tanya stok NMAX', 'Untuk NMAX V2 2021, full system titanium stoknya masih ada?', false],
        ['Dewi Lestari', 'dewi@example.test', '628130000002', 'Bunyi knalpot', 'Apakah slip-on Vario 160 lebih berisik dari standar? Saya butuh yang tetap halus.', false],
        ['Hendra Wijaya', 'hendra@example.test', null, 'Pemasangan di luar kota', 'Kalau saya kirim motor ke Yogyakarta, pemasangan butuh berapa hari?', false],
        ['Maya Puspita', 'maya@example.test', '628130000004', 'Garansi', 'Berapa lama garansi untuk header stainless?', true],
        ['Fajar Nugroho', 'fajar@example.test', '628130000005', 'Kirim ke Surabaya', 'Ongkir ke Surabaya untuk full system berapa? Beratnya sekitar 4 kg.', true],
        ['Linda Kusuma', 'linda@example.test', null, 'Reseller', 'Saya punya bengkel di Semarang. Ada harga khusus untuk reseller?', true],
        ['Bayu Setiawan', 'bayu@example.test', '628130000007', 'Custom diameter', 'Bisa dibuatkan pipa 45 mm? Rangka saya sudah ubah bagian belakang.', true],
        ['Nur Aini', 'nur@example.test', '628130000008', 'Pembayaran', 'Apakah bisa transfer bank selain BCA?', true],
    ];

    public function run(): void
    {
        foreach (self::PESAN as $i => [$nama, $email, $telepon, $subjek, $pesan, $sudahDibaca]) {
            // Kunci idempotensi memakai email, bukan subjek: subjek ber-cast
            // encrypted sehingga nilainya berbeda setiap kali ditulis, dan
            // updateOrCreate dengan kunci terenkripsi akan membuat baris baru
            // di setiap seeding. Email punya indeks buta (email_bidx) yang
            // stabil — sama untuk nilai yang sama.
            ContactMessage::query()->updateOrCreate(
                ['email_bidx' => \App\Support\BlindIndex::buat($email, 'email_bidx')],
                [
                    'name' => $nama,
                    'email' => $email,
                    'phone' => $telepon,
                    'subject' => $subjek,
                    'message' => $pesan,
                    'ip_hash' => hash('sha256', 'seeder-'.$email),
                    'user_agent' => 'Seeder/1.0',
                    'is_read' => $sudahDibaca,
                    'replied_at' => $sudahDibaca ? now()->subDays(8 - $i) : null,
                ],
            );
        }

        $this->command?->info(sprintf(
            'ContactMessageSeeder: %d pesan, %d belum dibaca.',
            ContactMessage::query()->count(),
            ContactMessage::query()->where('is_read', false)->count(),
        ));
    }
}
