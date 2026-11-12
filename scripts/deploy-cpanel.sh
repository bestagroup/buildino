#!/usr/bin/env sh
set -eu

BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SKIP_GIT="${SKIP_GIT:-0}"

if [ ! -f artisan ]; then
    echo "Run this script from the Buildino project root." >&2
    exit 1
fi

if [ "$SKIP_GIT" != "1" ]; then
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        echo "Tracked files have local changes. Commit/stash them before deployment." >&2
        exit 1
    fi

    git fetch origin "$BRANCH"
    git checkout "$BRANCH"
    git pull --ff-only origin "$BRANCH"
fi

# Resolve Composer before changing availability; it must use the deployment PHP runtime.
composer_path="$(command -v "$COMPOSER_BIN")" || {
    echo "Composer is required to install the locked application dependencies." >&2
    exit 1
}

"$PHP_BIN" artisan down --retry=60
# On failure, keep maintenance mode active until the operator resolves or rolls back the release.
"$PHP_BIN" "$composer_path" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$PHP_BIN" "$composer_path" check-platform-reqs --no-dev

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

"$PHP_BIN" artisan queue:restart
"$PHP_BIN" artisan up

echo "Buildino cPanel deployment completed on branch: $BRANCH"
