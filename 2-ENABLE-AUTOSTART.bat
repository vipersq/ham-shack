@echo off
title Ham Shack Rig Link - autostart
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0autostart.ps1"
echo.
pause
