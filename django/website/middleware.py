from django.utils import translation
from wagtail.models import Site


class SiteLanguageMiddleware:
    """Show each site in its own language: the language of its home page.

    The language is not in the address (there is no /es/ or /fr/); it comes
    from the domain, as on the old sites. The Wagtail admin picks its own
    language for each user, whichever domain it is opened on.
    """

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        language = None
        site = Site.find_for_request(request)
        if site is not None:
            language = site.root_page.locale.language_code
            translation.activate(language)
            request.LANGUAGE_CODE = translation.get_language()
        response = self.get_response(request)
        if language:
            response.headers.setdefault("Content-Language", language)
        translation.deactivate()
        return response
