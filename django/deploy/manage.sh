#!/bin/bash
# Run a manage.py command on the live site as its user (manage.py reads the
# settings from .env):
#   sudo /srv/tsi/app/django/deploy/manage.sh migrate
set -euo pipefail
cd /srv/tsi/app/django
exec runuser -u tsi -- /srv/tsi/venv/bin/python manage.py "$@"
