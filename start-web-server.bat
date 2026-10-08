@echo off
set "PHP_INI_SCAN_DIR=%~dp0storage\certs"
"C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8000
