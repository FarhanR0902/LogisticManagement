$ErrorActionPreference = "Continue"

"========== LOKASI PROJECT =========="
Get-Location

"========== VERSI TOOLS =========="
php -v
composer --version
node -v
npm -v
git --version

"========== LARAVEL =========="
php artisan --version
php artisan about

"========== PACKAGE COMPOSER =========="
composer show

"========== COMPOSER.JSON =========="
Get-Content composer.json

"========== PACKAGE NPM =========="
if (Test-Path package.json) {
    Get-Content package.json
    npm list --depth=0
}

"========== KONFIGURASI DATABASE AMAN =========="
if (Test-Path .env) {
    Get-Content .env |
        Where-Object { $_ -match '^\s*(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE)\s*=' } |
        ForEach-Object { ($_ -split '=', 2)[0] + "=[disembunyikan]" }
}

"========== ROUTE LARAVEL =========="
php artisan route:list

"========== MIGRATIONS =========="
Get-ChildItem database/migrations -File -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty Name

"========== FOLDER UTAMA =========="
Get-ChildItem -Directory |
    Select-Object -ExpandProperty Name

"========== GITHUB =========="
git remote -v
git branch --show-current
git status --short

"========== SELESAI =========="
