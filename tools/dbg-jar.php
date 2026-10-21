<?php

$jar = tempnam(sys_get_temp_dir(), 'a-');
$minta = static function (string $url, array $o = []) use ($jar): array {
    $ch = curl_init($url);
    $s = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEJAR=>$jar, CURLOPT_COOKIEFILE=>$jar, CURLOPT_FOLLOWLOCATION=>$o['ikut'] ?? true, CURLOPT_TIMEOUT=>60, CURLOPT_HEADER=>true, CURLOPT_HTTPHEADER=>$o['header'] ?? []];
    if (array_key_exists('pos', $o)) { $s[CURLOPT_POST]=true; $s[CURLOPT_POSTFIELDS]=$o['pos']; }
    curl_setopt_array($ch, $s);
    $raw = curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    return ['kode'=>$k, 'kepala'=>substr((string)$raw,0,$hs), 'isi'=>substr((string)$raw,$hs)];
};
$baca = static function (string $j): int { return count(array_filter(file($j) ?: [], fn($b) => !str_starts_with($b,'#') && trim($b)!=='')); };

$h = $minta('http://127.0.0.1:8100/admin/login');
echo 'jar awal: '.$baca($jar)." cookie\n";
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m); $snap = html_entity_decode($m[1][0], ENT_QUOTES);
preg_match('/name="csrf-token" content="([^"]+)"/', $h['isi'], $c); $csrf = $c[1];
preg_match('/XSRF-TOKEN\s+([^;\s]+)/', $h['kepala'], $x); $xsrf = $x[1] ?? '';
$r = $minta('http://127.0.0.1:8100/livewire/update', ['pos'=>json_encode(['components'=>[['snapshot'=>$snap,'updates'=>['email'=>'admin@iksanknalpot.test','password'=>'password'],'calls'=>[['path'=>'','method'=>'masuk','params'=>[]]]]]], JSON_UNESCAPED_SLASHES), 'ikut'=>false, 'header'=>['Content-Type: application/json','X-Livewire: 1','Accept: application/json','X-CSRF-TOKEN: '.$csrf,'X-XSRF-TOKEN: '.$xsrf]]);
echo 'login: '.$r['kode'].', jar: '.$baca($jar)." cookie\n";

foreach (['/admin', '/admin/pesanan', '/admin/pengaturan'] as $u) {
    $d = $minta('http://127.0.0.1:8100'.$u);
    echo $u.': '.$d['kode'].' '.strlen($d['isi']).' bytes, jar: '.$baca($jar)."\n";
}
