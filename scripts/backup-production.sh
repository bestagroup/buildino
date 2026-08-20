#!/usr/bin/env sh
set -eu

if [ "$#" -ne 1 ]; then
    echo "Usage: scripts/backup-production.sh /absolute/backup/directory" >&2
    exit 64
fi

backup_dir="$1"
case "$backup_dir" in
    /*) ;;
    *) echo "Backup directory must be absolute." >&2; exit 64 ;;
esac

mkdir -p "$backup_dir"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
database_file="$backup_dir/buildino-db-$timestamp.sql.gz"
storage_file="$backup_dir/buildino-storage-$timestamp.tar.gz"
database_plain="$backup_dir/.buildino-db-$timestamp.sql"
checksum_file="$backup_dir/buildino-$timestamp.sha256"

cleanup_partial_backup() {
    rm -f "$database_plain" "$database_file" "$storage_file" "$checksum_file"
}
trap cleanup_partial_backup EXIT HUP INT TERM

docker compose --env-file .env.production -f compose.production.yml \
    exec -T db sh -c 'mariadb-dump --single-transaction --quick --lock-tables=false -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
    > "$database_plain"

gzip -9 < "$database_plain" > "$database_file"

docker compose --env-file .env.production -f compose.production.yml \
    run --rm --no-deps --entrypoint sh app \
    -c 'tar -C /var/www/html/storage -czf - app' > "$storage_file"

(
    cd "$backup_dir"
    sha256sum "$(basename "$database_file")" "$(basename "$storage_file")" \
        > "$(basename "$checksum_file")"
)
rm -f "$database_plain"
trap - EXIT HUP INT TERM
echo "Backup created: $timestamp"
