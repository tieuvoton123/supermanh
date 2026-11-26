# SUPER MANH Viet Hoa - dong goi NSP tren PC cua ban.
# prod.keys chi doc tai cho, KHONG gui di dau.
$ErrorActionPreference = 'Stop'
$here    = Split-Path -Parent $MyInvocation.MyCommand.Path
$titleId = '05004d414e480000'
Set-Location $here

Write-Host '==================================================' -ForegroundColor DarkYellow
Write-Host '   SUPER MANH Viet Hoa - DONG GOI NSP' -ForegroundColor Yellow
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
    Write-Host 'Thieu tools\hacbrewpack.exe (co san trong goi). Giai nen lai SuperManh_TronBo.zip day du.' -ForegroundColor Red
    Write-Host 'Neu Windows Defender da xoa file nay: mo Windows Security > Protection history > Allow, roi giai nen lai.'
    exit 1
}
Write-Host "[2/3] hacBrewPack: $hbp"

# 3) Pack
$out = Join-Path $here 'NSP'
foreach ($d in @('hacbrewpack_temp', 'hacbrewpack_nca', 'hacbrewpack_backup', $out)) {
    if (Test-Path $d) { Remove-Item -Recurse -Force $d }
}
$hbArgs = @('-k', $keys, '--titleid', $titleId,
          '--exefsdir', 'kit\exefs', '--romfsdir', 'kit\romfs', '--controldir', 'kit\control',
          '--nspdir', $out)
$hbArgs += '--nologo'
Write-Host '[3/3] Dang dong goi...'
& $hbp @hbArgs
if ($LASTEXITCODE -ne 0) { throw "hacBrewPack bao loi (ma $LASTEXITCODE). Kiem tra prod.keys co du header_key va key_area_key_application_xx." }

$nsp = Get-ChildItem -Path $out -Filter '*.nsp' | Select-Object -First 1
if (-not $nsp) { throw 'Khong thay file NSP dau ra.' }
$final = Join-Path $out 'SuperManhVietHoa_v1.5.2.nsp'
Move-Item -Force $nsp.FullName $final
foreach ($d in @('hacbrewpack_temp', 'hacbrewpack_nca', 'hacbrewpack_backup')) {
    if (Test-Path $d) { Remove-Item -Recurse -Force $d }
}
Write-Host ''
Write-Host "XONG: $final" -ForegroundColor Green
Write-Host 'Cai file NSP nay bang Tinfoil / DBI / CyberFoil. Icon SUPER MANH Viet Hoa se hien o man hinh HOME.'
