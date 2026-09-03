@echo off
REM Compozit Suite - restart. Run after every deployment.
REM Thin wrapper. All logic lives in compozit.ps1.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0compozit.ps1" restart %*
exit /b %ERRORLEVEL%
