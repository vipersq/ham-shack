@echo off
title Ham Shack Rig Link - test
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0HamShack-RigLink.ps1" -Test
echo.
pause
