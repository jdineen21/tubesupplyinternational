from dataclasses import dataclass
from pathlib import Path


SNAPSHOT_DIR = Path(__file__).resolve().parent / "snapshot"


@dataclass
class OldSite:
    language: str
    tld: str
    country: str  # used in the old contact form's email subject
    https: bool = False  # only the .co.uk site has a working certificate
    old_site: bool = True  # False: a new site, with no old site to copy

    @property
    def domain(self):
        return f"tubesupplyinternational.{self.tld}"

    @property
    def hostname(self):
        return f"www.{self.domain}"

    @property
    def live_url(self):
        return f"{'https' if self.https else 'http'}://{self.hostname}"

    @property
    def php_dir(self):
        """The old site's page templates and the few fixed pictures the import
        needs: copied from ../sites/<domain> into the snapshot, so that the
        import works without the old PHP sites."""
        return SNAPSHOT_DIR / self.language / "old-site"


OLD_SITES = [
    OldSite("en", "co.uk", "", https=True),
    OldSite("es", "es", "Spain"),
    OldSite("fr", "fr", "France"),
    OldSite("pt", "pt", "Portugal"),
    OldSite("ru", "ru", "Russia"),
    OldSite("ar", "sa.com", "Saudi Arabia", old_site=False),
]
SITES_BY_LANGUAGE = {site.language: site for site in OLD_SITES}
