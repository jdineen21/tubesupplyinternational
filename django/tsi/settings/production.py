"""Production settings. Everything secret comes from the environment, which
manage.py and wsgi.py fill from .env (see .env.example), so nothing secret
is kept in git."""
from .base import *  # noqa: F401,F403
from .base import env, env_list

DEBUG = False
SECRET_KEY = env("SECRET_KEY")
if not SECRET_KEY:
    raise RuntimeError("SECRET_KEY is not set (see .env.example)")

ALLOWED_HOSTS = env_list(
    "ALLOWED_HOSTS",
    ",".join(
        f"www.tubesupplyinternational.{tld},tubesupplyinternational.{tld}"
        for tld in ("co.uk", "es", "fr", "pt", "ru", "sa.com")
    ),
)
CSRF_TRUSTED_ORIGINS = [f"https://{host}" for host in ALLOWED_HOSTS] + [f"http://{host}" for host in ALLOWED_HOSTS]

STORAGES["staticfiles"]["BACKEND"] = "django.contrib.staticfiles.storage.ManifestStaticFilesStorage"

# nginx terminates HTTPS and passes the scheme on
SECURE_PROXY_SSL_HEADER = ("HTTP_X_FORWARDED_PROTO", "https")
HTTPS = env("HTTPS", "1") == "1"
SESSION_COOKIE_SECURE = HTTPS
CSRF_COOKIE_SECURE = HTTPS
SESSION_COOKIE_HTTPONLY = True
SECURE_CONTENT_TYPE_NOSNIFF = True
X_FRAME_OPTIONS = "SAMEORIGIN"  # the admin's page preview is a frame
# nginx redirects to https and sends Strict-Transport-Security (deploy/nginx.conf)
SILENCED_SYSTEM_CHECKS = ["security.W004", "security.W008", "security.W019"]

ADMINS = [("Webmaster", a) for a in env_list("ADMIN_EMAILS")]

LOGGING = {
    "version": 1,
    "disable_existing_loggers": False,
    "handlers": {"console": {"class": "logging.StreamHandler"}},
    "root": {"handlers": ["console"], "level": "WARNING"},
}

try:
    from .local import *  # noqa: F401,F403
except ImportError:
    pass
