from django.apps import AppConfig


class WebsiteConfig(AppConfig):
    name = "website"
    verbose_name = "Tube Supply International"

    def ready(self):
        from wagtail.models import Page, Site

        original = Page._get_relevant_site_root_paths

        def own_site_root_paths(page, cache_object=None):
            """Each language has its own domain here, so a page belongs only to
            the site whose home page it is under.

            Wagtail's default is that a site also covers the translations of its
            home page (for sites with /es/, /fr/ addresses); with one domain per
            language that made links to the other languages' pages - the flags,
            "View live" in the admin - point at the wrong domain.
            """
            paths = original(page, cache_object)
            holder = cache_object if cache_object is not None else page
            try:
                roots = holder._tsi_site_roots
            except AttributeError:
                roots = holder._tsi_site_roots = dict(Site.objects.values_list("id", "root_page__url_path"))
            own = tuple(p for p in paths if roots.get(p.site_id) == p.root_path)
            return own or paths

        Page._get_relevant_site_root_paths = own_site_root_paths
