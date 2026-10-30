#!/bin/bash
# Run a manage.py command on the live site, with its settings, as its user:
#   sudo /srv/tsi/app/django/deploy/manage.sh migrate
set -euo pipefail
set -a
. /etc/tsi/tsi.env
set +a
cd /srv/tsi/app/django
exec runuser -u tsi -- /srv/tsi/venv/bin/python manage.py "$@"
