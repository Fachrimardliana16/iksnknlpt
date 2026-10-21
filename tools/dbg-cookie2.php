<?php

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
    if (preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $head, $m) > 0) {
        foreach ($m[1] as $s1) { $n = trim(explode('=', $s1, 2)[0]); if ($n !== '') $kuki[$n] = $s1; }
    }
    // apakah ada redirect?
    $redir = '-';
    if (preg_match('/^Location:\s*(\S+)/mi', $head, $lm)) $redir = $lm[1];
    return ['kode'=>$k, 'kepala'=>$head, 'isi'=>substr((string)$raw, $hs), 'redir'=>$redir];
};

$xsrf = static function () use (&$kuki): string {
    if (! isset($kuki['XSRF-TOKEN'])) return '';
    $p = explode(';', $kuki['XSRF-TOKEN'], 2)[0];
    $i = strpos($p, '=');
    return $i === false ? '' : substr($p, $i + 1);
};

$h = $minta($basis.'/admin/login');
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m);
$snap = html_entity_decode($m[1][0], ENT_QUOTES);
preg_match('/name="csrf-token" content="([^"]+)"/', $h['isi'], $c); $csrf = $c[1] ?? '';

$r = $minta($basis.'/livewire/update', [
    'pos' => json_encode(['components'=>[['snapshot'=>$snap,'updates'=>['email'=>'admin@iksanknalpot.test','password'=>'password'],'calls'=>[['path'=>'','method'=>'masuk','params'=>[]]]]]], JSON_UNESCAPED_SLASHES),
    'ikut' => false,
    'header' => ['Content-Type: application/json','X-Livewire: 1','Accept: application/json','X-CSRF-TOKEN: '.$csrf,'X-XSRF-TOKEN: '.$xsrf()],
]);

echo "livewire kode: ".$r['kode']." redirect: ".$r['redir']."\n";
echo "kuki sesudah login: ".count($kuki)."\n";
$s = isset($kuki['iksan-knalpot-session']) ? substr(explode(';', $kuki['iksan-knalpot-session'], 2)[0], 0, 40) : 'TIDAK ADA';
echo "session cookie: $s...\n";

// Jawaban livewire: cek isi
$j = json_decode($r['isi'], true);
if (is_array($j)) {
    echo "json keys: ".implode(',', array_keys($j))."\n";
    if (isset($j['effects']['redirect'])) echo "redirect effect: ".$j['effects']['redirect']."\n";
    $html = $j['components'][0]['effects']['html'] ?? ($j['html'] ?? '');
    if (is_string($html) && str_contains($html, 'galat')) {
        preg_match('/class="[^"]*galat[^"]*"[^>]*>(.*?)</s', $html, $gm);
        echo "GALAT di html: ".strip_tags($gm[1] ?? '?')."\n";
    }
}
// request /admin tanpa follow
$d = $minta($basis.'/admin', ['ikut' => false]);
echo "/admin tanpa follow: ".$d['kode']." -> ".$d['redir']."\n";
