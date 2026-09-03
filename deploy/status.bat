@echo off
REM Compozit Suite - report on all components
REM Thin wrapper. All logic lives in compozit.ps1.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0compozit.ps1" status %*
exit /b %ERRORLEVEL%
