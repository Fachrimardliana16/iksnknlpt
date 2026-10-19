# Matikan seluruh peramban headless yang ditinggalkan alat ukur.
#
# Penting: Chrome yang menggantung memegang berkas di public/build, sehingga
# `npm run build` gagal dengan EPERM saat membersihkan direktori keluaran.

$proses = Get-CimInstance Win32_Process -Filter "Name='chrome.exe'" |
    Where-Object { $_.CommandLine -match 'chrome-ukur-|chrome-tutup-|chrome-dbg' }

if (-not $proses) {
    Write-Output "Tidak ada peramban headless yang menggantung."
    exit 0
}

Write-Output ("Menghentikan " + $proses.Count + " proses Chrome headless.")
$proses | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Start-Sleep -Seconds 2

$sisa = Get-CimInstance Win32_Process -Filter "Name='chrome.exe'" |
    Where-Object { $_.CommandLine -match 'chrome-ukur-|chrome-tutup-|chrome-dbg' }

Write-Output ("Sisa: " + ($sisa | Measure-Object).Count)
