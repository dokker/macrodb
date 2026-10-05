#!/usr/bin/env bash
# Deploys the working tree to the staging host: build, rsync, composer, migrate, cache.
# Usage: ./deploy.sh [--dry-run]   (--dry-run only shows what rsync would change)
set -euo pipefail

HOST="${DEPLOY_HOST:-vertica1@we051.tarhely.com}"
REMOTE_DIR="${DEPLOY_DIR:-.macrodb}"
PHP="${DEPLOY_PHP:-/opt/alt/php85/usr/bin/php}"
URL="${DEPLOY_URL:-https://macrodb.verticaldev.hu}"

cd "$(dirname "$0")"

RSYNC_FLAGS=(-az --delete --info=stats1)
if [[ "${1:-}" == "--dry-run" ]]; then
    RSYNC_FLAGS+=(--dry-run --itemize-changes)
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Figyelem: van commitolatlan változás, ez is felkerül." >&2
fi

echo "==> Frontend build"
vendor/bin/sail npm run build

echo "==> Feltöltés (rsync)"
rsync "${RSYNC_FLAGS[@]}" \
    --exclude=.git --exclude=node_modules --exclude=vendor \
    --exclude=.env --exclude='.env.*' --exclude=.assets \
    --exclude=tests --exclude=.idea --exclude=.claude --exclude=.ai --exclude=.mcp.json \
    --exclude=docker-compose.yml --exclude=/public/hot --exclude=/public/.well-known \
    --exclude=/storage/logs --exclude='/storage/framework/*/*' --exclude='/storage/app/*' \
    --exclude='/storage/*.key' --exclude=/bootstrap/cache \
    ./ "$HOST:$REMOTE_DIR/"

if [[ "${1:-}" == "--dry-run" ]]; then
    exit 0
fi

echo "==> Szerveroldali lépések"
ssh "$HOST" bash -s -- "$REMOTE_DIR" "$PHP" <<'REMOTE'
set -euo pipefail
cd "$HOME/$1"
PHP="$2"
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app bootstrap/cache
"$PHP" /usr/local/bin/composer install --no-dev -o --no-interaction --quiet
"$PHP" artisan down --retry=10 || true
"$PHP" artisan migrate --force
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan queue:restart || true
"$PHP" artisan up
"$PHP" artisan mcp:well-known
REMOTE

echo "==> Füstteszt"
for path in / /login /.well-known/oauth-protected-resource/mcp /.well-known/oauth-authorization-server; do
    printf '%s -> ' "$path"
    curl -s -o /dev/null -w '%{http_code}\n' "$URL$path"
done
printf '/.env -> '
curl -s -o /dev/null -w '%{http_code} (404 vagy 403 az elvárt)\n' "$URL/.env"
