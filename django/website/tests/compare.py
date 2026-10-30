"""Compare the new site's pages with the saved copy of the old sites.

Used by the tests and by `manage.py compare_with_old_sites`. For each saved
page it requests the same address on the same domain from the new site and
compares what a visitor sees: the page title and description, the menus,
the text of the page, its pictures and its links.
"""
import html
import json
import re
from dataclasses import dataclass, field

from bs4 import BeautifulSoup, Comment

from website.importer.load import restore_accents
from website.importer.sites import OLD_SITES, SNAPSHOT_DIR


@dataclass
class Difference:
    language: str
    path: str
    what: str
    old: object
    new: object


@dataclass
class Comparison:
    pages: int = 0
    differences: list = field(default_factory=list)


def visible_text(soup):
    for tag in soup(["script", "style", "noscript"]):
        tag.decompose()
    for c in soup.find_all(string=lambda s: isinstance(s, Comment)):
        c.extract()
    text = soup.get_text(" ")
    return re.sub(r"\s+", " ", html.unescape(text).replace("\xa0", " ")).strip()


def menu(soup, selector):
    return [(a.get_text(strip=True), a["href"]) for a in soup.select(selector)]


def links(soup):
    """Links in the page body, as paths (the old sites mixed full and relative addresses)."""
    out = []
    body = soup.select_one(".container_16") or soup
    for a in body.find_all("a", href=True):
        if a.find_parent(class_="languageMenu"):
            continue  # the flags now go to the same page in the other language
        href = a["href"]
        if href.startswith(("mailto:", "javascript:", "#")):
            out.append(href)
            continue
        href = re.sub(r"^https?://(www\.)?tubesupplyinternational\.[a-z.]+", "", href)
        out.append(href.rstrip("/") or "/")
    return out


def pictures(soup):
    """Pictures in the page content (the frame's icons are the same files as before)."""
    out = []
    for area in soup.select("div.content, div.grid_12.omega"):
        if area.find_parent(class_="content"):
            continue
        out += [img.get("alt", "") for img in area.find_all("img") if not img.find_parent(class_="homeFeature")]
    return out


def normalise_text(text, year, titles=()):
    """Allow for the intended differences: the footer's copyright year, the
    accents put back in titles and menus, and the French footer's "Lire plus"
    links now all written the same way."""
    text = re.sub(r"© \d{4} Tube Supply", f"© {year} Tube Supply", text)
    for title in sorted(titles, key=len, reverse=True):
        text = text.replace(title, restore_accents(title))
    return text.replace("Lire Plus >", "Lire plus >")


def compare_site(client_get, language, year):
    folder = SNAPSHOT_DIR / language
    info = json.loads((folder / "site.json").read_text(encoding="utf-8"))
    result = Comparison()
    # pages the old site showed empty (broken): the new site does not link to them
    broken = set()
    for entry in info["pages"]:
        if entry.get("file"):
            page = BeautifulSoup((folder / "pages" / entry["file"]).read_text(encoding="utf-8"), "html.parser")
            if page.select_one(".content") is None:
                broken.add(entry["path"])
    for entry in info["pages"]:
        path = entry["path"]
        response = client_get(path)
        if entry.get("redirect"):
            ok = response.status_code in (301, 302) and response["Location"].rstrip("/").endswith(entry["redirect"].rstrip("/"))
            if not ok:
                result.differences.append(Difference(language, path, "redirect", entry["redirect"], response.status_code))
            result.pages += 1
            continue
        if not entry.get("file"):
            continue
        old_html = (folder / "pages" / entry["file"]).read_text(encoding="utf-8")
        old = BeautifulSoup(old_html, "html.parser")
        if old.select_one(".content") is None:
            # the old site showed an empty page here (a broken page): the new one has none
            if response.status_code != 404:
                result.differences.append(Difference(language, path, "status", 404, response.status_code))
            result.pages += 1
            continue
        result.pages += 1
        if response.status_code != 200:
            result.differences.append(Difference(language, path, "status", 200, response.status_code))
            continue
        new = BeautifulSoup(response.content.decode("utf-8"), "html.parser")
        old_title = html.unescape(old.title.get_text()).strip()
        titles = [old_title] + [a.get_text(strip=True) for a in old.select("nav li a, .sidebar li a, h1")]
        checks = [
            ("title", restore_accents(old_title), new.title.get_text().strip()),
            ("description", meta(old, "description"), meta(new, "description")),
            ("keywords", meta(old, "keywords"), meta(new, "keywords")),
            ("main menu", restored(menu(old, "nav li a")), menu(new, "nav li a")),
            ("current menu item", restored(menu(old, "li.current a")), menu(new, "li.current a")),
            ("product menu", restored(menu(old, ".sidebar li a")), menu(new, ".sidebar li a")),
            ("language flags", [a.get("class") for a in old.select(".languageMenu a")],
             [a.get("class") for a in new.select(".languageMenu a")]),
            ("links", [link for link in links(old) if link not in broken], links(new)),
            ("pictures", pictures(old), pictures(new)),
            ("text", normalise_text(visible_text(old), year, titles), normalise_text(visible_text(new), year)),
        ]
        for what, a, b in checks:
            if a != b:
                result.differences.append(Difference(language, path, what, a, b))
    return result


def restored(items):
    return [(restore_accents(text), href) for text, href in items]


def meta(soup, name):
    tag = soup.find("meta", attrs={"name": name})
    return html.unescape(tag.get("content", "")).strip() if tag else ""


def saved_languages():
    return [s.language for s in OLD_SITES if (SNAPSHOT_DIR / s.language / "site.json").exists()]
