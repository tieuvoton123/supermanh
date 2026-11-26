@echo off
chcp 65001 >nul
title SUPER MANH Test Host
cd /d "%~dp0"
if exist "SuperManh_TestHost.exe" (
  "SuperManh_TestHost.exe"
) else (
  python SuperManh_TestHost.py || py SuperManh_TestHost.py
)
pause
