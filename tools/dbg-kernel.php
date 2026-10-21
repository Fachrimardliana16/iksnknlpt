<?php
require 'D:/Projects/iksanknalpot/vendor/autoload.php';
$app = require 'D:/Projects/iksanknalpot/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = App\Models\User::query()->whereIn('role', ['super_admin', 'admin'])->first();
Illuminate\Support\Facades\Auth::login($u);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

foreach (['status=pending', 'status=cancelled'] as $q) {
    $req = Illuminate\Http\Request::create('/admin/pesanan?' . $q, 'GET');
    $req->setUserResolver(fn () => $u);
    app()->instance('request', $req);
    $res = $kernel->handle($req);
    $html = (string) $res->getContent();
    if (preg_match_all('/wire:snapshot="([^"]+)"/', $html, $all)) {
        foreach ($all[1] as $raw) {
            $s = html_entity_decode($raw, ENT_QUOTES);
            $j = json_decode($s, true);
            if (is_array($j) && ($j['memo']['name'] ?? '') === 'admin.pesanan-daftar') {
                echo "$q -> snapshot status=" . ($j['data']['status'] ?? '?') . "\n";
            }
        }
    }
    // bersihkan resolusi supaya request berikutnya tidak terpengaruh
    $app->make(Illuminate\Contracts\Foundation\Application::class)->forgetInstance('request');
}
