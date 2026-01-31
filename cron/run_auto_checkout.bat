@echo off
REM ============================================
REM MiPayMaster Auto-Checkout Cron Job
REM Runs daily to close open attendance sessions
REM ============================================

REM Set paths
set PHP_PATH=C:\xampp\php\php.exe
set CRON_PATH=C:\xampp\htdocs\Mipaymaster\cron\cron_auto_checkout.php
set LOG_PATH=C:\xampp\htdocs\Mipaymaster\logs\cron_auto_checkout.log

REM Create logs directory if it doesn't exist
if not exist "C:\xampp\htdocs\Mipaymaster\logs" mkdir "C:\xampp\htdocs\Mipaymaster\logs"

REM Run the cron job and append output to log
echo. >> "%LOG_PATH%"
echo ============================================ >> "%LOG_PATH%"
echo Running at: %date% %time% >> "%LOG_PATH%"
echo ============================================ >> "%LOG_PATH%"

"%PHP_PATH%" -d CRON_ALLOWED=1 "%CRON_PATH%" >> "%LOG_PATH%" 2>&1

echo Completed at: %date% %time% >> "%LOG_PATH%"
