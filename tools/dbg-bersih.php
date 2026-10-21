<?php
$h = @file_get_contents('http://127.0.0.1:8100/admin/pesanan?status=pending');
if ($h === false) { echo "gagal\n"; exit(1); }
echo "panjang: ".strlen($h)."\n";
echo "Masuk panel? ".(str_contains($h, 'Masuk panel') ? 'YA' : 'tidak')."\n";
// cari status pada snapshot pesanan-daftar
$n = preg_match_all('/wire:snapshot="([^"]+)"/', $h, $all);
echo "snapshot: $n\n";
foreach ($all[1] as $i => $raw) {
    $s = html_entity_decode($raw, ENT_QUOTES);
    $j = json_decode($s, true);
    $nama = is_array($j) ? ($j['memo']['name'] ?? '?') : 'bukan-json';
    echo "  [$i] $nama\n";
    if (is_array($j) && ($j['memo']['name'] ?? '') === 'admin.pesanan-daftar') {
        echo "       status=".($j['data']['status'] ?? '?')." bayar=".($j['data']['bayar'] ?? '?')."\n";
    }
}
