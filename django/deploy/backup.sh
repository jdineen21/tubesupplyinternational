#!/bin/bash
# Daily backup of the database and the uploaded pictures and documents.
# Keeps 14 days. From root's crontab, e.g.:
#   20 3 * * * /srv/tsi/app/django/deploy/backup.sh
set -euo pipefail
dir=/srv/tsi/backups
day=$(date +%Y-%m-%d)
mkdir -p "$dir"
chmod 700 "$dir"  # the database holds the admin users' password hashes
/srv/tsi/app/django/deploy/manage.sh backup_database | gzip > "$dir/tsi-$day.sqlite3.gz"
tar -czf "$dir/media-$day.tar.gz" -C /srv/tsi media
find "$dir" -type f -mtime +14 -delete
