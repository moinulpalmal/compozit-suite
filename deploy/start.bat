@echo off
REM Compozit Suite - start queue workers and scheduler
REM Thin wrapper. All logic lives in compozit.ps1.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0compozit.ps1" start %*
exit /b %ERRORLEVEL%
