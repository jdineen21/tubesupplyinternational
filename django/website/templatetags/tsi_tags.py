from django import template
from django.contrib.staticfiles import finders
from django.templatetags.static import static
from django.utils.translation import get_language
from wagtail.models import Locale, Page, Site

register = template.Library()

# The flags in the header, in the old sites' order
LANGUAGE_FLAGS = [
    ("en", "flagUk"), ("es", "flagSpain"), ("pt", "flagPortugal"),
    ("fr", "flagFrance"), ("ru", "flagRussia"), ("ar", "flagSaudiArabia"),
]


@register.simple_tag
def lang_static(path):
    """A file from static/frontend, in this site's language if there is a copy
    for it (pictures with words on them, the cookie notice)."""
    localized = f"frontend/i18n/{get_language()}/{path}"
    if finders.find(localized):
        return static(localized)
    return static(f"frontend/{path}")


@register.simple_tag
def has_lang_static(path):
    return bool(finders.find(f"frontend/i18n/{get_language()}/{path}"))


def _home(request):
    site = Site.find_for_request(request)
    return site.root_page.specific if site else None


@register.inclusion_tag("website/includes/main_menu.html", takes_context=True)
def main_menu(context):
    request = context["request"]
    current = context.get("page")
    home = _home(request)
    items = []
    if home is not None:
        items = [home] + list(home.get_children().live().in_menu())
    return {"items": items, "current": current, "request": request}


@register.inclusion_tag("website/includes/product_menu.html", takes_context=True)
def product_menu(context):
    from website.models import ProductsPage

    request = context["request"]
    current = context.get("page")
    home = _home(request)
    items = []
    if home is not None:
        folder = ProductsPage.objects.child_of(home).live().first()
        if folder is not None:
            items = folder.get_children().live().in_menu()
    return {"items": items, "current": current, "request": request}


@register.inclusion_tag("website/includes/language_menu.html", takes_context=True)
def language_menu(context):
    """Flags linking to the other sites - to the same page in that language
    when it has been translated, otherwise to that site's home page."""
    request = context["request"]
    page = context.get("page")
    current = get_language()
    sites = {}
    for site in Site.objects.select_related("root_page__locale"):
        sites.setdefault(site.root_page.locale.language_code, site)
    links = []
    for language, css_class in LANGUAGE_FLAGS:
        site = sites.get(language)
        url = None
        if isinstance(page, Page) and language != current:
            try:
                translation = page.get_translation_or_none(Locale.objects.get(language_code=language))
            except Locale.DoesNotExist:
                translation = None
            if translation is not None and translation.live:
                url = translation.get_url(request)
                if url and not url.startswith("http"):
                    url = None  # not on a site of its own: go to that site's home page
        if url is None and site is not None:
            url = site.root_url + "/" if language != current else "/"
        if url is None:
            continue
        links.append({"url": url, "css_class": css_class, "current": language == current})
    return {"links": links}


@register.simple_tag(takes_context=True)
def page_url(context, page):
    """URL of a page chosen in the site details, in this site's language."""
    if page is None:
        return ""
    page = page.localized
    return page.get_url(context.get("request")) or ""
