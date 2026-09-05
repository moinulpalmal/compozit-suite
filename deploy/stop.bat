@echo off
REM Compozit Suite - stop them, letting in-flight jobs finish
REM Thin wrapper. All logic lives in compozit.ps1.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0compozit.ps1" stop %*
exit /b %ERRORLEVEL%
