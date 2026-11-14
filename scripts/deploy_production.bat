@echo off
REM ============================================================================
REM  Santa Fe Beach Club - Production Deployment Script
REM  Run this once on the production server after uploading the project files.
REM ============================================================================

echo ======================================================
echo   Santa Fe Beach Club - Production Deployment
echo ======================================================
echo.

REM ─── Step 1: Navigate to Laravel Core ───────────────────────────────────────
cd /d "%~dp0..\laravel_core"
echo [1/7] Working directory: %CD%
echo.

REM ─── Step 2: Verify PHP ─────────────────────────────────────────────────────
echo [2/7] Checking PHP version...
php -v
if errorlevel 1 (
    echo ERROR: PHP is not in the system PATH. Please install PHP 8.2+ first.
    pause
    exit /b 1
)
echo.

REM ─── Step 3: Install Dependencies (no dev) ─────────────────────────────────
echo [3/7] Installing Composer dependencies (production mode)...
php composer.phar install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 (
    echo ERROR: Composer install failed. Check composer.phar is present.
    pause
    exit /b 1
)
echo.

REM ─── Step 4: Set Permissions ────────────────────────────────────────────────
echo [4/7] Setting directory permissions...
if not exist "storage\logs" mkdir "storage\logs"
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions"
if not exist "storage\framework\views" mkdir "storage\framework\views"
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data"
if not exist "bootstrap\cache" mkdir "bootstrap\cache"
echo     storage\ and bootstrap\cache\ directories verified.
echo.

REM ─── Step 5: Run Migrations ─────────────────────────────────────────────────
echo [5/7] Running database migrations...
php artisan migrate --force
echo.

REM ─── Step 6: Cache Configuration for Performance ───────────────────────────
echo [6/7] Caching configuration, routes, and views...
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo     Production caches built successfully.
echo.

REM ─── Step 7: Verify Deployment ──────────────────────────────────────────────
echo [7/7] Running system verification...
php run_full_qa.php
echo.

echo ======================================================
echo   Deployment complete! 
echo.
echo   Next steps:
echo     1. Set up Windows Task Scheduler with run_scheduler.bat
echo     2. Configure Apache VHost (see apache-vhost.conf)
echo     3. Set GEMINI_API_KEY in .env for chatbot
echo     4. Set DB credentials in .env
echo ======================================================
pause
