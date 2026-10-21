<?php
require 'D:/Projects/iksanknalpot/vendor/autoload.php';
$app = require 'D:/Projects/iksanknalpot/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$u = App\Models\User::query()->whereIn('role', ['super_admin','admin'])->first();
Illuminate\Support\Facades\Auth::login($u);

$req = Illuminate\Http\Request::create('http://127.0.0.1:8100/admin/pesanan?status=pending', 'GET');
$req->setUserResolver(fn () => $u);
app()->instance('request', $req);

$res = $kernel->handle($req);
$html = (string) $res->getContent();
preg_match_all('/wire:snapshot="([^"]+)"/', $html, $all);
foreach ($all[1] as $raw) {
    $s = html_entity_decode($raw, ENT_QUOTES); $j = json_decode($s, true);
    if (is_array($j) && ($j['memo']['name'] ?? '') === 'admin.pesanan-daftar') {
        echo "kernel status=".($j['data']['status'] ?? '?')."\n";
    }
}
