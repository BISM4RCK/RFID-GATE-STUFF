@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Smart Gate - Docker Launcher

cd /d "%~dp0"

echo.
echo ============================================================
echo                  SMART GATE DOCKER LAUNCHER
echo ============================================================
echo.

REM --- Verify Docker CLI ---
where docker >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker was not found in PATH.
    echo Install Docker Desktop, then run this file again.
    pause
    exit /b 1
)

REM --- Verify Docker Desktop / daemon ---
echo [1/6] Checking Docker...
docker info >nul 2>&1
if errorlevel 1 (
    echo Docker Desktop is not running. Starting Docker Desktop...
    if exist "%ProgramFiles%\Docker\Docker\Docker Desktop.exe" (
        start "" "%ProgramFiles%\Docker\Docker\Docker Desktop.exe"
    ) else if exist "%LocalAppData%\Programs\Docker\Docker\Docker Desktop.exe" (
        start "" "%LocalAppData%\Programs\Docker\Docker\Docker Desktop.exe"
    ) else (
        echo [ERROR] Docker Desktop executable was not found.
        echo Start Docker Desktop manually and run this file again.
        pause
        exit /b 1
    )

    echo Waiting for Docker Desktop...
    set /a WAIT_COUNT=0
    :WAIT_DOCKER
    timeout /t 3 /nobreak >nul
    docker info >nul 2>&1
    if not errorlevel 1 goto DOCKER_READY
    set /a WAIT_COUNT+=1
    if !WAIT_COUNT! GEQ 40 (
        echo [ERROR] Docker Desktop did not become ready within 120 seconds.
        pause
        exit /b 1
    )
    goto WAIT_DOCKER
)

:DOCKER_READY
echo [OK] Docker is ready.
echo.

REM --- Ensure required local environment files exist ---
echo [2/6] Checking environment files...

if not exist ".env" (
    if exist ".env.example" (
        copy /Y ".env.example" ".env" >nul
        echo [OK] Created root .env from .env.example.
    ) else (
        echo [ERROR] Root .env is missing and no .env.example exists.
        pause
        exit /b 1
    )
)

if not exist "backend\.env" (
    if exist "backend\.env.example" (
        copy /Y "backend\.env.example" "backend\.env" >nul
        echo [OK] Created backend\.env from backend\.env.example.
    ) else if exist "backend\.env.docker.example" (
        copy /Y "backend\.env.docker.example" "backend\.env" >nul
        echo [OK] Created backend\.env from backend\.env.docker.example.
    ) else (
        echo [ERROR] backend\.env is missing and no template exists.
        pause
        exit /b 1
    )
)

echo [OK] Environment files are present.
echo.

REM --- Validate Compose before doing anything destructive ---
echo [3/6] Validating Docker Compose configuration...
docker compose config > "%TEMP%\smart-gate-compose-check.txt" 2>&1
if errorlevel 1 (
    echo [ERROR] Docker Compose configuration is invalid.
    echo.
    type "%TEMP%\smart-gate-compose-check.txt"
    del "%TEMP%\smart-gate-compose-check.txt" >nul 2>&1
    pause
    exit /b 1
)
del "%TEMP%\smart-gate-compose-check.txt" >nul 2>&1
echo [OK] Docker Compose configuration is valid.
echo.

REM --- Build using the normal cache ---
echo [4/6] Building Smart Gate containers...
echo This uses Docker's normal build cache. No --no-cache is used.
echo.
docker compose build
if errorlevel 1 (
    echo.
    echo [ERROR] Docker build failed.
    echo Review the error above.
    pause
    exit /b 1
)
echo [OK] Build completed.
echo.

REM --- Start without deleting volumes ---
echo [5/6] Starting Smart Gate...
docker compose up -d
if errorlevel 1 (
    echo.
    echo [ERROR] Docker Compose failed to start the stack.
    pause
    exit /b 1
)

echo.
echo Waiting for services to initialize...
timeout /t 8 /nobreak >nul
echo.

REM --- Show status ---
echo [6/6] Checking service status...
docker compose ps
echo.

echo ============================================================
echo SMART GATE IS STARTING
echo ============================================================
echo.
echo Local URL:
echo   http://localhost:8080/smart-gate/
echo.
echo Public URL:
echo   https://gate.kunehobatumbakal.site/
echo.
echo IMPORTANT:
echo   - This script does NOT delete Docker volumes.
echo   - MySQL data is preserved.
echo   - The Cloudflare Tunnel must be running separately.
echo.
echo Recent Laravel logs:
echo ------------------------------------------------------------
docker compose logs app --tail=40
echo ------------------------------------------------------------
echo.

REM Open the public URL. If the tunnel is unavailable, use the local URL.
echo Opening the public Smart Gate URL...
start "" "https://gate.kunehobatumbakal.site/"

echo.
echo Done.
echo Leave Docker Desktop running while using Smart Gate.
echo.
pause
endlocal
