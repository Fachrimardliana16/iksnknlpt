<?php
// Login; PASTIKAN redirect diikuti ke dasbor baru. Lalu baru /admin/pesanan?status=pending
$basis = 'http://127.0.0.1:8100';
$kuki = [];
$minta = static function (string $url, array $o = []) use (&$kuki): array {
    $ch = curl_init($url);
    $s = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_TIMEOUT=>60, CURLOPT_HEADER=>true, CURLOPT_HTTPHEADER=>$o['header'] ?? []];
    if ($kuki !== []) { $b=[]; foreach ($kuki as $p) { $n=explode(';',$p,2)[0]; if($n!=='')$b[]=$n; } $s[CURLOPT_COOKIE]=implode('; ',$b); }
    if (array_key_exists('pos', $o)) { $s[CURLOPT_POST]=true; $s[CURLOPT_POSTFIELDS]=$o['pos']; }
    curl_setopt_array($ch, $s);
    $raw = curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $head = substr((string)$raw, 0, $hs);
    if (preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $head, $m) > 0) { foreach ($m[1] as $s1) { $n=trim(explode('=',$s1,2)[0]); if($n!=='')$kuki[$n]=$s1; } }
    if (preg_match('/name="csrf-token" content="([^"]+)"/', (string)$raw, $cm) === 1) $GLOBALS['csrf']=$cm[1];
    return ['kode'=>$k, 'kepala'=>$head, 'isi'=>substr((string)$raw, $hs), 'redirect'=>'-'];
};

$h = $minta($basis.'/admin/login');
preg_match('/name="csrf-token" content="([^"]+)"/', $h['isi'], $c); $csrf = $c[1];
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m); $snap = html_entity_decode($m[1][0], ENT_QUOTES);

$masuk = $minta($basis.'/livewire/update', [
    'pos'=>json_encode(['components'=>[['snapshot'=>$snap,'updates'=>['email'=>'admin@iksanknalpot.test','password'=>'password'],'calls'=>[['path'=>'','method'=>'masuk','params'=>[]]]]]], JSON_UNESCAPED_SLASHES),
    'ikut'=>false,
    'header'=>['Content-Type: application/json','X-Livewire: 1','Accept: application/json','X-CSRF-TOKEN: '.$csrf],
]);
// Ikuti redirect Livewire secara EKSPLISIT
if (preg_match('/"redirect":"([^"]+)"/', $masuk['isi'], $rm)) {
    $t = str_replace('\\/', '/', $rm[1]);
    echo "redirect effect: $t\n";
    $dasbor = $minta($basis.$t);
    echo "dasbor: ".$dasbor['kode']." ".strlen($dasbor['isi'])." bytes, Keluar? ".(str_contains($dasbor['isi'],'Keluar')?'YA':'tidak')."\n";
}

// Sekarang halaman pesanan dengan query
foreach (['?status=pending', '?status=cancelled'] as $q) {
    $d = $minta($basis.'/admin/pesanan'.$q);
    preg_match_all('/wire:snapshot="([^"]+)"/', $d['isi'], $a);
    foreach ($a[1] as $raw) { $s=html_entity_decode($raw, ENT_QUOTES); $j=json_decode($s, true); if (is_array($j) && ($j['memo']['name']??'')==='admin.pesanan-daftar') echo "$q -> status=".($j['data']['status']??'?')."\n"; }
}
