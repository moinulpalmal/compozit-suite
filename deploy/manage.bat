@echo off
REM Compozit Suite - interactive operations console.
REM The one entry point. Start here if you are not sure what you need.
REM Thin wrapper: all logic lives in compozit.ps1.
title Compozit Suite - Operations Console
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0compozit.ps1" menu %*
set "RC=%ERRORLEVEL%"
REM Hold the window open on failure so a double-click user can read the error.
if not "%RC%"=="0" pause
exit /b %RC%
