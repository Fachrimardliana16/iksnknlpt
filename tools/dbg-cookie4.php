<?php
// Urutan PERSIS seperti skenario: /admin (ikut=false) dulu, lalu /admin/login
$basis = 'http://127.0.0.1:8100';
$kuki = [];

$minta = static function (string $url, array $o = []) use (&$kuki): array {
    $ch = curl_init($url);
    $s = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>$o['ikut'] ?? true, CURLOPT_TIMEOUT=>60, CURLOPT_HEADER=>true, CURLOPT_HTTPHEADER=>$o['header'] ?? []];
    if ($kuki !== []) {
        $baris = [];
        foreach ($kuki as $p) { $nn = explode(';', $p, 2)[0]; if ($nn !== '') $baris[] = $nn; }
        $s[CURLOPT_COOKIE] = implode('; ', $baris);
    }
    if (array_key_exists('pos', $o)) { $s[CURLOPT_POST]=true; $s[CURLOPT_POSTFIELDS]=$o['pos']; }
    curl_setopt_array($ch, $s);
    $raw = curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    $head = substr((string)$raw, 0, $hs);
    $set = [];
    if (preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $head, $m) > 0) {
        foreach ($m[1] as $s1) { $n = trim(explode('=', $s1, 2)[0]); if ($n !== '') { $kuki[$n] = $s1; $set[] = $n; } }
    }
    return ['kode'=>$k, 'kepala'=>$head, 'isi'=>substr((string)$raw, $hs), 'set'=>$set];
};

$xsrf = static function () use (&$kuki): string {
    if (! isset($kuki['XSRF-TOKEN'])) return '';
    $p = explode(';', $kuki['XSRF-TOKEN'], 2)[0];
    $i = strpos($p, '=');
    return $i === false ? '' : substr($p, $i + 1);
};

echo "0. kuki awal: ".count($kuki)."\n";
$tamu = $minta($basis.'/admin', ['ikut' => false]);
echo "1. GET /admin (ikut=false): ".$tamu['kode'].", Set-Cookie: ".implode(',', $tamu['set'])."\n";
$h = $minta($basis.'/admin/login');
echo "2. GET /admin/login: ".$h['kode'].", Set-Cookie: ".implode(',', $h['set'])."\n";
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m); $snap = html_entity_decode($m[1][0], ENT_QUOTES);
$xs = $xsrf();
echo "   xsrf(" . strlen($xs) . "): " . substr($xs, 0, 16) . "...\n";

$r = $minta($basis.'/livewire/update', [
    'pos' => json_encode(['components'=>[['snapshot'=>$snap,'updates'=>['email'=>'admin@iksanknalpot.test','password'=>'password'],'calls'=>[['path'=>'','method'=>'masuk','params'=>[]]]]]], JSON_UNESCAPED_SLASHES),
    'ikut' => false,
    'header' => ['Content-Type: application/json','X-Livewire: 1','Accept: application/json','X-CSRF-TOKEN: '.$xs,'X-XSRF-TOKEN: '.$xs],
]);
echo "3. POST livewire masuk: ".$r['kode']."\n";
$j = json_decode($r['isi'], true);
if (is_array($j)) {
    echo "   effects: ".(isset($j['components'][0]['effects']) ? implode(',', array_keys($j['components'][0]['effects'])) : 'n/a')."\n";
    if (isset($j['components'][0]['effects']['redirect'])) echo "   redirect: ".$j['components'][0]['effects']['redirect']."\n";
}
$d = $minta($basis.'/admin');
echo "4. GET /admin: ".$d['kode'].' '.strlen($d['isi'])." bytes\n";
