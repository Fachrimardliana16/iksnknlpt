@include('errors.layout', [
    'kode' => 403,
    'headline' => __('site.errors.403_title'),
    'pesan' => 'Akun Anda tidak memiliki akses ke halaman ini.',
])
