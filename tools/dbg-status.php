<?php
$basis = 'http://127.0.0.1:8100';
$kuki = [];
$minta = static function (string $url, array $o = []) use (&$kuki): array {
    $ch = curl_init($url);
    $s = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>$o['ikut'] ?? true, CURLOPT_TIMEOUT=>60, CURLOPT_HEADER=>true, CURLOPT_HTTPHEADER=>$o['header'] ?? []];
    if ($kuki !== []) { $baris = []; foreach ($kuki as $p) { $nn = explode(';', $p, 2)[0]; if ($nn !== '') $baris[] = $nn; } $s[CURLOPT_COOKIE] = implode('; ', $baris); }
    if (array_key_exists('pos', $o)) { $s[CURLOPT_POST]=true; $s[CURLOPT_POSTFIELDS]=$o['pos']; }
    curl_setopt_array($ch, $s);
    $raw = curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $head = substr((string)$raw, 0, $hs);
    if (preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $head, $m) > 0) { foreach ($m[1] as $s1) { $n = trim(explode('=', $s1, 2)[0]); if ($n !== '') $kuki[$n] = $s1; } }
    if (preg_match('/name="csrf-token" content="([^"]+)"/', (string)$raw, $cm) === 1) $GLOBALS['csrf_token_terakhir'] = $cm[1];
    return ['kode'=>$k, 'kepala'=>$head, 'isi'=>substr((string)$raw, $hs)];
};

// Login
$h = $minta($basis.'/admin/login');
preg_match('/name="csrf-token" content="([^"]+)"/', $h['isi'], $c); $csrf = $c[1];
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m); $snap = html_entity_decode($m[1][0], ENT_QUOTES);
$minta($basis.'/livewire/update', [
    'pos' => json_encode(['components'=>[['snapshot'=>$snap,'updates'=>['email'=>'admin@iksanknalpot.test','password'=>'password'],'calls'=>[['path'=>'','method'=>'masuk','params'=>[]]]]]], JSON_UNESCAPED_SLASHES),
    'ikut' => false,
    'header' => ['Content-Type: application/json','X-Livewire: 1','Accept: application/json','X-CSRF-TOKEN: '.$csrf],
]);

foreach (['status=pending','status=cancelled','status=completed'] as $q) {
    $d = $minta($basis.'/admin/pesanan?'.$q.'&bayar=semua&cari=&urut=terbaru');
    // Hitung nomor unik di tabel saja (hari di luar filter chip juga ada)
    preg_match_all('/IK-\d{8}-\d{4}/', $d['isi'], $mm);
    $unik = count(array_unique($mm[0]));
    // Badge status yang tampil
    $badge = substr_count($d['isi'], 'badge-warn');
    echo "$q: HTTP ".$d['kode']." IK-unik=".$unik." bytes=".strlen($d['isi'])."\n";
}
