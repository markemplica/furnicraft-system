@echo off
cd /d "%~dp0"
"C:\xampp\php\php.exe" -d openssl.cafile="%~dp0storage\certs\mail-ca-bundle.pem" artisan queue:work --tries=3
pause