#!/usr/bin/env bash
# aHost / cPanel: ~/public_html ichida ishga tushiring
set -euo pipefail

cd "$(dirname "$0")/.." 2>/dev/null || cd ~/public_html
ROOT="${1:-$HOME/public_html}"
cd "$ROOT"

echo "==> Deploy: $ROOT"

if [ -d .git ]; then
  git pull origin main
else
  echo "Git yo‘q. Avval: git clone https://github.com/uydayolgiz5-dev/boss-fish.git ."
  exit 1
fi

PHP=php
if command -v ea-php82 >/dev/null 2>&1; then
  PHP=ea-php82
elif command -v ea-php83 >/dev/null 2>&1; then
  PHP=ea-php83
fi

echo "==> PHP: $($PHP -v | head -1)"

if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --optimize-autoloader
else
  $PHP -d memory_limit=512M $(command -v composer.phar 2>/dev/null || echo composer) install --no-dev --optimize-autoloader
fi

if [ ! -f .env ]; then
  echo "ERROR: .env yo‘q. Avval .env qo‘ying."
  exit 1
fi

$PHP artisan migrate --force
$PHP artisan storage:link 2>/dev/null || true
chmod -R 775 storage bootstrap/cache || true

$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "==> Deploy OK"
