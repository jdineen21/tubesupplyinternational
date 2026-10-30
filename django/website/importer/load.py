"""Build the Wagtail site from the saved copy of the old sites.

For each old site (language):
  * each saved page is matched against the old site's PHP templates
    (atlas.py) to find its page type and the content of each editable area;
  * the pages of the different languages are matched up through the menus
    (the old CMS gave a page the same menu id on every site) and the fixed
    links in the templates (contact button, stock tube box, footer);
  * the English pages are created, then each language's pages as their
    translations, with that language's text, title and address;
  * the text around the pages (header, left column, footer) is read from the
    old site's templates into that site's Site details.

The Russian site could not be copied (it is offline), so its pages are made
as drafts copied from English, ready to be translated; its header, left
column and footer come from its templates, which are in this repository.
"""
import hashlib
import io
import html
import json
import re
import urllib.parse
from dataclasses import dataclass, field

from bs4 import BeautifulSoup, Comment, NavigableString, Tag
from django.core.files.images import ImageFile
from django.db import transaction
from wagtail.admin.rich_text.converters.contentstate import ContentstateConverter
from wagtail.images.models import Image
from wagtail.models import Collection, Locale, Page, Site
from wagtail.rich_text import RichText

from website import models
from website.blocks import RICH_TEXT_FEATURES

from . import atlas, translations
from .sites import OLD_SITES, SITES_BY_LANGUAGE, SNAPSHOT_DIR

PAGE_TYPES = {
    "home": models.HomePage,
    "page": models.StandardPage,
    "product": models.ProductPage,
    "page-slider": models.SliderPage,
    "page-slider-body-image": models.SliderPage,
    "page-two-column": models.TwoColumnPage,
    "two-column-lower-image": models.StockPage,
    "page-image-column": models.ImageColumnPage,
    "contact": models.ContactPage,
    "size-conversion-chart": models.SizeChartPage,
}
PRODUCTS_FOLDER_TITLES = {"en": "Products", "es": "Productos", "fr": "Produits", "pt": "Produtos", "ru": "Продукция",
                          "ar": "المنتجات"}


# ---------------------------------------------------------------- old pages

@dataclass
class OldPage:
    language: str
    path: str
    template: str
    regions: dict
    html: str
    title: str  # the page title (browser tab)
    description: str
    keywords: str
    key: str = ""
    nav_title: str = ""


@dataclass
class OldSiteContent:
    language: str
    pages: dict = field(default_factory=dict)  # key -> OldPage
    paths: dict = field(default_factory=dict)  # path -> key
    main_menu: list = field(default_factory=list)  # keys in menu order
    product_menu: list = field(default_factory=list)
    nav_titles: dict = field(default_factory=dict)  # key -> menu name
    skipped: list = field(default_factory=list)


def norm_path(href):
    path = urllib.parse.urlparse(href).path or "/"
    return path.rstrip("/") or "/"


def php_templates(old_site):
    folder = old_site.php_dir / "frontend" / "template"
    return {
        f.stem: f.read_text(encoding="utf-8")
        for f in sorted(folder.glob("*.php"))
        if f.stem not in ("404", "offline")
    }


def meta(soup, name):
    tag = soup.find("meta", attrs={"name": name})
    return html.unescape(tag.get("content", "")).strip() if tag else ""


def read_old_site(old_site, report):
    """The saved copy of one old site, matched against its templates."""
    folder = SNAPSHOT_DIR / old_site.language
    content = OldSiteContent(old_site.language)
    if not (folder / "site.json").exists():
        return content
    info = json.loads((folder / "site.json").read_text(encoding="utf-8"))
    templates = php_templates(old_site)
    for entry in info["pages"]:
        if not entry.get("file"):
            continue
        page_html = (folder / "pages" / entry["file"]).read_text(encoding="utf-8")
        found = atlas.best_match(templates, page_html)
        soup = BeautifulSoup(page_html, "html.parser")
        if not found:
            report.note(old_site.language, f"{entry['path']}: not a page of any template (on the old site it shows "
                                           f"'{soup.title.string.strip() if soup.title else ''}' with no content), skipped")
            content.skipped.append(entry["path"])
            continue
        template, regions = found
        page = OldPage(
            language=old_site.language, path=entry["path"], template=template, regions=regions, html=page_html,
            title=html.unescape(soup.title.get_text()).strip() if soup.title else "",
            description=meta(soup, "description"), keywords=meta(soup, "keywords"),
        )
        content.pages[page.path] = page
        if page.path == "/":
            identify_pages(content, soup)

    # key the pages by what they are, so that languages can be matched up
    by_key = {}
    for path, page in content.pages.items():
        page.key = content.paths.get(path, "")
        if not page.key:
            report.note(old_site.language, f"{path}: not linked from the menus or the page frame, imported anyway")
            page.key = "path:" + path
        page.nav_title = content.nav_titles.get(page.key, "")
        restored = restore_accents(page.nav_title)
        if restored != page.nav_title:
            report.titles.append(f"[{old_site.language}] {page.nav_title}  ->  {restored}")
            page.nav_title = restored
        by_key[page.key] = page
    content.pages = by_key
    return content


def identify_pages(content, soup):
    """Key each page by its menu id or by the fixed link that leads to it."""
    content.paths["/"] = "nav-1"
    for menu, keys in (("nav", content.main_menu), ("div.sidebar", content.product_menu)):
        for li in soup.select(f"{menu} li[id^=navItem-]"):
            key = "nav-" + li["id"].split("-", 1)[1]
            content.paths[norm_path(li.a["href"])] = key
            content.nav_titles[key] = html.unescape(li.a.get_text()).strip()
            keys.append(key)
    for selector, key in ((".contactButton a", "contact"), (".emergencyButton a", "emergency"),
                          (".subProduct a", "stock")):
        link = soup.select_one(selector)
        if link is not None:
            content.paths.setdefault(norm_path(link["href"]), key)


# ------------------------------------------------------------- page frame

PHP_VALUE = re.compile(r"<\?php\s+echo\s+\$this->content\('(Telephone Number|Email Address)'[^?]*\?>")
PHP_ANY = re.compile(r"<\?php.*?\?>", re.S)


def template_soup(old_site, include):
    source = (old_site.php_dir / "frontend" / "template" / "includes" / f"{include}.php").read_text(encoding="utf-8")
    source = PHP_VALUE.sub(lambda m: "{{telephone}}" if m.group(1).startswith("Tel") else "{{email}}", source)
    source = PHP_ANY.sub("", source)
    return BeautifulSoup(source, "html.parser")


def squash(text):
    """Collapse runs of spaces/tabs and indentation, keep line breaks."""
    lines = [re.sub(r"[ \t\r\f\v]+", " ", line).strip() for line in text.split("\n")]
    return "\n".join(line for line in lines if line)


def text_with_breaks(node):
    out = []
    for item in node.descendants:
        if isinstance(item, Comment):
            continue
        if isinstance(item, NavigableString):
            out.append(str(item).replace("\n", " "))
        elif item.name == "br":
            out.append("\n")
    return squash(html.unescape("".join(out)))


def inner_html(node):
    if node is None:
        return ""
    parts = [str(c) for c in node.contents if not isinstance(c, Comment)]
    return squash_html("".join(parts))


def squash_html(markup):
    markup = re.sub(r"[ \t]+", " ", markup)
    return "\n".join(line.strip() for line in markup.strip().split("\n") if line.strip())


def page_frame(old_site, old_content):
    """Site details for one site, from its header, sidebar and footer templates."""
    d = {}
    if not old_site.php_dir.exists():
        return d, {}  # a new site: its details come from its translation.json
    header = template_soup(old_site, "header")
    tagline = header.select_one(".tagline p")
    if tagline is not None:
        span = tagline.find("span")
        first = []
        for child in tagline.children:
            if isinstance(child, Tag) and child.name in ("br", "span"):
                break
            first.append(str(child))
        d["tagline"] = squash(html.unescape(BeautifulSoup("".join(first), "html.parser").get_text()))
        d["tagline_highlight"] = text_with_breaks(span) if span else ""
    label = header.select_one(".contactDetails p span")
    d["telephone_label"] = text_with_breaks(label) if label else ""
    links = {
        "contact_page": header.select_one(".contactButton a"),
        "emergency_page": header.select_one(".emergencyButton a"),
    }

    sidebar = template_soup(old_site, "sidebar")
    d["products_heading"] = text_with_breaks(sidebar.find("h4"))
    d["stock_box_heading"] = text_with_breaks(sidebar.select_one(".subProductText h4"))
    d["stock_box_text"] = inner_html(sidebar.select_one(".subProductText p"))
    d["certificate_link"] = inner_html(sidebar.select_one(".sidebarLink p"))
    d["sidebar_text"] = inner_html(sidebar.select_one(".sidebar_paragraph"))
    links["stock_box_page"] = sidebar.select_one(".subProduct a")

    footer = template_soup(old_site, "footer")
    boxes = footer.select(".footerBox")
    for box, name in zip(boxes, ("quality", "chart", "sectors", "contact")):
        head = box.select_one(".footerBoxHeader")
        d[f"footer_{name}_heading"] = text_with_breaks(head.find("h4"))
        d[f"footer_{name}_subheading"] = text_with_breaks(head.find("span"))
        if name != "contact":
            links[f"footer_{name}_page"] = head.find("a")
            body = [c for c in head.next_siblings if not isinstance(c, Comment)]
            # the last paragraph is the "Read more" link
            last = [c for c in body if isinstance(c, Tag)][-1]
            d.setdefault("read_more", text_with_breaks(last))
            d[f"footer_{name}_text"] = squash_html("".join(str(c) for c in body if c is not last))
        else:
            paragraphs = [p for p in box.find_all("p")]
            d["footer_address"] = squash_html(str(paragraphs[0]))
            for p in paragraphs[1:]:
                text = p.get_text()
                if "{{telephone}}" in text:
                    d["footer_telephone_label"] = text_with_breaks(p.find("span"))
                elif "{{email}}" not in text and text.strip():
                    d["footer_email_label"] = text_with_breaks(p)

    head = (old_site.php_dir / "frontend" / "template" / "includes" / "htmlhead.php").read_text(encoding="utf-8")
    google = re.search(r'name="google-site-verification" content="([^"]+)"', head)
    bing = re.search(r'name="msvalidate.01" content="([^"]+)"', head)
    d["google_site_verification"] = google.group(1) if google else ""
    d["bing_site_verification"] = bing.group(1) if bing else ""

    # values the old CMS filled in: from the live pages
    home = old_content.pages.get("nav-1")
    if home is not None:
        soup = BeautifulSoup(home.html, "html.parser")
        details = soup.select_one(".contactDetails p")
        if details is not None:
            span = details.find("span")
            d["telephone"] = details.get_text().replace(span.get_text() if span else "", "", 1).strip()
        mail = soup.select_one("footer a[href^=mailto]")
        d["email"] = mail["href"][len("mailto:"):].strip() if mail else ""
        ga = re.search(r'_setAccount","([^"]+)"', home.html)
        d["analytics_id"] = ga.group(1) if ga else ""
    return d, {name: norm_path(a["href"]) for name, a in links.items() if a is not None and a.get("href")}


# --------------------------------------------------------------- content

ELISIONS = {"l", "d", "j", "n", "s", "c", "m", "t", "qu", "lorsqu", "puisqu", "jusqu"}


def restore_accents(text):
    """Titles on the .es and .fr sites were typed with the accent before the
    letter ("'etir'es `a froid"). Put the accents back ("étirés à froid"),
    leaving apostrophes alone ("d'urgence", "l'entreprise")."""
    marks = {"'": dict(zip("aeiouAEIOU", "áéíóúÁÉÍÓÚ")), "`": dict(zip("aeiouAEIOU", "àèìòùÀÈÌÒÙ"))}

    def fix(m):
        before, mark, letter = m.group(1), m.group(2), m.group(3)
        word_before = re.search(r"(\w*)$", before).group(1)
        if mark == "'" and word_before.lower() in ELISIONS:
            return m.group(0)  # elision
        return before + marks[mark].get(letter, mark + letter)

    return re.sub(r"(^|.*?)(['`])([aeiouAEIOU])", fix, text) if re.search(r"['`][aeiouAEIOU]", text) else text


SAFE_TAGS = {"p", "br", "strong", "b", "em", "i", "ul", "ol", "li", "h1", "h2", "h3", "h4", "h5", "a", "span"}
TEXT_BLOCKS = {"p", "ul", "ol", "h1", "h2", "h3", "h4", "h5"}


def rich_text_safe(node):
    """Whether the editor can hold this element exactly as it is."""
    if isinstance(node, Comment):
        return False
    if isinstance(node, NavigableString):
        return True
    if node.name not in SAFE_TAGS:
        return False
    attrs = set(node.attrs)
    if node.name == "a":
        ok = attrs == {"href"} and not node["href"].lower().startswith("javascript")
    elif node.name == "span":
        ok = attrs <= {"lang", "xml:lang"}  # left by pasting from Word; makes no difference
    else:
        ok = not attrs
    return ok and all(rich_text_safe(c) for c in node.children)


def shape(markup):
    """Text and element structure, to check a conversion lost nothing."""
    markup = re.sub(r"</(strong|em|b|i)>(\s*)<\1>", r"\2", markup)
    soup = BeautifulSoup(markup, "html.parser")
    for span in soup.find_all("span"):
        span.unwrap()
    tags = [{"b": "strong", "i": "em"}.get(t.name, t.name) for t in soup.find_all(True)]
    text = re.sub(r"\s+", " ", soup.get_text(" ").replace("\xa0", " ")).strip()
    return tags, text


class ContentConverter:
    def __init__(self, importer, language):
        self.importer = importer
        self.language = language
        self.rich_text = ContentstateConverter(RICH_TEXT_FEATURES)

    def stream(self, markup):
        """An editable area as ContentBlock data: text blocks where the editor
        can hold the content exactly, HTML blocks where it cannot."""
        if not markup or not markup.strip():
            return []
        soup = BeautifulSoup(markup, "html.parser")
        blocks, text, raw = [], [], []

        def flush_text():
            if text:
                blocks.extend(self.text_blocks(text))
                text.clear()

        def flush_raw():
            if raw:
                blocks.append(("html", self.raw_html("".join(raw).strip())))
                raw.clear()

        for node in soup.contents:
            if isinstance(node, NavigableString) and not isinstance(node, Comment) and not node.strip():
                (raw if raw else text).append(str(node))
                continue
            if isinstance(node, Tag) and node.name in TEXT_BLOCKS and rich_text_safe(node):
                flush_raw()
                text.append(str(node))
            else:
                flush_text()
                raw.append(str(node))
        flush_text()
        flush_raw()
        return blocks

    def convert(self, markup):
        """Editor (database) HTML for markup, or None if the editor would change it."""
        source = self.to_database_html(markup)
        converted = self.rich_text.to_database_format(self.rich_text.from_database_format(source))
        return converted if shape(converted) == shape(markup) else None

    def text_blocks(self, nodes):
        """Text blocks for a run of paragraphs, lists and headings; any of
        them the editor would change goes in an HTML block between them."""
        converted = self.convert("".join(nodes))
        if converted is not None:
            self.importer.report.count("text")
            return [("text", RichText(converted))]
        blocks, run = [], []
        for node in nodes + [None]:
            single = self.convert(node) if node is not None and node.strip() else ""
            if node is not None and (single is not None or not node.strip()):
                run.append(node)
                continue
            if any(n.strip() for n in run):
                joined = self.convert("".join(run))
                parts = [joined] if joined is not None else [self.convert(n) for n in run if n.strip()]
                for part in parts:
                    self.importer.report.count("text")
                    blocks.append(("text", RichText(part)))
            run = []
            if node is not None:
                blocks.append(("html", self.raw_html(node.strip())))
        return blocks

    def to_database_html(self, markup):
        # <strong>a</strong><strong> b</strong> is one piece of bold text to the editor
        markup = re.sub(r"</(strong|em|b|i)>(\s*)<\1>", r"\2", markup)
        soup = BeautifulSoup(markup, "html.parser")
        for span in soup.find_all("span"):
            span.unwrap()
        for old, new in (("b", "strong"), ("i", "em")):
            for tag in soup.find_all(old):
                tag.name = new
        for a in soup.find_all("a"):
            page = self.importer.page_for_href(self.language, a["href"])
            if page is not None:
                a.attrs = {"linktype": "page", "id": str(page.id)}
        return str(soup)

    def raw_html(self, markup):
        self.importer.report.count("html")
        markup = re.sub(r'(src|href)="/frontend/', r'\1="/static/frontend/', markup)
        return markup


# ---------------------------------------------------------------- import

class Report:
    def __init__(self):
        self.notes = []
        self.counts = {}
        self.titles = []
        self.untranslated = {}  # language -> pages made as drafts copied from English
        self.translated = {}  # language -> pages given text from translation.json

    def note(self, language, text):
        self.notes.append(f"[{language}] {text}")

    def count(self, what):
        self.counts[what] = self.counts.get(what, 0) + 1


class Importer:
    def __init__(self, hostnames=None, publish_untranslated=False, port=80, log=print):
        self.report = Report()
        self.hostnames = hostnames or {}
        self.publish_untranslated = publish_untranslated
        self.port = port
        self.log = log
        self.images = {}
        self.pages = {}  # (language, key) -> page
        self.paths = {}  # (language, path) -> page

    # -- helpers

    def collection(self):
        root = Collection.get_first_root_node()
        existing = root.get_children().filter(name="Old sites").first()
        return existing or root.add_child(name="Old sites")

    def image(self, data, name, title):
        digest = hashlib.sha1(data).hexdigest()
        if digest in self.images:
            return self.images[digest]
        image = Image.objects.filter(file_hash=digest).first()
        if image is None:
            image = Image(title=title[:255] or name, collection=self._collection,
                          file=ImageFile(io.BytesIO(data), name=name))
            image._set_image_file_metadata()
            image.save()
        self.images[digest] = image
        return image

    def cms_image(self, language, img_tag):
        """Image from an <img> tag of an old page: (image, alt) or (None, '')."""
        attrs = atlas.img_attrs(img_tag) if img_tag else {}
        src = urllib.parse.urlparse(attrs.get("src", "")).path
        if not src:
            return None, ""
        if src.startswith("/media/"):
            path = SNAPSHOT_DIR / language / "media" / src.lstrip("/").replace("/", "__")
        else:
            path = SITES_BY_LANGUAGE[language].php_dir / src.lstrip("/")
        if not path.exists():
            self.report.note(language, f"picture {src} was not saved, left out")
            return None, ""
        alt = attrs.get("alt", "").strip()
        return self.image(path.read_bytes(), path.name.split("__")[-1], alt or path.stem), alt

    def static_image(self, language, path, title):
        file = SITES_BY_LANGUAGE[language].php_dir / path
        return self.image(file.read_bytes(), file.name, title)

    def page_for_href(self, language, href):
        url = urllib.parse.urlparse(href)
        if url.scheme not in ("", "http", "https"):
            return None
        if url.netloc and url.netloc.replace("www.", "") != SITES_BY_LANGUAGE[language].domain:
            return None
        return self.paths.get((language, norm_path(href)))

    # -- the import

    @transaction.atomic
    def run(self):
        self._collection = self.collection()
        sites = {s.language: read_old_site(s, self.report) for s in OLD_SITES}
        english = sites["en"]
        if not english.pages:
            raise RuntimeError("No saved copy of the English site: run fetch_old_sites first.")
        locales = {s.language: Locale.objects.get_or_create(language_code=s.language)[0] for s in OLD_SITES}

        # pages in the order of the menus, then the rest
        order = ["nav-1"] + english.main_menu[1:] + ["contact", "emergency"]
        order += [k for k in english.pages if k not in order and not english.pages[k].path.startswith("/products/")]
        product_order = english.product_menu + ["stock"]
        product_order += [k for k in english.pages if k not in order + product_order]
        in_menus = set(english.main_menu[1:]) | set(english.product_menu)

        self.frames = {s.language: page_frame(s, sites[s.language]) for s in OLD_SITES}

        # 1. the English tree
        root = Page.get_first_root_node()
        home_old = english.pages["nav-1"]
        home = self.new_page(root, home_old, "en", slug="home", in_menu=False)
        for key in order[1:]:
            if key in english.pages:
                self.new_page(home, english.pages[key], "en", in_menu=key in in_menus)
        products = models.ProductsPage(title=PRODUCTS_FOLDER_TITLES["en"], slug="products", locale=locales["en"],
                                       show_in_menus=False)
        home.add_child(instance=products)
        self.pages[("en", "products")] = products
        self.paths[("en", "/products")] = products
        for key in product_order:
            if key in english.pages:
                self.new_page(products, english.pages[key], "en", in_menu=key in in_menus)

        # 2. English content (links between pages need the pages to exist)
        for key, old in english.pages.items():
            self.fill(self.pages[("en", key)], old, "en")
        for page in [p for (language, key), p in self.pages.items() if language == "en"]:
            Page.objects.get(pk=page.pk).specific.save_revision().publish()

        # 3. translations, in tree order so that each parent exists first;
        # then their content, once they all exist
        tree = [("nav-1", None)] + [(k, "nav-1") for k in order[1:] if k in english.pages]
        tree += [("products", "nav-1")] + [(k, "products") for k in product_order if k in english.pages]
        english_frame_paths = self.frames["en"][1]
        for old_site in OLD_SITES[1:]:
            language = old_site.language
            content = sites[language]
            # where the old site's frame links give a page's address (all we know of the Russian pages)
            known_paths = {}
            for name, path in self.frames[language][1].items():
                en_page = self.paths.get(("en", english_frame_paths.get(name)))
                if en_page is not None:
                    known_paths[en_page.id] = path
            for key, parent_key in tree:
                source = self.pages[("en", key)]
                self.translate(source, content.pages.get(key), language, locales[language], known_paths.get(source.id), key)
            # pages with no old text: from the language's translation.json, if there is one
            data = translations.load(language)
            translator = self.translator(language, data) if data else None
            for key, parent_key in tree:
                old = content.pages.get(key)
                translated = False
                if old is not None:
                    self.fill(self.pages[(language, key)], old, language)
                elif translator is not None:
                    page = Page.objects.get(pk=self.pages[(language, key)].pk).specific
                    translated = translator.apply(page, self.english_address(key))
                    if translated:
                        if isinstance(page, models.ImageColumnPage):
                            advert = old_site.php_dir / "frontend/images/page/e-fin_ad.jpg"
                            if advert.exists():  # the advert has words on it, in this language
                                page.advert_image = self.static_image(language, "frontend/images/page/e-fin_ad.jpg",
                                                                      f"E-fin advert ({language})")
                        page.save()
                        self.report.translated[language] = self.report.translated.get(language, 0) + 1
                        untranslated = self.report.untranslated.get(language, [])
                        if self.pages[("en", key)].url_path in untranslated:
                            untranslated.remove(self.pages[("en", key)].url_path)
                page = Page.objects.get(pk=self.pages[(language, key)].pk).specific
                if old is not None or translated or key == "products" or self.publish_untranslated:
                    page.save_revision().publish()
                else:
                    page.save_revision()
            if not self.report.untranslated.get(language):
                self.report.untranslated.pop(language, None)

        # 4. the sites and their details
        for old_site in OLD_SITES:
            self.make_site(old_site, self.frames[old_site.language])

        self.report.titles = sorted(set(self.report.titles))
        return self.report

    def english_address(self, key):
        """Address of an English page on its site: "/", "/products/u-tubes"."""
        home = Page.objects.get(pk=self.pages[("en", "nav-1")].pk)
        page = Page.objects.get(pk=self.pages[("en", key)].pk)
        return "/" + page.url_path[len(home.url_path):].strip("/")

    def translator(self, language, data):
        home = Page.objects.get(pk=self.pages[(language, "nav-1")].pk)
        english_ids, translated_urls = {}, {}
        for (lang, key), page in self.pages.items():
            if lang != "en":
                continue
            address = self.english_address(key)
            english_ids[address] = page.id
            translation = Page.objects.filter(pk=self.pages[(language, key)].pk).first() if (language, key) in self.pages else None
            if translation is not None:
                translated_urls[address] = "/" + translation.url_path[len(home.url_path):].strip("/")
        return translations.Translator(language, data, english_ids, translated_urls)

    def new_page(self, parent, old, language, slug=None, in_menu=False):
        model = PAGE_TYPES[old.template]
        title = restore_accents(old.title)
        if title != old.title:
            self.report.titles.append(f"[{language}] {old.title}  ->  {title}")
        page = model(
            title=old.nav_title or title,
            seo_title=title if title != (old.nav_title or title) else "",
            slug=slug or old.path.rstrip("/").rsplit("/", 1)[-1],
            search_description=old.description,
            meta_keywords=old.keywords,
            show_in_menus=in_menu,
            locale=Locale.objects.get(language_code=language),
            live=True,
        )
        parent.add_child(instance=page)
        self.pages[(language, old.key)] = page
        self.paths[(language, old.path)] = page
        return page

    def translate(self, source, old, language, locale, known_path, key):
        if source.get_translation_or_none(locale) is not None:
            raise RuntimeError(f"{source} already has a {language} translation; use --replace")
        # a fresh copy: the cached parent of a page just created can have an out-of-date child count
        page = Page.objects.get(pk=source.pk).specific.copy_for_translation(locale).specific
        if old is not None:
            title = restore_accents(old.title)
            if title != old.title:
                self.report.titles.append(f"[{language}] {old.title}  ->  {title}")
            page.title = old.nav_title or title
            page.seo_title = title if title != page.title else ""
            page.search_description = old.description
            page.meta_keywords = old.keywords
            page.slug = old.path.rstrip("/").rsplit("/", 1)[-1] if old.path != "/" else f"home-{language}"
            self.paths[(language, old.path)] = page
        elif key == "nav-1":
            page.slug = f"home-{language}"
        elif key == "products":
            page.title = PRODUCTS_FOLDER_TITLES[language]
            page.live = True
            self.paths[(language, "/products")] = page
        else:
            if known_path:
                page.slug = known_path.rsplit("/", 1)[-1]
                self.paths[(language, known_path)] = page
            self.report.untranslated.setdefault(language, []).append(source.url_path)
        page.save()
        self.pages[(language, key)] = page
        return page

    def fill(self, page, old, language):
        # a fresh copy: pages added below this one since it was loaded are
        # not in its child count, and saving it would lose them
        page = Page.objects.get(pk=page.pk).specific
        convert = ContentConverter(self, language)
        r = old.regions
        content = r["content"]
        stream = convert.stream

        def picture(region="Page Image"):
            return self.cms_image(language, r["image"].get(region))

        if isinstance(page, models.HomePage):
            page.header_text = stream(content.get("Page Header Text"))
            page.body = stream(content.get("Home Page Content"))
        elif isinstance(page, models.ContactPage):
            page.header_text = stream(content.get("Page Header Text"))
            page.left_column = stream(content.get("Left Column Text"))
            page.right_column = stream(content.get("Right Column Text"))
            without_comments = re.sub(r"<!--.*?-->", "", old.html, flags=re.S)
            frame = re.search(r'<iframe[^>]+src="([^"]+)"', without_comments)
            page.map_url = html.unescape(frame.group(1)) if frame else ""
            php = (SITES_BY_LANGUAGE[language].php_dir / "frontend/template/contact.php").read_text(encoding="utf-8")
            subject = re.search(r'\$subject = "([^"]+)"', php)
            if subject:
                page.email_subject = subject.group(1)
        elif isinstance(page, models.SizeChartPage):
            php = (SITES_BY_LANGUAGE[language].php_dir / "frontend/template/size-conversion-chart.php").read_text(encoding="utf-8")
            intro = re.search(r'<div class="content grid_12">\s*(<p>.*?</p>)', php, re.S)
            page.intro = stream(intro.group(1) if intro else "")
        else:
            if hasattr(page, "image"):
                page.image, page.image_alt = picture()
            if isinstance(page, models.StandardPage):
                page.header_text = stream(content.get("Page Header Text"))
                page.body = stream(content.get("Page Content"))
            elif isinstance(page, models.ProductPage):
                heading = restore_accents(r["title"] or "")
                page.heading_text = heading if heading != page.title else ""
                page.body = stream(content.get("Page Content"))
            elif isinstance(page, models.SliderPage):
                page.header_text = stream(content.get("Page Header Text"))
                page.body = stream(content.get("Page Content"))
                slides = []
                for tag in atlas.IMG_TAG.findall(r["repeat"].get("Slider Images", "")):
                    image, alt = self.cms_image(language, tag)
                    if image is not None:
                        slides.append(("slide", {"image": image, "alt_text": alt, "decorative": False}))
                page.slides = slides
                page.side_image = (self.static_image(language, "frontend/images/u_tube_page.jpg", "U tube")
                                   if old.template == "page-slider-body-image" else None)
            elif isinstance(page, models.TwoColumnPage):
                page.left_column = stream(content.get("Page Content Left"))
                page.right_column = stream(content.get("Page Content Right"))
            elif isinstance(page, models.StockPage):
                page.body = stream(content.get("Page Content"))
                page.intro = stream(content.get("Intro Text"))
                page.left_column = stream(content.get("Left Column Text"))
                page.right_column = stream(content.get("Right Column Text"))
                page.chart_image = self.static_image(language, "frontend/images/page/stock-chart.png", "Stock chart")
            elif isinstance(page, models.ImageColumnPage):
                page.intro = stream(content.get("Page Intro"))
                page.full_width_text = stream(content.get("Full Width Page Text"))
                page.advert_image = self.static_image(language, "frontend/images/page/e-fin_ad.jpg",
                                                      f"E-fin advert ({language})")
                page.advert_link = None  # the E-fin page it linked to is broken on the old sites
                rows = []
                for i in range(1, 6):
                    text = content.get(f"Product Text {i}", "")
                    image, alt = picture(f"Product Image {i}")
                    if text.strip() or image is not None:
                        rows.append(("row", {"text": stream(text),
                                             "image": {"image": image, "alt_text": alt, "decorative": False}
                                             if image else None}))
                page.rows = rows
        page.save()

    def make_site(self, old_site, frame):
        language = old_site.language
        locale = Locale.objects.get(language_code=language)
        details, links = frame
        home = self.pages[(language, "nav-1")]
        hostname = self.hostnames.get(language, old_site.hostname)
        site = Site.objects.filter(hostname=hostname).first() or Site(hostname=hostname)
        site.port = self.port
        site.root_page = home
        site.site_name = f"Tube Supply International ({old_site.tld})"
        site.is_default_site = language == "en"
        if site.is_default_site:
            Site.objects.filter(is_default_site=True).exclude(pk=site.pk).update(is_default_site=False)
        site.save()

        settings, _ = models.SiteDetails.objects.get_or_create(site=site)
        data = translations.load(language)
        if data and data.get("site_details"):
            details = {**details, **data["site_details"]}
        if not details.get("telephone") and language != "en":
            english = self.frames["en"][0]
            details["telephone"], details["email"] = english.get("telephone", ""), english.get("email", "")
            self.report.note(language, "telephone and email copied from the English site")
        for name, value in details.items():
            setattr(settings, name, value)
        english_links = self.frames["en"][1]
        for name in english_links:
            path = links.get(name)
            page = self.paths.get((language, path)) if path else None
            if page is None:
                # the page in the same place on the English site, in this language
                en_page = self.paths.get(("en", english_links.get(name)))
                page = en_page.get_translation_or_none(locale) if en_page else None
            setattr(settings, name, page)
        settings.save()
