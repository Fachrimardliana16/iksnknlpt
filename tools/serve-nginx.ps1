# Menjalankan iksanknalpot dengan nginx + php-cgi (server konkuren).
#
# Kenapa bukan `php artisan serve`: di Windows server bawaan PHP melayani SATU
# permintaan pada satu waktu (PHP_CLI_SERVER_WORKERS butuh fork, tidak tersedia
# di Windows). Peramban membuka 6 koneksi paralel, jadi permintaan berbaris dan
# halaman dengan TTFB 0,3 detik bisa selesai 10-20 detik. Terbukti saat diuji:
# enam permintaan paralel lewat `artisan serve` selesai 0,86 / 1,54 / 2,25 /
# 2,84 / 3,58 / 4,32 detik (berbaris rapi), sedangkan lewat nginx 1,1-3,9 detik
# untuk DELAPAN permintaan serentak.
#
# Catatan Windows: nginx.exe TIDAK mem-daemon-kan diri, jadi harus dijalankan
# lewat Start-Process tanpa -Wait. Menjalankannya dengan `&` membuat skrip ini
# menggantung selamanya.
#
# Pakai:    powershell -ExecutionPolicy Bypass -File tools/serve-nginx.ps1
# Hentikan: powershell -ExecutionPolicy Bypass -File tools/serve-nginx.ps1 -Stop

param(
    [switch]$Stop,
    [int]$Port = 8100
)

$ErrorActionPreference = 'Stop'
$proyek = 'D:\Projects\iksanknalpot'
$nginxDir = Join-Path $env:LOCALAPPDATA 'nginx\nginx-1.28.0'
$php = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php-cgi.exe'
$portPhp = 9200..9207

function Hentikan {
    Get-CimInstance Win32_Process -Filter "Name='nginx.exe'" -ErrorAction SilentlyContinue |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

    Get-CimInstance Win32_Process -Filter "Name='php-cgi.exe'" -ErrorAction SilentlyContinue |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

function Cek-Port([int]$p) {
    $klien = New-Object System.Net.Sockets.TcpClient
    try {
        $tugas = $klien.ConnectAsync('127.0.0.1', $p)
        if ($tugas.Wait(700)) { return $klien.Connected }
        return $false
    } catch {
        return $false
    } finally {
        $klien.Dispose()
    }
}

Hentikan
Start-Sleep -Milliseconds 800

if ($Stop) {
    Write-Output 'Server dihentikan.'
    exit 0
}

if (-not (Test-Path $php)) { throw "php-cgi tidak ditemukan: $php" }
if (-not (Test-Path (Join-Path $nginxDir 'nginx.exe'))) { throw "nginx tidak ditemukan di $nginxDir" }

# PHP_FCGI_CHILDREN diabaikan pada mode `php-cgi -b` di Windows: prosesnya
# memang membuat anak, tetapi yang menerima koneksi tetap satu per induk.
# Karena itu jumlah paralel ditentukan oleh JUMLAH PORT, bukan variabel ini.
# Terukur: 4 port memberi 4 permintaan serentak; 6 permintaan paralel membuat
# tiga di antaranya menunggu 4-6 detik.
$env:PHP_FCGI_CHILDREN = '1'
$env:PHP_FCGI_MAX_REQUESTS = '500'
$env:REDIRECT_STATUS = '200'

foreach ($p in $portPhp) {
    Start-Process -FilePath $php `
        -ArgumentList @('-b', "127.0.0.1:$p", '-c', (Join-Path (Split-Path $php) 'php.ini')) `
        -WorkingDirectory $proyek `
        -WindowStyle Hidden
}

Start-Sleep -Seconds 3

# nginx berjalan foreground: Start-Process TANPA -Wait, kalau tidak skrip menggantung.
Start-Process -FilePath (Join-Path $nginxDir 'nginx.exe') `
    -ArgumentList @('-p', $nginxDir, '-c', 'conf/iksan.conf') `
    -WorkingDirectory $nginxDir `
    -WindowStyle Hidden

Start-Sleep -Seconds 3

$hidup = 0
foreach ($p in $portPhp) { if (Cek-Port $p) { $hidup++ } }

$nginxHidup = Cek-Port $Port

Write-Output "php-cgi menjawab: $hidup/$($portPhp.Count)"
Write-Output "nginx di 127.0.0.1:$Port : $(if ($nginxHidup) { 'hidup' } else { 'MATI' })"
