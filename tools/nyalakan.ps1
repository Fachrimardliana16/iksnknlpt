# Menyalakan SELURUH tumpukan (nginx + php-cgi + tunnel) dengan satu perintah.
#
# Kenapa ada skrip ini: `cloudflared tunnel --url` TANPA akun membuat domain
# acak baru setiap kali dijalankan, dan URL lama mati permanen (DNS-nya hilang,
# bukan 502). Setiap restart tunnel berarti alamat baru, dan mencarinya manual
# di log membuat orang membuka alamat yang sudah mati.
#
# Skrip ini mencetak URL barunya dengan jelas, dan menyimpannya ke
# tools/out/url.txt supaya dapat dibaca kapan saja tanpa menebak.
#
# Pakai:    powershell -ExecutionPolicy Bypass -File tools/nyalakan.ps1
# Hentikan: powershell -ExecutionPolicy Bypass -File tools/nyalakan.ps1 -Stop

param(
    [switch]$Stop,
    [int]$Port = 8100
)

$ErrorActionPreference = 'Stop'
$proyek = 'D:\Projects\iksanknalpot'
$cloudflared = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
$berkasUrl = Join-Path $proyek 'tools\out\url.txt'

function HentikanSemua {
    Get-CimInstance Win32_Process -Filter "Name='cloudflared.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like "*127.0.0.1:$Port*" } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

    & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $proyek 'tools\serve-nginx.ps1') -Stop | Out-Null
}

if ($Stop) {
    HentikanSemua
    Remove-Item $berkasUrl -ErrorAction SilentlyContinue
    Write-Output 'Semua dihentikan (nginx, php-cgi, tunnel).'
    exit 0
}

# 1. Server
Write-Output '== 1/3 menyalakan nginx + php-cgi =='
& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $proyek 'tools\serve-nginx.ps1')

Start-Sleep -Seconds 1

# 2. Pastikan server benar-benar menjawab sebelum tunnel dinyalakan.
#    Tanpa ini tunnel mendaftar ke server yang belum siap dan pengunjung
#    pertama mendapat 502.
$siap = $false
foreach ($i in 1..20) {
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/" -UseBasicParsing -TimeoutSec 5
        if ($r.StatusCode -eq 200) { $siap = $true; break }
    } catch {
        Start-Sleep -Milliseconds 500
    }
}

if (-not $siap) { throw "Server di 127.0.0.1:$Port tidak menjawab. Tunnel dibatalkan." }
Write-Output '   server menjawab 200.'

# 3. Tunnel, dengan log ditangkap supaya URL-nya dapat dibaca.
Write-Output '== 2/3 menyalakan tunnel =='
$logTunnel = Join-Path $proyek 'tools\out\tunnel.log'
Remove-Item $logTunnel -ErrorAction SilentlyContinue

Start-Process -FilePath $cloudflared `
    -ArgumentList @('tunnel', '--url', "http://127.0.0.1:$Port", '--no-autoupdate') `
    -RedirectStandardError $logTunnel `
    -RedirectStandardOutput (Join-Path $proyek 'tools\out\tunnel.out.log') `
    -WindowStyle Hidden

# 4. Tunggu URL muncul di log, bukan menebak lama tidurnya.
$url = $null
foreach ($i in 1..60) {
    Start-Sleep -Milliseconds 500
    if (Test-Path $logTunnel) {
        $cocok = Select-String -Path $logTunnel -Pattern '(https://[a-z0-9-]+\.trycloudflare\.com)' -ErrorAction SilentlyContinue |
            Select-Object -First 1
        if ($cocok) { $url = $cocok.Matches[0].Groups[1].Value; break }
    }
}

if (-not $url) { throw "URL tunnel tidak muncul di log. Periksa $logTunnel" }

# 5. Tunggu sampai URL benar-benar menjawab 200. Ini yang mencegah pengguna
#    membuka alamat yang belum siap (gejala Error 1033).
Write-Output '== 3/3 menunggu tunnel siap =='
$hidup = $false
foreach ($i in 1..40) {
    try {
        $r = Invoke-WebRequest -Uri "$url/admin/login" -UseBasicParsing -TimeoutSec 10
        if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 400) { $hidup = $true; break }
    } catch {
        Start-Sleep -Seconds 1
    }
}

$url | Set-Content -Path $berkasUrl -Encoding utf8

Write-Output ''
if ($hidup) {
    Write-Output '  ===================================================='
    Write-Output "   SIAP:  $url/admin/login"
    Write-Output '  ===================================================='
} else {
    Write-Output "  Tunnel terdaftar tetapi belum menjawab 200: $url"
    Write-Output '  Coba buka beberapa detik lagi; log ada di tools/out/tunnel.log'
}
Write-Output ''
Write-Output "  URL disimpan di: $berkasUrl"
Write-Output '  Catatan: alamat ini BERUBAH setiap tunnel dijalankan ulang.'
