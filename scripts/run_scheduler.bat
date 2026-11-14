@echo off
REM Santa Fe Beach Club - Laravel Background Scheduler Runner
cd /d "C:\xampp\htdocs\SantaBeachClub-BookingSystem\laravel_core"
php artisan schedule:run >> "storage\logs\scheduler.log" 2>&1
