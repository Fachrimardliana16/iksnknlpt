<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas
    |--------------------------------------------------------------------------
    */

    'order_prefix' => env('IKK_ORDER_PREFIX', 'IK'),

    'low_stock_threshold' => (int) env('IKK_LOW_STOCK_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Notifikasi pesanan
    |--------------------------------------------------------------------------
    | Pengiriman lewat surel dan WhatsApp berjalan sebagai pekerjaan antrean.
    | Sakelar di bawah berguna saat pengujian dan saat pengembangan, ketika
    | mengirim pesan sungguhan ke pembeli tidak diinginkan.
    */

    'notifikasi' => [
        'kirim_saat_dibuat' => (bool) env('IKK_NOTIF_DIBUAT', true),
        'kirim_saat_dibayar' => (bool) env('IKK_NOTIF_DIBAYAR', true),
        'kirim_saat_dikirim' => (bool) env('IKK_NOTIF_DIKIRIM', true),

        // Push perangkat (web push) saat notifikasi panel BERTAMBAH.
        // Matikan kalau admin lebih memilih surel saja.
        'push_perangkat' => (bool) env('IKK_NOTIF_PUSH', true),
        // Surel cadangan saat push tidak sampai (belum ada langganan, izin
        // belum diberikan, atau layanan push sedang bermasalah).
        'push_surel_cadangan' => (bool) env('IKK_NOTIF_PUSH_EMAIL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hero
    |--------------------------------------------------------------------------
    | Batas yang ditegakkan di server, bukan hanya di antarmuka (checklist
    | F4-11). Berkas dapat dikirim langsung ke titik akhir unggah tanpa
    | melewati formulir, jadi batas di peramban saja tidak cukup.
    */

    'hero' => [
        'max_slides' => (int) env('IKK_HERO_MAX_SLIDES', 5),
        'video_max_kb' => (int) env('IKK_HERO_VIDEO_MAX_KB', 8192),
        'video_max_detik' => (int) env('IKK_HERO_VIDEO_MAX_SECONDS', 15),
        'min_slide_aktif' => 1,
        'gambar_lebar_maks' => 2560,
    ],

    /*
    |--------------------------------------------------------------------------
    | Halaman depan
    |--------------------------------------------------------------------------
    | Diukur pada viewport 390x844: katalog delapan kartu dalam dua kolom
    | menghabiskan 1,96 layar, dan halaman depan seluruhnya mencapai 6 layar.
    | Angka di bawah memangkasnya tanpa menghilangkan isi — sisanya tetap
    | terjangkau lewat tombol "lihat semua".
    */

    'home' => [
        // Kartu unggulan yang tampil sebelum tombol lihat semua di ponsel.
        'featured_mobile' => (int) env('IKK_HOME_FEATURED_MOBILE', 4),
        // Kategori yang tampil di footer, maksimum.
        'footer_categories' => (int) env('IKK_HOME_FOOTER_CATEGORIES', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Blind index
    |--------------------------------------------------------------------------
    | Kolom ber-cast "encrypted" tidak dapat di-LIKE maupun di-WHERE. Setiap
    | kolom yang perlu dicari menyimpan HMAC-SHA256 di kolom *_bidx.
    */

    'blind_index_key' => env('BLIND_INDEX_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Unggahan
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'image_max_kb' => (int) env('UPLOAD_IMAGE_MAX_KB', 5120),
        'video_max_kb' => (int) env('UPLOAD_VIDEO_MAX_KB', 8192),
        'max_files_per_product' => (int) env('UPLOAD_MAX_FILES_PER_PRODUCT', 200),
        'max_total_mb_per_product' => (int) env('UPLOAD_MAX_TOTAL_MB_PER_PRODUCT', 50),
        'processing_memory_mb' => (int) env('UPLOAD_PROCESSING_MEMORY_MB', 256),
        'processing_timeout' => (int) env('UPLOAD_PROCESSING_TIMEOUT', 60),
        'derivatives' => [
            'thumb' => [320, 240],
            'card' => [640, 480],
            'full' => [1600, 1200],
            'og' => [1200, 630],
        ],
        'webp_quality' => 82,
        'webp_method' => 6,
        'max_dimension' => 6000,
        'min_dimension' => 200,
        'max_aspect_ratio' => 20,
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/avif',
        ],
        'allowed_video_mimes' => [
            'video/mp4',
        ],
        'quarantine_days' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas laju
    |--------------------------------------------------------------------------
    | PRD Bagian 11.1. Nilai dalam format [percobaan, menit].
    */

    'rate_limits' => [
        'admin_login' => [5, 1],
        'admin_login_lockout_minutes' => 15,
        'customer_login' => [5, 1],
        'customer_login_hourly' => [20, 60],
        'register' => [5, 60],
        'password_reset' => [3, 60],
        'contact' => [3, 60],
        'cart_add' => [60, 1],
        'checkout' => [5, 10],
        'track_order' => [10, 1],
        'search' => [30, 1],
        'admin_upload' => [60, 60],
        'payment_proof' => [3, 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Brute force
    |--------------------------------------------------------------------------
    */

    'brute_force' => [
        'failures_before_ip_block' => 10,
        'failure_window_minutes' => 30,
        'ip_block_hours' => 1,
        'backoff_seconds' => [1, 2, 4, 8],
        'min_form_seconds' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxy
    |--------------------------------------------------------------------------
    | Hanya untuk terowongan pengembangan lokal. Di produksi, ganti dengan
    | rentang CIDR proxy yang sebenarnya. Jangan pernah pakai '*' di produksi.
    */

    'trusted_proxies' => env('IKK_TRUSTED_PROXIES'),

];
