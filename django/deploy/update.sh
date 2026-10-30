#!/bin/bash
# Bring the live site up to date with the repository:
#   sudo /srv/tsi/app/django/deploy/update.sh
set -euo pipefail
here=/srv/tsi/app/django
runuser -u tsi -- git -C /srv/tsi/app pull --ff-only
runuser -u tsi -- /srv/tsi/venv/bin/pip install --quiet -r "$here/requirements.txt"
"$here/deploy/manage.sh" migrate --noinput
"$here/deploy/manage.sh" createcachetable
"$here/deploy/manage.sh" collectstatic --noinput --verbosity 0
systemctl reload tsi
echo "Updated."
