<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Cart; use App\Models\Product; use App\Models\ProductVariant; use App\Models\Category;
echo "fillable carts: ".json_encode((new Cart)->getFillable())."\n";
echo "kolom note ada: ".var_export(Schema::hasColumn('carts','note'), true)."\n";
