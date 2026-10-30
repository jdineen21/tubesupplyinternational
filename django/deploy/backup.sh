#!/bin/bash
# Daily backup of the database and the uploaded pictures and documents.
# Keeps 14 days. From root's crontab, e.g.:
#   20 3 * * * /srv/tsi/app/django/deploy/backup.sh
set -euo pipefail
dir=/srv/tsi/backups
day=$(date +%Y-%m-%d)
mkdir -p "$dir"
chmod 700 "$dir"  # the database dump holds the admin users' password hashes
runuser -u postgres -- pg_dump --format=custom tsi > "$dir/tsi-$day.dump"
tar -czf "$dir/media-$day.tar.gz" -C /srv/tsi media
find "$dir" -type f -mtime +14 -delete
