<?php
// Login, lalu minta HALAMAN PENUH dengan query — tanpa redirect follow.
// Setelah dapat snapshot, ikut Livewire: klik aturStatus('pending') via /livewire/update.
$basis = 'http://127.0.0.1:8100';
$kuki = [];
$minta = static function (string $url, array $o = []) use (&$kuki): array {
    $ch = curl_init($url);
    $s = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>$o['ikut'] ?? true, CURLOPT_TIMEOUT=>60, CURLOPT_HEADER=>true, CURLOPT_HTTPHEADER=>$o['header'] ?? []];
    if ($kuki !== []) { $b=[]; foreach ($kuki as $p) { $n=explode(';',$p,2)[0]; if($n!=='')$b[]=$n; } $s[CURLOPT_COOKIE]=implode('; ',$b); }
    if (array_key_exists('pos', $o)) { $s[CURLOPT_POST]=true; $s[CURLOPT_POSTFIELDS]=$o['pos']; }
    curl_setopt_array($ch, $s);
    $raw = curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $head = substr((string)$raw, 0, $hs);
    if (preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $head, $m) > 0) { foreach ($m[1] as $s1) { $n=trim(explode('=',$s1,2)[0]); if($n!=='')$kuki[$n]=$s1; } }
    if (preg_match('/name="csrf-token" content="([^"]+)"/', (string)$raw, $cm) === 1) $GLOBALS['csrf']=$cm[1];
    return ['kode'=>$k, 'kepala'=>$head, 'isi'=>substr((string)$raw, $hs)];
};

// 1. Login — wait for redirect effects
$h = $minta($basis.'/admin/login');
preg_match('/name="csrf-token" content="([^"]+)"/', $h['isi'], $c); $csrf = $c[1];
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m); $snap = html_entity_decode($m[1][0], ENT_QUOTES);
$masuk = $minta($basis.'/livewire/update', [
    'pos'=>json_encode(['components'=>[['snapshot'=>$snap,'updates'=>['email'=>'admin@iksanknalpot.test','password'=>'password'],'calls'=>[['path'=>'','method'=>'masuk','params'=>[]]]]]], JSON_UNESCAPED_SLASHES),
    'ikut'=>false,
    'header'=>['Content-Type: application/json','X-Livewire: 1','Accept: application/json','X-CSRF-TOKEN: '.$csrf],
]);
echo "masuk: ".$masuk['kode'].", redirect effect? ".(strpos($masuk['isi'],'"redirect"')!==false?'YA':'tidak')."\n";

// 2. GET halaman TANPA query dulu (seperti navigasi normal)
$d1 = $minta($basis.'/admin/pesanan');
// snapshot awal
preg_match_all('/wire:snapshot="([^"]+)"/', $d1['isi'], $a1);
$snapDaftar = null;
foreach ($a1[1] as $raw) { $s=html_entity_decode($raw, ENT_QUOTES); $j=json_decode($s, true); if (is_array($j) && ($j['memo']['name']??'')==='admin.pesanan-daftar') $snapDaftar=$s; }
echo "halaman awal status=".json_decode($snapDaftar, true)['data']['status']."\n";

// 3. Sekarang GET dengan query
$d2 = $minta($basis.'/admin/pesanan?status=pending');
preg_match_all('/wire:snapshot="([^"]+)"/', $d2['isi'], $a2);
foreach ($a2[1] as $raw) { $s=html_entity_decode($raw, ENT_QUOTES); $j=json_decode($s, true); if (is_array($j) && ($j['memo']['name']??'')==='admin.pesanan-daftar') echo "halaman dengan ?status=pending status=".$j['data']['status']."\n"; }
