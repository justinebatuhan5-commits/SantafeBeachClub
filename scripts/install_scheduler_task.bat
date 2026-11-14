@echo off
REM ============================================================================
REM  Santa Fe Beach Club - Task Scheduler Installer
REM  Creates a Windows Scheduled Task to run Laravel's scheduler every minute.
REM  Must be run as Administrator.
REM ============================================================================

echo ======================================================
echo   Installing Windows Scheduled Task for Laravel
echo ======================================================
echo.

REM Check for admin privileges
net session >nul 2>&1
if errorlevel 1 (
    echo ERROR: This script must be run as Administrator.
    echo Right-click and select "Run as administrator".
    pause
    exit /b 1
)

set TASK_NAME=SantaFeBeachClub_LaravelScheduler
set SCRIPT_PATH=%~dp0run_scheduler.bat

echo Task Name: %TASK_NAME%
echo Script:    %SCRIPT_PATH%
echo Schedule:  Every 1 minute
echo.

REM Remove existing task if present
schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1

REM Create the scheduled task
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "\"%SCRIPT_PATH%\"" ^
    /sc minute ^
    /mo 1 ^
    /ru SYSTEM ^
    /rl HIGHEST ^
    /f

if errorlevel 1 (
    echo ERROR: Failed to create scheduled task.
    pause
    exit /b 1
)

echo.
echo SUCCESS: Scheduled task "%TASK_NAME%" created.
echo The Laravel scheduler will now run every minute automatically.
echo.
echo To verify:  schtasks /query /tn "%TASK_NAME%"
echo To remove:  schtasks /delete /tn "%TASK_NAME%" /f
echo.
pause
