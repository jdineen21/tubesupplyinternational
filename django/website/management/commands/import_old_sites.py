from django.core.management.base import BaseCommand, CommandError
from django.db import transaction
from django.test.utils import override_settings
from wagtail.contrib.redirects.models import Redirect
from wagtail.models import Page, Site

from website.importer.load import Importer
from website.importer.sites import OLD_SITES
from website.models import SiteDetails


class Command(BaseCommand):
    help = (
        "Build the site from the saved copy of the old sites (website/importer/snapshot, "
        "made by fetch_old_sites): all pages in all languages, the pictures, and each "
        "domain's Site details."
    )

    def add_arguments(self, parser):
        parser.add_argument("--replace", action="store_true",
                            help="Delete all pages and sites first (everything edited since is lost)")
        parser.add_argument("--hostname", action="append", default=[], metavar="LANG=HOST",
                            help="Use another domain for a language, e.g. for a test server")
        parser.add_argument("--https", action="store_true",
                            help="The sites are served over https (links between the domains use https)")
        parser.add_argument("--publish-untranslated", action="store_true",
                            help="Publish pages that have no translation (the Russian site) "
                                 "with the English text, instead of leaving them as drafts")

    def handle(self, replace, hostname, publish_untranslated, https, **options):
        hostnames = dict(item.split("=", 1) for item in hostname)
        unknown = set(hostnames) - {s.language for s in OLD_SITES}
        if unknown:
            raise CommandError(f"Unknown language: {', '.join(sorted(unknown))}")

        root = Page.get_first_root_node()
        existing = root.get_children()
        default_only = (existing.count() == 1 and existing.first().specific_class is Page)
        if existing.exists() and not default_only and not replace:
            raise CommandError("The site already has pages. Use --replace to delete them and import again.")
        # Wagtail would note each translated page's change of address as a redirect
        with override_settings(WAGTAILREDIRECTS_AUTO_CREATE=False), transaction.atomic():
            Redirect.objects.all().delete()
            SiteDetails.objects.all().delete()
            Site.objects.all().delete()
            # deepest first: deleting a page with sub-pages in one go can leave some behind
            for page in Page.objects.filter(depth__gt=1).order_by("-depth"):
                page.delete()
            report = Importer(hostnames, publish_untranslated, port=443 if https else 80,
                              log=self.stdout.write).run()

        self.stdout.write(self.style.SUCCESS("Imported."))
        for site in Site.objects.select_related("root_page__locale").order_by("id"):
            pages = site.root_page.get_descendants(inclusive=True)
            self.stdout.write(f"  {site.hostname}: {pages.live().count()} pages published, "
                              f"{pages.not_live().count()} drafts")
        self.stdout.write(f"Editable areas: {report.counts.get('text', 0)} as text, "
                          f"{report.counts.get('html (editor would change it)', 0) + report.counts.get('html', 0) - report.counts.get('html (editor would change it)', 0)} as HTML")
        if report.titles:
            self.stdout.write("Titles with their accents put back:")
            for line in report.titles:
                self.stdout.write("  " + line)
        for language, count in report.translated.items():
            self.stdout.write(f"[{language}] {count} pages given their text from snapshot/{language}/translation.json")
        for language, paths in report.untranslated.items():
            self.stdout.write(f"[{language}] {len(paths)} pages have no {language} text and were made as "
                              f"{'published' if publish_untranslated else 'draft'} copies of the English pages")
        for note in report.notes:
            self.stdout.write(note)
