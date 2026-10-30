"""Tests for the whole site, built from the saved copy of the old sites.

    python manage.py test website

The import runs once for the whole class (about half a minute); pictures go
to a temporary folder.
"""
import datetime
import shutil
import tempfile
from unittest import mock

from bs4 import BeautifulSoup
from django.core import mail
from django.core.management import CommandError, call_command
from django.test import TestCase, override_settings
from wagtail.models import Locale, Page, Site

from website.importer.load import restore_accents
from website.importer.sites import SITES_BY_LANGUAGE
from website.models import ContactPage, ContactSubmission, HomePage, ProductPage, SiteDetails

from .compare import compare_site, saved_languages

MEDIA = tempfile.mkdtemp(prefix="tsi-test-media-")


def host(language):
    return SITES_BY_LANGUAGE[language].hostname


@override_settings(MEDIA_ROOT=MEDIA)
@mock.patch("captcha.conf.settings.CAPTCHA_TEST_MODE", True)  # the code "PASSED" is accepted
class SiteTests(TestCase):
    @classmethod
    def setUpTestData(cls):
        call_command("import_old_sites", stdout=open("/dev/null", "w"))

    @classmethod
    def tearDownClass(cls):
        super().tearDownClass()
        shutil.rmtree(MEDIA, ignore_errors=True)

    def get(self, language, path, **kwargs):
        return self.client.get(path, HTTP_HOST=host(language), **kwargs)

    def soup(self, response):
        return BeautifulSoup(response.content.decode(), "html.parser")

    # -- the import

    def test_every_saved_page_matches_the_old_site(self):
        """Title, description, menus, text, pictures and links of every page,
        on every site, are as on the old sites (apart from the intended
        differences listed in compare.py)."""
        year = datetime.date.today().year
        languages = saved_languages()
        self.assertEqual(languages, ["en", "es", "fr", "pt"])
        for language in languages:
            result = compare_site(lambda path: self.get(language, path), language, year)
            self.assertEqual(result.pages, 19, language)
            self.assertEqual(
                [(d.path, d.what) for d in result.differences], [], f"{language}: {result.differences[:3]}"
            )

    def test_one_tree_of_translations(self):
        english = Page.objects.filter(locale__language_code="en", depth__gt=1)
        self.assertEqual(english.count(), 19)
        for language in ("es", "fr", "pt", "ru", "ar"):
            locale = Locale.objects.get(language_code=language)
            for page in english:
                translation = page.get_translation_or_none(locale)
                self.assertIsNotNone(translation, f"{page.url_path} has no {language} translation")
                self.assertEqual(translation.specific_class, page.specific_class)

    def test_each_domain_is_a_site_in_its_language(self):
        for language, site in SITES_BY_LANGUAGE.items():
            wagtail_site = Site.objects.get(hostname=site.hostname)
            self.assertEqual(wagtail_site.root_page.locale.language_code, language)
        self.assertTrue(Site.objects.get(hostname=host("en")).is_default_site)

    def test_russian_site_translated_from_english(self):
        """The old Russian site was offline: its pages come from
        snapshot/ru/translation.json, at the addresses its templates link to,
        and its header, left column and footer from its templates."""
        home = HomePage.objects.get(locale__language_code="ru")
        self.assertTrue(home.live)
        response = self.get("ru", "/products/u-tubes")
        self.assertContains(response, "U-образные трубы изготавливаются в соответствии со стандартом TEMA R")
        slugs = set(Page.objects.filter(locale__language_code="ru").values_list("slug", flat=True))
        self.assertTrue({"contact-us", "emergency-delivery", "industrial-sectors", "quality-systems",
                         "size-conversion-chart", "plain-stock-tubes"} <= slugs)
        self.assertEqual(self.get("ru", "/industrial-sectors").status_code, 200)
        details = SiteDetails.objects.get(site__hostname=host("ru"))
        self.assertEqual(details.products_heading, "НАШИ ПРОДУКТЫ")
        self.assertEqual(details.footer_email_label, "Электронная почта:")
        self.assertEqual(details.contact_page.slug, "contact-us")
        self.assertContains(self.get("ru", details.contact_page.url), "Ваше имя (обязательно)")

    def test_arabic_site_right_to_left(self):
        response = self.get("ar", "/")
        self.assertEqual(response.status_code, 200)
        html = self.soup(response).html
        self.assertEqual((html["lang"], html["dir"]), ("ar", "rtl"))
        self.assertContains(response, "frontend/i18n/ar/css/lang.css")
        self.assertContains(response, "أنابيب المبادلات الحرارية")
        details = SiteDetails.objects.get(site__hostname=host("ar"))
        self.assertEqual(details.products_heading, "منتجاتنا")
        self.assertEqual(details.contact_page.locale.language_code, "ar")
        contact = self.get("ar", details.contact_page.url)
        self.assertContains(contact, "اسمك (مطلوب)")
        self.assertContains(contact, "إرسال الرسالة")
        # the other sites have no direction set
        self.assertNotIn("dir", self.soup(self.get("en", "/")).html.attrs)

    def test_translations_show_the_certificate(self):
        """The BSI/UKAS certificate picture is in the sidebar of every translated site
        (the English site has it commented out)."""
        for language in ("es", "fr", "pt", "ru", "ar"):
            link = self.soup(self.get(language, "/")).select_one(".sidebarLink a")
            self.assertIsNotNone(link, language)
            self.assertEqual(link["href"], "/downloads/TSI_Certificate.pdf")
        self.assertIsNone(self.soup(self.get("en", "/")).select_one(".sidebarLink"))

    def test_translated_sites_have_no_broken_links(self):
        """Every link to a page of its own site, on every Russian and Arabic page, works."""
        for language in ("ru", "ar"):
            for page in Page.objects.filter(locale__language_code=language).live().specific():
                response = self.get(language, page.url)
                if response.status_code == 302:
                    continue  # the products folder
                self.assertEqual(response.status_code, 200, f"{language} {page.url}")
                for a in self.soup(response).select(".container_16 a[href^='/']"):
                    href = a["href"]
                    if href.startswith(("/static/", "/media/", "/downloads/", "/documents/")):
                        continue
                    self.assertIn(self.get(language, href).status_code, (200, 302), f"{language} {page.url} -> {href}")

    def test_import_twice_needs_replace(self):
        with self.assertRaises(CommandError):
            call_command("import_old_sites", stdout=open("/dev/null", "w"))

    def test_accents_put_back_in_titles(self):
        self.assertEqual(restore_accents("Tubes sans soudure 'etir'es `a froid"), "Tubes sans soudure étirés à froid")
        self.assertEqual(restore_accents("Tubos de permutaci'on"), "Tubos de permutación")
        # apostrophes of elision stay
        self.assertEqual(restore_accents("Livraison d'Urgence"), "Livraison d'Urgence")
        self.assertEqual(restore_accents("Page D'Acceil"), "Page D'Acceil")
        self.assertEqual(restore_accents("Tubes en h'elice"), "Tubes en hélice")
        page = ProductPage.objects.get(locale__language_code="fr", slug="tubes-soudes")
        self.assertEqual(page.title, "Tubes Soudés")

    def test_most_text_is_editable_as_text(self):
        """Text that the editor can hold exactly is in text blocks, not HTML."""
        text = html = 0
        for page in Page.objects.live().specific():
            for name in ("body", "header_text", "left_column", "right_column", "intro"):
                for block in getattr(page, name, None) or []:
                    text += block.block_type == "text"
                    html += block.block_type == "html"
        self.assertGreater(text, html * 2)

    # -- serving

    def test_old_addresses_with_and_without_slash(self):
        self.assertEqual(self.get("en", "/products/u-tubes").status_code, 200)
        self.assertEqual(self.get("en", "/products/u-tubes/").status_code, 200)
        self.assertEqual(self.get("es", "/products/tubos-u").status_code, 200)
        # each domain has only its own language's addresses
        self.assertEqual(self.get("es", "/products/u-tubes").status_code, 404)

    def test_products_folder_goes_to_home_page(self):
        response = self.get("fr", "/products")
        self.assertEqual(response.status_code, 302)
        self.assertEqual(response["Location"], "/")

    def test_page_in_its_language(self):
        response = self.get("fr", "/")
        self.assertEqual(response["Content-Language"], "fr")
        self.assertEqual(self.soup(response).html["lang"], "fr")
        self.assertContains(response, "Continuer")  # cookie notice button

    def test_flags_link_to_same_page_in_other_language(self):
        soup = self.soup(self.get("en", "/products/u-tubes"))
        flags = {a["class"][0]: a["href"] for a in soup.select(".languageMenu a")}
        self.assertEqual(flags["flagSpain"], "http://www.tubesupplyinternational.es/products/tubos-u")
        self.assertEqual(flags["flagFrance"], "http://www.tubesupplyinternational.fr/products/tubes-en-u")
        self.assertEqual(flags["flagRussia"], "http://www.tubesupplyinternational.ru/products/u-tubes")
        self.assertEqual(flags["flagSaudiArabia"], "http://www.tubesupplyinternational.sa.com/products/u-tubes")
        self.assertIn("current", soup.select_one(".flagUk")["class"])

    def test_404_in_site_language(self):
        response = self.get("fr", "/nothing-here")
        self.assertEqual(response.status_code, 404)
        self.assertContains(response, "404! Erreur", status_code=404)
        response = self.get("es", "/nothing-here")
        self.assertContains(response, "404! Page not found.", status_code=404)  # as on the old .es site
        self.assertContains(response, "Leer más", status_code=404)  # the site's own footer

    def test_sitemap_per_domain(self):
        response = self.get("es", "/sitemap.xml")
        self.assertEqual(response.status_code, 200)
        content = response.content.decode()
        self.assertIn("http://www.tubesupplyinternational.es/products/tubos-u", content)
        self.assertNotIn("tubesupplyinternational.co.uk", content)

    def test_site_details_edit_shows_on_pages(self):
        details = SiteDetails.objects.get(site__hostname=host("pt"))
        details.telephone = "+351 000 000"
        details.save()
        self.assertContains(self.get("pt", "/"), "+351 000 000")
        self.assertNotContains(self.get("en", "/"), "+351 000 000")

    def test_edited_page_is_shown(self):
        page = ProductPage.objects.filter(locale__language_code="en").first()
        page.body = [("text", "<p>New <strong>text</strong></p>")]
        page.save_revision().publish()
        self.assertContains(self.get("en", page.url), "<p>New <strong>text</strong></p>", html=True)
        # the Spanish page is a separate translation and stays as it was
        spanish = page.get_translation(Locale.objects.get(language_code="es"))
        self.assertNotContains(self.get("es", spanish.url), "New <strong>text</strong>")

    # -- contact form

    def contact(self, language, **data):
        page = ContactPage.objects.get(locale__language_code=language)
        form = {"name": "Ann", "contact": "0123", "email": "ann@example.com", "job": "Buyer", "company": "ACME",
                "country": "UK", "message": "Need tubes", "captcha_0": "x", "captcha_1": "PASSED"}
        form.update(data)
        return self.client.post(page.url, form, HTTP_HOST=host(language))

    def test_contact_form_labels_in_site_language(self):
        page = ContactPage.objects.get(locale__language_code="fr")
        response = self.get("fr", page.url)
        for label in ("Votre nom (obligatoire)", "Description d’Emploi", "Envoyez Votre Message"):
            self.assertContains(response, label)
        self.assertContains(response, 'name="captcha_1"')
        self.assertContains(response, 'id="siimage"')

    def test_contact_form_sends_email(self):
        response = self.contact("es")
        self.assertContains(response, "Thank you for submitting your message.")
        self.assertEqual(len(mail.outbox), 1)
        message = mail.outbox[0]
        self.assertEqual(message.subject, "Contact form message from TSI Website Spain.")
        self.assertEqual(message.to, [SiteDetails.objects.get(site__hostname=host("es")).email])
        self.assertEqual(message.reply_to, ["ann@example.com"])
        self.assertIn("Name: Ann\n\nContact: 0123\n\nEmail: ann@example.com\n\nJob Description: Buyer", message.body)
        saved = ContactSubmission.objects.get()
        self.assertEqual((saved.name, saved.site), ("Ann", host("es")))

    @override_settings(EMAIL_BACKEND="django.core.mail.backends.smtp.EmailBackend", EMAIL_HOST="127.0.0.1", EMAIL_PORT=1)
    def test_contact_form_when_mail_fails(self):
        """The visitor is thanked and the message is kept even if the mail server is down."""
        with self.assertLogs("website.models", "ERROR"):
            response = self.contact("en")
        self.assertContains(response, "Thank you for submitting your message.")
        self.assertEqual(ContactSubmission.objects.count(), 1)

    def test_contact_form_wrong_code(self):
        response = self.contact("en", captcha_1="WRONG", name="<b>x</b>")
        self.assertContains(response, "Code incorrect. Please enter the code again.")
        self.assertContains(response, "&lt;b&gt;x&lt;/b&gt;")  # what was typed is kept, escaped
        self.assertEqual(len(mail.outbox), 0)

    def test_contact_form_required_fields(self):
        response = self.contact("en", name="", contact="")
        self.assertEqual(len(mail.outbox), 0)
        self.assertEqual(response.context["form"].errors.keys(), {"name", "contact"})
