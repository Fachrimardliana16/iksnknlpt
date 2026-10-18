<?php

namespace App\Livewire\Admin;

use App\Enums\TipeKupon;
use App\Models\Coupon;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kupon diskon.
 *
 * Tiga jenis: persentase, potongan nominal, dan gratis ongkir. Perbedaan
 * pentingnya bukan pada tampilan melainkan pada batas potongan: persentase
 * tanpa batas atas dapat memotong seluruh nilai belanja, dan gratis ongkir
 * dihitung dari ongkir, bukan dari subtotal.
 *
 * Kuota dan masa berlaku diperiksa di server melalui Coupon::alasanTidakSah().
 * Formulir admin memakai pemeriksaan yang sama dengan checkout, sehingga kupon
 * yang tampak sah di sini pasti dapat dipakai pembeli.
 */
#[Layout('layouts.admin')]
class Kupon extends Component
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    #[Url(as: 'keadaan', history: true)]
    public string $keadaan = 'semua';

    public bool $formTerbuka = false;

    public ?int $suntingId = null;

    public string $code = '';

    public string $type = 'percent';

    public string $value = '';

    public string $min_spend = '';

    public string $max_discount = '';

    public string $quota = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public bool $is_active = true;

    public ?string $pesan = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function aturKeadaan(string $nilai): void
    {
        $this->keadaan = in_array($nilai, ['semua', 'aktif', 'berjalan', 'kedaluwarsa', 'habis'], true)
            ? $nilai
            : 'semua';

        $this->resetPage();
    }

    public function bukaForm(): void
    {
        $this->bersihkan();
        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->bersihkan();
        $this->formTerbuka = false;
    }

    public function sunting(int $id): void
    {
        $kupon = Coupon::query()->findOrFail($id);

        $this->suntingId = $kupon->id;
        $this->code = (string) $kupon->code;
        $this->type = $kupon->type->value;
        $this->value = (string) $kupon->value;
        $this->min_spend = $kupon->min_spend === null ? '' : (string) $kupon->min_spend;
        $this->max_discount = $kupon->max_discount === null ? '' : (string) $kupon->max_discount;
        $this->quota = $kupon->quota === null ? '' : (string) $kupon->quota;
        $this->starts_at = $kupon->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = $kupon->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->is_active = (bool) $kupon->is_active;
        $this->formTerbuka = true;
        $this->pesan = null;

        $this->resetValidation();
    }

    public function simpan(): void
    {
        // Kode dinormalkan ke huruf besar SEBELUM diperiksa, bukan sesudah.
        //
        // Model sudah menormalkannya saat menyimpan, sehingga "uji10" tersimpan
        // sebagai "UJI10". Kalau validasi memeriksa bentuk aslinya, "uji10"
        // tidak dianggap kembar dengan "UJI10" yang sudah ada — pemeriksaan
        // lolos, lalu penyimpanan gagal dengan galat batasan basis data yang
        // tidak berarti apa pun bagi pengguna.
        $this->code = mb_strtoupper(trim($this->code));

        $jenis = TipeKupon::tryFrom($this->type);

        $aturan = [
            'code' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/',
                Rule::unique('coupons', 'code')->ignore($this->suntingId),
            ],
            'type' => ['required', Rule::in(TipeKupon::values())],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'min_spend' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];

        $pesan = [
            'code.required' => 'Kode kupon wajib diisi.',
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'code.unique' => 'Kode ini sudah dipakai kupon lain.',
            'code.min' => 'Kode minimal tiga karakter.',
            'value.required' => 'Nilai potongan wajib diisi.',
            'value.numeric' => 'Nilai potongan harus berupa angka.',
            'ends_at.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ];

        $bersih = $this->validate($aturan, $pesan);

        // Persentase di atas 100 bukan potongan, melainkan pemberian uang.
        // Batasnya ditegakkan di sini supaya tidak ada kupon yang membuat
        // total pesanan menjadi negatif.
        if ($jenis === TipeKupon::Persen && (float) $bersih['value'] > 100) {
            $this->addError('value', 'Potongan persentase tidak boleh melebihi 100%.');

            return;
        }

        // Potongan nominal tanpa nilai jelas tidak memotong apa pun.
        if ($jenis === TipeKupon::Nominal && (float) $bersih['value'] <= 0) {
            $this->addError('value', 'Potongan nominal harus lebih dari nol.');

            return;
        }

        $data = [
            'code' => mb_strtoupper($bersih['code']),
            'type' => $jenis,
            'value' => $bersih['value'],
            // min_spend memakai 0 sebagai "tanpa minimum", bukan null: kolomnya
            // NOT NULL dengan nilai bawaan 0, dan Coupon::alasanTidakSah()
            // memeriksanya sebagai angka (min_spend > 0). Mengirim null akan
            // ditolak basis data, dan mengirimnya sebagai angka nol adalah cara
            // yang sudah dipahami seluruh kode pembacanya.
            //
            // max_discount sebaliknya memakai null sebagai "tanpa batas", karena
            // pembacanya membandingkan dengan null secara tegas.
            'min_spend' => $bersih['min_spend'] === null || $bersih['min_spend'] === '' ? 0 : $bersih['min_spend'],
            'max_discount' => $bersih['max_discount'] === null || $bersih['max_discount'] === '' ? null : $bersih['max_discount'],
            'quota' => $bersih['quota'] === null || $bersih['quota'] === '' ? null : (int) $bersih['quota'],
            'starts_at' => $bersih['starts_at'] ?: null,
            'ends_at' => $bersih['ends_at'] ?: null,
            'is_active' => $bersih['is_active'],
        ];

        if ($this->suntingId === null) {
            $kupon = Coupon::query()->create($data + ['used_count' => 0]);

            CatatanAktivitas::catat('kupon.dibuat', ['code' => $kupon->code], auth()->id(), $kupon);

            $this->pesan = 'Kupon "'.$kupon->code.'" ditambahkan.';
        } else {
            $kupon = Coupon::query()->findOrFail($this->suntingId);

            // Kuota tidak boleh diturunkan di bawah jumlah pemakaian. Kalau
            // diizinkan, kupon tampak tersedia padahal sudah melewati batasnya,
            // dan pemeriksaan di checkout akan menolaknya tanpa sebab jelas.
            if (array_key_exists('quota', $data) && $data['quota'] !== null && $data['quota'] < $kupon->used_count) {
                $this->addError(
                    'quota',
                    'Kuota tidak boleh di bawah '.$kupon->used_count.' karena sudah dipakai sebanyak itu.',
                );

                return;
            }

            $kupon->update($data);

            CatatanAktivitas::catat('kupon.diubah', ['code' => $kupon->code], auth()->id(), $kupon);

            $this->pesan = 'Kupon "'.$kupon->code.'" diperbarui.';
        }

        $this->bersihkan();
        $this->formTerbuka = false;
    }

    public function ubahAktif(int $id): void
    {
        $kupon = Coupon::query()->findOrFail($id);
        $kupon->update(['is_active' => ! $kupon->is_active]);

        CatatanAktivitas::catat('kupon.status', [
            'code' => $kupon->code,
            'is_active' => $kupon->is_active,
        ], auth()->id(), $kupon);

        $this->pesan = $kupon->is_active
            ? 'Kupon "'.$kupon->code.'" diaktifkan.'
            : 'Kupon "'.$kupon->code.'" dinonaktifkan.';
    }

    public function hapus(int $id): void
    {
        $kupon = Coupon::query()->withCount('orders')->findOrFail($id);

        // Kupon yang pernah dipakai menempel pada pesanan lama. Menghapusnya
        // membuat baris pesanan kehilangan keterangan potongan yang diterima
        // pembeli, dan itu bagian dari catatan uang.
        if ($kupon->orders_count > 0) {
            $this->addError(
                'umum',
                'Kupon ini sudah dipakai '.$kupon->orders_count.' pesanan. Nonaktifkan saja, jangan dihapus.',
            );

            return;
        }

        CatatanAktivitas::catat('kupon.dihapus', ['code' => $kupon->code], auth()->id(), $kupon);

        $kupon->delete();

        $this->pesan = 'Kupon "'.$kupon->code.'" dihapus.';
    }

    /** Pratinjau potongan untuk belanja contoh, membantu memeriksa batasnya. */
    public function pratinjauPotongan(float $belanja = 500000, float $ongkir = 25000): array
    {
        $jenis = TipeKupon::tryFrom($this->type) ?? TipeKupon::Persen;

        $potongan = $jenis->hitung(
            $belanja,
            (float) ($this->value ?: 0),
            $this->max_discount === '' ? null : (float) $this->max_discount,
            $ongkir,
        );

        return ['potongan' => $potongan, 'belanja' => $belanja, 'ongkir' => $ongkir];
    }

    private function bersihkan(): void
    {
        $this->suntingId = null;
        $this->code = '';
        $this->type = 'percent';
        $this->value = '';
        $this->min_spend = '';
        $this->max_discount = '';
        $this->quota = '';
        $this->starts_at = '';
        $this->ends_at = '';
        $this->is_active = true;

        $this->resetValidation();
    }

    public function render(): View
    {
        $daftar = Coupon::query()
            ->when($this->cari !== '', function ($q): void {
                $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtoupper($this->cari)).'%';
                $q->where('code', 'like', $pola);
            })
            ->when($this->keadaan === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($this->keadaan === 'berjalan', fn ($q) => $q->berlaku())
            ->when($this->keadaan === 'kedaluwarsa', fn ($q) => $q->where('ends_at', '<', now()))
            ->when($this->keadaan === 'habis', fn ($q) => $q->whereNotNull('quota')->whereColumn('used_count', '>=', 'quota'))
            ->withCount('orders')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.kupon', [
            'daftar' => $daftar,
            'jumlah' => [
                'semua' => Coupon::query()->count(),
                'berjalan' => Coupon::query()->berlaku()->count(),
                'habis' => Coupon::query()->whereNotNull('quota')->whereColumn('used_count', '>=', 'quota')->count(),
            ],
            'pratinjau' => $this->pratinjauPotongan(),
        ])->layout('layouts.admin', ['judul' => 'Kupon', 'bagian' => 'Penjualan']);
    }
}
