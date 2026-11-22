@echo off
title DonorNexa - Offline Server Launcher
echo ========================================================
echo         DonorNexa - Offline Emergency Blood Portal
echo ========================================================
echo.

set "MYSQL_EXE=D:\xampp\mysql\bin\mysqld.exe"
set "MYSQL_INI=D:\xampp\mysql\bin\my.ini"
set "APACHE_EXE=D:\xampp\apache\bin\httpd.exe"

echo [*] Checking MySQL service...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL is already running.
) else (
    echo [*] Starting MySQL daemon...
    start /B "" "%MYSQL_EXE%" --defaults-file="%MYSQL_INI%" --standalone
    timeout /t 3 /nobreak >nul
    echo [OK] MySQL started.
)

echo [*] Checking Apache service...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] Apache is already running.
) else (
    echo [*] Starting Apache HTTP Server...
    start /B "" "%APACHE_EXE%"
    timeout /t 2 /nobreak >nul
    echo [OK] Apache started.
)

echo.
echo ========================================================
echo   DonorNexa is live and running 100%% offline!
echo   Local URL: http://localhost/DonorNexa/public/
echo ========================================================
echo.
echo Launching your default browser...
start http://localhost/DonorNexa/public/

exit /b 0

