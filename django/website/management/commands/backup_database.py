import shutil
import sys
import tempfile
from pathlib import Path

from django.core.management.base import BaseCommand, CommandError
from django.db import connection


class Command(BaseCommand):
    help = (
        "Write a copy of the SQLite database to standard output, e.g. "
        "manage.py backup_database > copy.sqlite3. The copy is consistent even "
        "while the site is running and being edited."
    )

    def handle(self, **options):
        if connection.vendor != "sqlite":
            raise CommandError("The database is not SQLite (for PostgreSQL use pg_dump).")
        connection.ensure_connection()
        # The copy is made next to the database: that folder is writable by the
        # site's user and has room for it.
        folder = Path(connection.settings_dict["NAME"]).parent
        with tempfile.NamedTemporaryFile(dir=folder, suffix=".backup") as tmp:
            copy = connection.Database.connect(tmp.name)
            try:
                connection.connection.backup(copy)
            finally:
                copy.close()
            with open(tmp.name, "rb") as f:
                shutil.copyfileobj(f, sys.stdout.buffer)
