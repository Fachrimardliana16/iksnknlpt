@include('errors.layout', [
    'kode' => 404,
    'headline' => __('site.errors.404_title'),
    'pesan' => 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.',
])
