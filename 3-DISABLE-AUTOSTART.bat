@echo off
title Ham Shack Rig Link - stop
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0autostart.ps1" -Remove
echo.
pause
