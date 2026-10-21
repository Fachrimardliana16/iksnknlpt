<?php
// Periksa: apakah header X-XSRF-TOKEN persis sama dgn nilai cookie?
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
    return ['kode'=>$k, 'kepala'=>$head, 'isi'=>substr((string)$raw, $hs)];
};

$minta($basis.'/admin', ['ikut' => false]);
$h = $minta($basis.'/admin/login');

// Cookie yang AKAN dikirim
$baris = [];
foreach ($kuki as $p) { $nn = explode(';', $p, 2)[0]; if ($nn !== '') $baris[] = $nn; }
echo "Cookie header: ".implode('; ', $baris)."\n\n";

// Nilai cookie XSRF vs nilai yang dipakai header
$cookieRaw = $kuki['XSRF-TOKEN'] ?? 'TIDAK ADA';
echo "Set-Cookie mentah: ".$cookieRaw."\n";
$pasangan = explode(';', $cookieRaw, 2)[0];
$i = strpos($pasangan, '=');
$nilai = $i === false ? '' : substr($pasangan, $i + 1);
echo "Nilai XSRF (header): ".$nilai."\n";
// value di dalam cookie baris: sudah "XSRF-TOKEN=<nilai>"
echo "Perlu dicek: apakah urldecode dibutuhkan? panjang nilai=".strlen($nilai)."\n";
echo "ada %3D? ".(strpos($nilai, '%3D') !== false ? 'YA' : 'tidak')."\n";

// coba keduanya
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m); $snap = html_entity_decode($m[1][0], ENT_QUOTES);
foreach (['mentah' => $nilai, 'urldecode' => urldecode($nilai)] as $nama => $v) {
    $kuki2 = $kuki;
    $r = (function () use ($basis, $snap, $v, $kuki2) {
        $ch = curl_init($basis.'/livewire/update');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_TIMEOUT=>60, CURLOPT_HEADER=>true,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['components'=>[['snapshot'=>$snap,'updates'=>['email'=>'admin@iksanknalpot.test','password'=>'password'],'calls'=>[['path'=>'','method'=>'masuk','params'=>[]]]]]], JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Livewire: 1','Accept: application/json','X-CSRF-TOKEN: '.$v,'X-XSRF-TOKEN: '.$v],
            CURLOPT_COOKIE=>implode('; ', array_map(fn($p) => explode(';', $p, 2)[0], $kuki2)),
        ]);
        $raw = curl_exec($ch); $k = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $k;
    })();
    echo "POST dgn $nama: ".$r."\n";
}
