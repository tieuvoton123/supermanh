@echo off
chcp 65001 >nul
title SUPER MANH Shop - Dong goi NSP
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0dong_goi_nsp.ps1"
echo.
pause
