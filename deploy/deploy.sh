#!/usr/bin/env bash
# Deploy the MedAlbumin backend + public site to medalbumin.com (cPanel/LiteSpeed shared hosting).
# Usage: bash deploy/deploy.sh          (full deploy)
#        bash deploy/deploy.sh --dry    (show what would be uploaded)
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$HERE")"
BACKEND="$ROOT/backend"

CONFIG="$HERE/deploy.config.sh"
if [[ ! -f "$CONFIG" ]]; then
  echo "Missing $CONFIG — copy deploy.config.example.sh to deploy.config.sh and fill it in." >&2
  exit 1
fi
# shellcheck source=deploy.config.example.sh
source "$CONFIG"

SSH_OPTS=(-p "$SSH_PORT" -i "$SSH_KEY" -o StrictHostKeyChecking=accept-new)
REMOTE="$SSH_USER@$SSH_HOST"
DRY=""
[[ "${1:-}" == "--dry" ]] && DRY="--dry-run"

echo "==> 1/5 Building Vite assets"
(cd "$BACKEND" && npm run build)

echo "==> 2/5 Installing production PHP dependencies (composer --no-dev)"
(cd "$BACKEND" && composer install --no-dev --optimize-autoloader --no-interaction)

restore_dev_deps() {
  echo "==> Restoring local dev dependencies (composer install)"
  (cd "$BACKEND" && composer install --no-interaction >/dev/null)
}
trap restore_dev_deps EXIT

echo "==> 3/5 Uploading app code to ~/$REMOTE_APP"
rsync -az --delete $DRY -e "ssh ${SSH_OPTS[*]}" \
  --exclude '.env' --exclude '.env.example' --exclude '.git' \
  --exclude 'node_modules' --exclude 'tests' --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/data/*' --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' --exclude 'storage/app/public/*' \
  --exclude 'public' --exclude '.phpunit.result.cache' \
  "$BACKEND/" "$REMOTE:$REMOTE_APP/"

echo "==> 4/5 Uploading web root to ~/$REMOTE_WEB"
rsync -az $DRY -e "ssh ${SSH_OPTS[*]}" \
  --exclude 'storage' --exclude 'hot' \
  "$BACKEND/public/" "$REMOTE:$REMOTE_WEB/"

if [[ -n "$DRY" ]]; then
  echo "Dry run — skipping server-side steps."
  exit 0
fi

echo "==> 5/5 Finishing on the server"
ssh "${SSH_OPTS[@]}" "$REMOTE" REMOTE_APP="$REMOTE_APP" REMOTE_WEB="$REMOTE_WEB" 'bash -s' <<'EOSH'
set -euo pipefail
cd ~

# Point public_html/index.php at ~/REMOTE_APP instead of __DIR__/..
php -r '
  $app = getenv("HOME") . "/" . getenv("REMOTE_APP");
  $web = getenv("HOME") . "/" . getenv("REMOTE_WEB");
  $f = "$web/index.php";
  $c = file_get_contents($f);
  $c = str_replace("__DIR__.\x27/../storage", var_export("$app/storage", true) . ".\x27", $c);
  $c = str_replace("__DIR__.\x27/../vendor", var_export("$app/vendor", true) . ".\x27", $c);
  $c = str_replace("__DIR__.\x27/../bootstrap", var_export("$app/bootstrap", true) . ".\x27", $c);
  file_put_contents($f, $c);
'

# storage symlink for uploaded files
rm -rf "$HOME/$REMOTE_WEB/storage"
ln -sfn "$HOME/$REMOTE_APP/storage/app/public" "$HOME/$REMOTE_WEB/storage"

cd "$HOME/$REMOTE_APP"
if [[ ! -f .env ]]; then
  echo '!! No .env on the server yet. Follow "First deploy only" in deploy/DEPLOY.md, then rerun deploy.sh.'
  exit 0
fi
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true
echo "Deploy finished — https://medalbumin.com"
EOSH
