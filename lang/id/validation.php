<?php

/*
|--------------------------------------------------------------------------
| Pesan validasi bahasa Indonesia
|--------------------------------------------------------------------------
|
| Berkas ini sebelumnya tidak ada. Akibatnya Laravel jatuh ke locale
| cadangan (en) dan pengguna melihat "The email field is required." di
| halaman yang sepenuhnya berbahasa Indonesia — termasuk panel admin.
|
| Semua pesan memakai placeholder bawaan Laravel (:attribute, :min, :max)
| supaya Laravel-lah yang menggantinya, bukan teks yang dirakit sendiri.
|
*/

return [

    'accepted' => 'Kolom :attribute harus disetujui.',
    'accepted_if' => 'Kolom :attribute harus disetujui bila :other bernilai :value.',
    'active_url' => 'Kolom :attribute bukan tautan yang sah.',
    'after' => 'Kolom :attribute harus tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus tanggal :date atau setelahnya.',
    'alpha' => 'Kolom :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Kolom :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Kolom :attribute hanya boleh berisi huruf dan angka.',
    'any_of' => 'Kolom :attribute tidak sesuai aturan mana pun.',
    'array' => 'Kolom :attribute harus berupa daftar.',
    'ascii' => 'Kolom :attribute hanya boleh berisi karakter ASCII.',
    'before' => 'Kolom :attribute harus tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus tanggal :date atau sebelumnya.',
    'between' => [
        'array' => 'Kolom :attribute harus berisi :min sampai :max butir.',
        'file' => 'Berkas :attribute harus :min sampai :max kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai :min sampai :max.',
        'string' => 'Kolom :attribute harus :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'can' => 'Kolom :attribute memuat nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak sama.',
    'contains' => 'Kolom :attribute belum memuat nilai yang diperlukan.',
    'current_password' => 'Kata sandi yang dimasukkan salah.',
    'date' => 'Kolom :attribute harus berupa tanggal yang sah.',
    'date_equals' => 'Kolom :attribute harus tanggal sama dengan :date.',
    'date_format' => 'Kolom :attribute tidak mengikuti format :format.',
    'decimal' => 'Kolom :attribute harus :decimal angka di belakang koma.',
    'declined' => 'Kolom :attribute harus ditolak.',
    'declined_if' => 'Kolom :attribute harus ditolak bila :other bernilai :value.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'digits' => 'Kolom :attribute harus terdiri dari :digits angka.',
    'digits_between' => 'Kolom :attribute harus terdiri dari :min sampai :max angka.',
    'dimensions' => 'Ukuran gambar :attribute tidak sesuai.',
    'distinct' => 'Kolom :attribute memiliki nilai yang kembar.',
    'doesnt_end_with' => 'Kolom :attribute tidak boleh berakhir dengan: :values.',
    'doesnt_start_with' => 'Kolom :attribute tidak boleh diawali dengan: :values.',
    'doesnt_contain' => 'Kolom :attribute tidak boleh memuat salah satu dari: :values.',
    'email' => 'Kolom :attribute harus berupa alamat email yang sah.',
    'encoding' => 'Kolom :attribute harus memakai pengkodean :encoding.',
    'ends_with' => 'Kolom :attribute harus berakhir dengan: :values.',
    'enum' => 'Pilihan :attribute tidak tersedia.',
    'exists' => 'Pilihan :attribute tidak ditemukan.',
    'extensions' => 'Berkas :attribute harus berakhiran: :values.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'filled' => 'Kolom :attribute harus memiliki nilai.',
    'gt' => [
        'array' => 'Kolom :attribute harus lebih dari :value butir.',
        'file' => 'Berkas :attribute harus lebih besar dari :value kilobyte.',
        'numeric' => 'Kolom :attribute harus lebih besar dari :value.',
        'string' => 'Kolom :attribute harus lebih panjang dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Kolom :attribute harus :value butir atau lebih.',
        'file' => 'Berkas :attribute harus :value kilobyte atau lebih.',
        'numeric' => 'Kolom :attribute harus bernilai :value atau lebih besar.',
        'string' => 'Kolom :attribute harus :value karakter atau lebih.',
    ],
    'hex_color' => 'Kolom :attribute harus berupa kode warna heksadesimal.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => 'Pilihan :attribute tidak tersedia.',
    'in_array' => 'Kolom :attribute harus ada di dalam :other.',
    'in_array_keys' => 'Kolom :attribute harus memuat salah satu kunci berikut: :values.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'ip' => 'Kolom :attribute harus berupa alamat IP yang sah.',
    'ipv4' => 'Kolom :attribute harus berupa alamat IPv4 yang sah.',
    'ipv6' => 'Kolom :attribute harus berupa alamat IPv6 yang sah.',
    'json' => 'Kolom :attribute harus berupa teks JSON yang sah.',
    'lowercase' => 'Kolom :attribute harus berupa huruf kecil.',
    'list' => 'Kolom :attribute harus berupa daftar bernomor.',
    'lt' => [
        'array' => 'Kolom :attribute harus kurang dari :value butir.',
        'file' => 'Berkas :attribute harus kurang dari :value kilobyte.',
        'numeric' => 'Kolom :attribute harus kurang dari :value.',
        'string' => 'Kolom :attribute harus lebih pendek dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Kolom :attribute harus :value butir atau kurang.',
        'file' => 'Berkas :attribute harus :value kilobyte atau kurang.',
        'numeric' => 'Kolom :attribute harus bernilai :value atau kurang.',
        'string' => 'Kolom :attribute harus :value karakter atau kurang.',
    ],
    'mac_address' => 'Kolom :attribute harus berupa alamat MAC yang sah.',
    'max' => [
        'array' => 'Kolom :attribute maksimal :max butir.',
        'file' => 'Berkas :attribute maksimal :max kilobyte.',
        'numeric' => 'Kolom :attribute maksimal bernilai :max.',
        'string' => 'Kolom :attribute maksimal :max karakter.',
    ],
    'max_digits' => 'Kolom :attribute maksimal :max angka.',
    'mimes' => 'Berkas :attribute harus berjenis: :values.',
    'mimetypes' => 'Berkas :attribute harus berjenis: :values.',
    'min' => [
        'array' => 'Kolom :attribute minimal :min butir.',
        'file' => 'Berkas :attribute minimal :min kilobyte.',
        'numeric' => 'Kolom :attribute minimal bernilai :min.',
        'string' => 'Kolom :attribute minimal :min karakter.',
    ],
    'min_digits' => 'Kolom :attribute minimal :min angka.',
    'missing' => 'Kolom :attribute harus kosong.',
    'missing_if' => 'Kolom :attribute harus kosong bila :other bernilai :value.',
    'missing_unless' => 'Kolom :attribute harus kosong kecuali :other bernilai :value.',
    'missing_with' => 'Kolom :attribute harus kosong bila :other ada.',
    'missing_with_all' => 'Kolom :attribute harus kosong bila :values ada.',
    'multiple_of' => 'Kolom :attribute harus kelipatan :value.',
    'not_in' => 'Pilihan :attribute tidak diizinkan.',
    'not_regex' => 'Format kolom :attribute tidak sah.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Kata sandi harus memuat minimal satu huruf.',
        'mixed' => 'Kata sandi harus memakai huruf besar dan huruf kecil.',
        'numbers' => 'Kata sandi harus memuat minimal satu angka.',
        'symbols' => 'Kata sandi harus memuat minimal satu simbol.',
        'uncompromised' => 'Kata sandi ini pernah muncul dalam kebocoran data. Pilih kata sandi lain.',
    ],
    'present' => 'Kolom :attribute harus ada.',
    'present_if' => 'Kolom :attribute harus ada bila :other bernilai :value.',
    'present_unless' => 'Kolom :attribute harus ada kecuali :other bernilai :value.',
    'present_with' => 'Kolom :attribute harus ada bila :other ada.',
    'present_with_all' => 'Kolom :attribute harus ada bila :values ada.',
    'prohibited' => 'Kolom :attribute tidak boleh diisi.',
    'prohibited_if' => 'Kolom :attribute tidak boleh diisi bila :other bernilai :value.',
    'prohibited_if_accepted' => 'Kolom :attribute tidak boleh diisi bila :other disetujui.',
    'prohibited_if_declined' => 'Kolom :attribute tidak boleh diisi bila :other ditolak.',
    'prohibited_unless' => 'Kolom :attribute tidak boleh diisi kecuali :other ada di :values.',
    'prohibits' => 'Kolom :attribute melarang :other diisi.',
    'regex' => 'Format kolom :attribute tidak sah.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_array_keys' => 'Kolom :attribute harus memuat: :values.',
    'required_if' => 'Kolom :attribute wajib diisi bila :other bernilai :value.',
    'required_if_accepted' => 'Kolom :attribute wajib diisi bila :other disetujui.',
    'required_if_declined' => 'Kolom :attribute wajib diisi bila :other ditolak.',
    'required_unless' => 'Kolom :attribute wajib diisi kecuali :other ada di :values.',
    'required_with' => 'Kolom :attribute wajib diisi bila :other ada.',
    'required_with_all' => 'Kolom :attribute wajib diisi bila :values ada.',
    'required_without' => 'Kolom :attribute wajib diisi bila :other tidak ada.',
    'required_without_all' => 'Kolom :attribute wajib diisi bila :values tidak ada.',
    'same' => 'Kolom :attribute dan :other harus sama.',
    'size' => [
        'array' => 'Kolom :attribute harus berisi :size butir.',
        'file' => 'Berkas :attribute harus :size kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai :size.',
        'string' => 'Kolom :attribute harus :size karakter.',
    ],
    'starts_with' => 'Kolom :attribute harus diawali dengan: :values.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'timezone' => 'Kolom :attribute harus berupa zona waktu yang sah.',
    'unique' => ':attribute sudah dipakai. Pilih nilai lain.',
    'uploaded' => 'Berkas :attribute gagal diunggah.',
    'uppercase' => 'Kolom :attribute harus berupa huruf besar.',
    'url' => 'Kolom :attribute harus berupa tautan yang sah.',
    'ulid' => 'Kolom :attribute harus berupa ULID yang sah.',
    'uuid' => 'Kolom :attribute harus berupa UUID yang sah.',

    /*
    |--------------------------------------------------------------------------
    | Pesan khusus
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'required' => 'Alamat email wajib diisi.',
            'email' => 'Alamat email tidak sah.',
        ],
        'password' => [
            'required' => 'Kata sandi wajib diisi.',
            'min' => 'Kata sandi minimal :min karakter.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama kolom
    |--------------------------------------------------------------------------
    |
    | Tanpa berkas ini, nama kolom yang tampil adalah nama teknis dari basis
    | data ("starts_at", "focal_point_x"). Diterjemahkan ke istilah yang
    | dikenal pengelola toko.
    |
    */

    'attributes' => [
        'email' => 'Email',
        'password' => 'Kata sandi',
        'otp' => 'Kode verifikasi',
        'nama' => 'Nama',
        'name' => 'Nama',
        'judul' => 'Judul',
        'deskripsi' => 'Deskripsi',
        'harga' => 'Harga',
        'price' => 'Harga',
        'stok' => 'Stok',
        'starts_at' => 'Mulai tayang',
        'ends_at' => 'Berakhir',
        'focal_point_x' => 'Titik fokus X',
        'focal_point_y' => 'Titik fokus Y',
        'berkasGambar' => 'Gambar desktop',
        'berkasPonsel' => 'Gambar ponsel',
        'berkasVideo' => 'Berkas video',
    ],

];
