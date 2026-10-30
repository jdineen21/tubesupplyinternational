from .base import *  # noqa: F401,F403

DEBUG = True
SECRET_KEY = "dev-only-not-secret"
ALLOWED_HOSTS = ["*"]
EMAIL_BACKEND = "django.core.mail.backends.console.EmailBackend"

try:
    from .local import *  # noqa: F401,F403
except ImportError:
    pass
