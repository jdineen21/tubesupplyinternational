from pathlib import Path

from django.core.management.base import BaseCommand

from website.importer.fetch import fetch_site
from website.importer.sites import OLD_SITES, SNAPSHOT_DIR


class Command(BaseCommand):
    help = (
        "Save a copy of the old sites' pages and pictures into website/importer/snapshot, "
        "for import_old_sites. By default from the live sites; --from ru=http://... "
        "takes a site from somewhere else (e.g. a copy of the old PHP site running locally)."
    )

    def add_arguments(self, parser):
        parser.add_argument("languages", nargs="*", help="Only these (en es fr pt ru)")
        parser.add_argument("--from", dest="sources", action="append", default=[], metavar="LANG=URL")
        parser.add_argument("--out", default=str(SNAPSHOT_DIR))

    def handle(self, languages, sources, out, **options):
        urls = {site.language: site.live_url for site in OLD_SITES if site.old_site}
        for item in sources:
            language, _, url = item.partition("=")
            urls[language] = url
        for language in languages or urls:
            self.stdout.write(f"{language}: {urls[language]}")
            pages = fetch_site(urls[language], Path(out) / language, log=self.stdout.write)
            saved = sum(1 for p in pages if p.get("file"))
            self.stdout.write(self.style.SUCCESS(f"{language}: {saved} pages saved"))
