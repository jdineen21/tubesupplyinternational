"""
Settings shared by development and production.

One Wagtail installation serves all five Tube Supply International sites.
Each domain is a Wagtail Site whose home page is in that site's language;
the pages are translations of each other (see website/models.py).
"""
import os
from pathlib import Path

PROJECT_DIR = Path(__file__).resolve().parent.parent
BASE_DIR = PROJECT_DIR.parent


def env(name, default=None):
    return os.environ.get(name, default)


def env_list(name, default=''):
    return [item.strip() for item in env(name, default).split(',') if item.strip()]


INSTALLED_APPS = [
    "website",
    "wagtail.contrib.settings",
    "wagtail.contrib.simple_translation",
    "wagtail.contrib.redirects",
    "wagtail.contrib.sitemaps",
    "wagtail.sites",
    "wagtail.users",
    "wagtail.snippets",
    "wagtail.documents",
    "wagtail.images",
    "wagtail.search",
    "wagtail.admin",
    "wagtail.locales",
    "wagtail",
    "modelcluster",
    "taggit",
    "django_filters",
    "captcha",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    "django.contrib.sitemaps",
]

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "website.middleware.SiteLanguageMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
    "wagtail.contrib.redirects.middleware.RedirectMiddleware",
]

ROOT_URLCONF = "tsi.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.debug",
                "django.template.context_processors.request",
                "django.template.context_processors.i18n",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
                "wagtail.contrib.settings.context_processors.settings",
            ],
        },
    },
]

WSGI_APPLICATION = "tsi.wsgi.application"

# PostgreSQL in production (DATABASE_NAME etc. from the environment);
# SQLite when DATABASE_NAME is not set, for trying the site out locally.
if env("DATABASE_NAME"):
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.postgresql",
            "NAME": env("DATABASE_NAME"),
            "USER": env("DATABASE_USER", ""),
            "PASSWORD": env("DATABASE_PASSWORD", ""),
            "HOST": env("DATABASE_HOST", ""),
            "PORT": env("DATABASE_PORT", ""),
            "CONN_MAX_AGE": 60,
        }
    }
    INSTALLED_APPS.append("django.contrib.postgres")  # Wagtail's search uses PostgreSQL's
else:
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.sqlite3",
            "NAME": BASE_DIR / "db.sqlite3",
        }
    }

AUTH_PASSWORD_VALIDATORS = [
    {"NAME": "django.contrib.auth.password_validation.UserAttributeSimilarityValidator"},
    {"NAME": "django.contrib.auth.password_validation.MinimumLengthValidator"},
    {"NAME": "django.contrib.auth.password_validation.CommonPasswordValidator"},
    {"NAME": "django.contrib.auth.password_validation.NumericPasswordValidator"},
]

# Languages: one per site. The language of a request is the language of
# the site (domain) it is for - see website/middleware.py.
LANGUAGE_CODE = "en"
LANGUAGES = [
    ("en", "English"),
    ("es", "Spanish"),
    ("fr", "French"),
    ("pt", "Portuguese"),
    ("ru", "Russian"),
    ("ar", "Arabic"),
]
WAGTAIL_I18N_ENABLED = True
WAGTAIL_CONTENT_LANGUAGES = LANGUAGES
LOCALE_PATHS = [BASE_DIR / "website" / "locale"]
TIME_ZONE = "Europe/London"
USE_I18N = True
USE_TZ = True

# A cache shared by every server process and by management commands: Wagtail
# keeps which pages belong to which domain in it, and a cache per process
# would go stale when sites or home pages change. Run "manage.py
# createcachetable" once when setting up.
CACHES = {
    "default": {
        "BACKEND": "django.core.cache.backends.db.DatabaseCache",
        "LOCATION": "tsi_cache",
    }
}

STATICFILES_FINDERS = [
    "django.contrib.staticfiles.finders.FileSystemFinder",
    "django.contrib.staticfiles.finders.AppDirectoriesFinder",
]
STATIC_ROOT = Path(env("STATIC_ROOT", BASE_DIR / "collected-static"))
STATIC_URL = "/static/"

MEDIA_ROOT = Path(env("MEDIA_ROOT", BASE_DIR / "media"))
MEDIA_URL = "/media/"

# The PDFs the old sites linked to as /downloads/<file> (served from here
# by nginx in production, by Django when DEBUG is on).
DOWNLOADS_ROOT = Path(env("DOWNLOADS_ROOT", BASE_DIR / "downloads"))

STORAGES = {
    "default": {"BACKEND": "django.core.files.storage.FileSystemStorage"},
    "staticfiles": {"BACKEND": "django.contrib.staticfiles.storage.StaticFilesStorage"},
}

DATA_UPLOAD_MAX_NUMBER_FIELDS = 10_000
DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

# Contact form email
EMAIL_HOST = env("EMAIL_HOST", "localhost")
EMAIL_PORT = int(env("EMAIL_PORT", "25"))
EMAIL_HOST_USER = env("EMAIL_HOST_USER", "")
EMAIL_HOST_PASSWORD = env("EMAIL_HOST_PASSWORD", "")
EMAIL_USE_TLS = env("EMAIL_USE_TLS", "") == "1"
# The old .co.uk site sent its contact form mail with this envelope sender.
DEFAULT_FROM_EMAIL = env("DEFAULT_FROM_EMAIL", "tubesales@heatextube.co.uk")
SERVER_EMAIL = DEFAULT_FROM_EMAIL

# Captcha on the contact form: small, like the old Securimage one
CAPTCHA_IMAGE_SIZE = (175, 45)
CAPTCHA_FONT_SIZE = 30
CAPTCHA_LENGTH = 6
CAPTCHA_FOREGROUND_COLOR = "#055099"
CAPTCHA_LETTER_ROTATION = (-25, 25)
CAPTCHA_TIMEOUT = 30  # minutes

# Wagtail
WAGTAIL_SITE_NAME = "Tube Supply International"
WAGTAILADMIN_BASE_URL = env("WAGTAILADMIN_BASE_URL", "https://www.tubesupplyinternational.co.uk")
WAGTAILSEARCH_BACKENDS = {"default": {"BACKEND": "wagtail.search.backends.database"}}
WAGTAILDOCS_EXTENSIONS = ["csv", "doc", "docx", "jpg", "odt", "pdf", "png", "pptx", "rtf", "txt", "xls", "xlsx", "zip"]
WAGTAILDOCS_MAX_UPLOAD_SIZE = 20 * 1024 * 1024
WAGTAILIMAGES_EXTENSIONS = ["gif", "jpg", "jpeg", "png", "webp"]
WAGTAIL_ALLOW_UNICODE_SLUGS = False
# Page addresses without a slash at the end (/products/u-tubes), as on the old sites
WAGTAIL_APPEND_SLASH = False
