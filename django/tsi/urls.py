from django.conf import settings
from django.urls import include, path, re_path
from django.views.generic import RedirectView
from django.views.static import serve
from wagtail import urls as wagtail_urls
from wagtail.admin import urls as wagtailadmin_urls
from wagtail.contrib.sitemaps.views import sitemap
from wagtail.documents import urls as wagtaildocs_urls

urlpatterns = [
    path("admin", RedirectView.as_view(url="/admin/")),
    path("admin/", include(wagtailadmin_urls)),
    path("documents/", include(wagtaildocs_urls)),
    path("captcha/", include("captcha.urls")),
    path("sitemap.xml", sitemap),
]

if settings.DEBUG:
    from django.conf.urls.static import static
    from django.contrib.staticfiles.urls import staticfiles_urlpatterns

    # nginx serves these in production (deploy/nginx.conf)
    urlpatterns += staticfiles_urlpatterns()
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
    urlpatterns += [re_path(r"^downloads/(?P<path>.+)$", serve, {"document_root": settings.DOWNLOADS_ROOT})]

urlpatterns += [path("", include(wagtail_urls))]
