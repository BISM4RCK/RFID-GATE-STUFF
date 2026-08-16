@echo off
setlocal
cd /d "%~dp0"
title Smart Gate - Stop Docker Stack
echo Stopping Smart Gate containers...
docker compose stop
if errorlevel 1 (
  echo [ERROR] Could not stop the Docker Compose stack.
  pause
  exit /b 1
)
echo.
echo Smart Gate has been stopped.
echo Your MySQL volume was NOT deleted.
pause
endlocal
