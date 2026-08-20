#!/usr/bin/env sh
set -eu

if [ "$#" -ne 4 ] || [ "$1" != "--confirm-restore" ]; then
    echo "Usage: scripts/restore-production.sh --confirm-restore /absolute/db.sql.gz /absolute/storage.tar.gz /absolute/checksums.sha256" >&2
    exit 64
fi

database_file="$2"
storage_file="$3"
checksum_file="$4"

for restore_file in "$database_file" "$storage_file" "$checksum_file"; do
    case "$restore_file" in
        /*) ;;
        *) echo "Restore paths must be absolute." >&2; exit 64 ;;
    esac
    [ -f "$restore_file" ] || { echo "Missing file: $restore_file" >&2; exit 66; }
done

backup_dir="$(dirname "$database_file")"
database_name="$(basename "$database_file")"
storage_name="$(basename "$storage_file")"
checksum_name="$(basename "$checksum_file")"

if [ "$(dirname "$storage_file")" != "$backup_dir" ] || [ "$(dirname "$checksum_file")" != "$backup_dir" ]; then
    echo "Database, storage and checksum files must be in the same directory." >&2
    exit 64
fi

case "$database_name" in
    buildino-db-*.sql.gz) ;;
    *) echo "Unexpected database backup filename: $database_name" >&2; exit 64 ;;
esac

backup_timestamp="${database_name#buildino-db-}"
backup_timestamp="${backup_timestamp%.sql.gz}"
if [ "$storage_name" != "buildino-storage-$backup_timestamp.tar.gz" ] || \
   [ "$checksum_name" != "buildino-$backup_timestamp.sha256" ]; then
    echo "Backup files do not belong to the same timestamped set." >&2
    exit 64
fi

(
    cd "$backup_dir"
    sha256sum -c "$checksum_name"
)

gzip -t "$database_file"
tar -tzf "$storage_file" >/dev/null
tar -tzf "$storage_file" | while IFS= read -r archive_entry; do
    case "$archive_entry" in
        /*|..|../*|*/..|*/../*)
            echo "Unsafe storage archive entry: $archive_entry" >&2
            exit 65
            ;;
        app|app/*) ;;
        *) echo "Unsafe storage archive entry: $archive_entry" >&2; exit 65 ;;
    esac
done

database_plain="$(mktemp "${TMPDIR:-/tmp}/buildino-restore.XXXXXX.sql")"
cleanup_restore_file() {
    rm -f "$database_plain"
}
trap cleanup_restore_file EXIT HUP INT TERM
gzip -dc "$database_file" > "$database_plain"

echo "Putting application in maintenance mode..."
docker compose --env-file .env.production -f compose.production.yml exec -T app \
    php artisan down --retry=60

docker compose --env-file .env.production -f compose.production.yml \
    exec -T db sh -c \
    'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
    < "$database_plain"

rm -f "$database_plain"
trap - EXIT HUP INT TERM

docker compose --env-file .env.production -f compose.production.yml \
    run --rm --no-deps --entrypoint sh app \
    -c 'find /var/www/html/storage/app -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +; tar -C /var/www/html/storage -xzf -' \
    < "$storage_file"

docker compose --env-file .env.production -f compose.production.yml exec -T app \
    php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yml exec -T app \
    php artisan release:gate --production
docker compose --env-file .env.production -f compose.production.yml exec -T app \
    php artisan up

echo "Restore completed and production gate passed."
