import json

from django.core.management.base import BaseCommand
from wagtail.models import Locale

from website.importer.translations import page_text
from website.models import HomePage


class Command(BaseCommand):
    help = (
        "Write the text of every page of a language (English by default) as JSON, keyed by the "
        "English page's address: the form of website/importer/snapshot/<language>/translation.json, "
        "for a translator."
    )

    def add_arguments(self, parser):
        parser.add_argument("--language", default="en")

    def handle(self, language, **options):
        english_home = HomePage.objects.get(locale__language_code="en")
        locale = Locale.objects.get(language_code=language)
        english = english_home.get_descendants(inclusive=True).specific()
        home_path = english_home.url_path

        def address(page):
            return "/" + page.url_path[len(home_path):].strip("/")

        paths_by_id = {page.id: address(page) for page in english}
        pages = {}
        for page in english:
            translation = page.get_translation_or_none(locale)
            if translation is not None:
                pages[address(page)] = page_text(translation.specific, paths_by_id)
        self.stdout.write(json.dumps({"pages": pages}, ensure_ascii=False, indent=1))
