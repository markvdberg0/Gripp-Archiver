@echo off
rem Gripp Back-up starten op Windows: dubbelklik voor het menu, of bijvoorbeeld
rem   Start-Windows.cmd backup --profile=mijnbedrijf
rem Haalt zo nodig eenmalig een portable Node.js op (zie tools\start.ps1).
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\start.ps1" %*
set "CODE=%ERRORLEVEL%"
if not "%CODE%"=="0" pause
exit /b %CODE%
