#!/usr/bin/env bash
# Nightly backup: database + uploaded files (payment screenshots, task files, photos). Keeps 14 days.
set -euo pipefail
APP=/var/www/lwe-portal
DEST=/var/backups/lwe-portal
mkdir -p "$DEST"
cd "$APP"
DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
STAMP=$(date +%Y-%m-%d_%H%M)

MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --quick -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$DEST/db_$STAMP.sql.gz"
tar -czf "$DEST/files_$STAMP.tar.gz" -C "$APP" storage/app
find "$DEST" -type f -mtime +14 -delete
echo "$(date) backup ok: $STAMP"
