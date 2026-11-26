# SUPER MANH Shop - dong goi NSP tren PC cua ban.
# prod.keys chi doc tai cho, KHONG gui di dau.
$ErrorActionPreference = 'Stop'
$here    = Split-Path -Parent $MyInvocation.MyCommand.Path
$titleId = '05004d414e480000'
Set-Location $here

Write-Host '==================================================' -ForegroundColor DarkYellow
Write-Host '   SUPER MANH Shop - DONG GOI NSP' -ForegroundColor Yellow
Write-Host '==================================================' -ForegroundColor DarkYellow

# 1) prod.keys
$keys = $null
foreach ($k in @("$here\prod.keys", "$here\keys.txt", "$env:USERPROFILE\.switch\prod.keys")) {
    if (Test-Path $k) { $keys = $k; break }
}
if (-not $keys) {
    Write-Host ''
    Write-Host 'Khong tim thay prod.keys.' -ForegroundColor Red
    Write-Host 'Chep file prod.keys (dump tu may Switch cua ban bang Lockpick_RCM) vao thu muc nay:'
    Write-Host "  $here"
    Write-Host 'roi chay lai DONG_GOI_NSP.bat.'
    exit 1
}
Write-Host "[1/3] Dung khoa: $keys"

# 2) hacBrewPack
$hbp = Join-Path $here 'tools\hacbrewpack.exe'
if (-not (Test-Path $hbp)) {
    Write-Host '[2/3] Tai hacBrewPack tu GitHub (The-4n/hacBrewPack)...'
    New-Item -ItemType Directory -Force -Path (Join-Path $here 'tools') | Out-Null
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $rel   = Invoke-RestMethod -UseBasicParsing -Uri 'https://api.github.com/repos/The-4n/hacBrewPack/releases/latest' -Headers @{ 'User-Agent' = 'supermanh' }
    $asset = $rel.assets | Where-Object { $_.name -match '(?i)win' -and $_.name -match '(?i)\.zip$' } | Select-Object -First 1
    if (-not $asset) { $asset = $rel.assets | Where-Object { $_.name -match '(?i)\.zip$' } | Select-Object -First 1 }
    if (-not $asset) { throw 'Khong tim thay ban hacBrewPack cho Windows. Tai thu cong hacbrewpack.exe vao thu muc tools\.' }
    $zip = Join-Path $env:TEMP $asset.name
    Invoke-WebRequest -UseBasicParsing -Uri $asset.browser_download_url -OutFile $zip
    $tmp = Join-Path $env:TEMP 'smss_hbp'
    if (Test-Path $tmp) { Remove-Item -Recurse -Force $tmp }
    Expand-Archive -Path $zip -DestinationPath $tmp -Force
    $exe = Get-ChildItem -Path $tmp -Recurse -Filter 'hacbrewpack.exe' | Select-Object -First 1
    if (-not $exe) { throw 'Trong file tai ve khong co hacbrewpack.exe.' }
    Copy-Item $exe.FullName $hbp
}
Write-Host "[2/3] hacBrewPack: $hbp"

# 3) Pack
$out = Join-Path $here 'NSP'
foreach ($d in @('hacbrewpack_temp', 'hacbrewpack_nca', 'hacbrewpack_backup', $out)) {
    if (Test-Path $d) { Remove-Item -Recurse -Force $d }
}
$help = (& $hbp --help 2>&1 | Out-String)
$hbArgs = @('-k', $keys, '--titleid', $titleId,
          '--exefsdir', 'kit\exefs', '--romfsdir', 'kit\romfs', '--controldir', 'kit\control',
          '--nspdir', $out)
if (-not (Test-Path 'kit\logo') -and $help -match '--nologo') { $hbArgs += '--nologo' }
if (Test-Path 'kit\logo') { $hbArgs += @('--logodir', 'kit\logo') }
Write-Host '[3/3] Dang dong goi...'
& $hbp @hbArgs
if ($LASTEXITCODE -ne 0) { throw "hacBrewPack bao loi (ma $LASTEXITCODE). Kiem tra prod.keys co du header_key va key_area_key_application_xx." }

$nsp = Get-ChildItem -Path $out -Filter '*.nsp' | Select-Object -First 1
if (-not $nsp) { throw 'Khong thay file NSP dau ra.' }
$final = Join-Path $out 'SuperManhShop_v1.5.1.nsp'
Move-Item -Force $nsp.FullName $final
foreach ($d in @('hacbrewpack_temp', 'hacbrewpack_nca', 'hacbrewpack_backup')) {
    if (Test-Path $d) { Remove-Item -Recurse -Force $d }
}
Write-Host ''
Write-Host "XONG: $final" -ForegroundColor Green
Write-Host 'Cai file NSP nay bang Tinfoil / DBI / CyberFoil. Icon SUPER MANH Shop se hien o man hinh HOME.'
