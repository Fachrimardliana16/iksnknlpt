@include('errors.layout', [
    'kode' => 500,
    'headline' => __('site.errors.500_title'),
    'pesan' => 'Kami sudah mencatat kejadian ini. Coba muat ulang sebentar lagi.',
])
