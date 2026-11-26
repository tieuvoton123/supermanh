@echo off
chcp 65001 >nul
cd /d "%~dp0"
set "TARGET=%~dp0CHAY_TEST_HOST.bat"
set "LNK=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\SuperManh Test Host.lnk"
if exist "%LNK%" (
  del "%LNK%"
  echo Da TAT tu chay cung Windows.
) else (
  powershell -NoProfile -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut($env:LNK); $s.TargetPath=$env:TARGET; $s.WorkingDirectory='%~dp0'; $s.WindowStyle=7; $s.Save()"
  echo Da BAT tu chay cung Windows. Chay lai file nay de tat.
)
pause
