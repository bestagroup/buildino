#!/usr/bin/env sh
set -eu

BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
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

maintenance=0
restore_application() {
    if [ "$maintenance" = "1" ]; then
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
    fi
}
trap restore_application EXIT INT TERM

if "$PHP_BIN" artisan down --retry=60; then
    maintenance=1
fi

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

"$PHP_BIN" artisan up || true
maintenance=0

echo "Buildino cPanel deployment completed on branch: $BRANCH"
