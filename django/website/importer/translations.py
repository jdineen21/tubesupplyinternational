"""Translated text for pages that were not on the old sites.

The old Russian site was offline and there never was an Arabic one, so their
pages are translations of the English pages. The translated text is kept in
snapshot/<language>/translation.json:

    {
      "pages": {
        "/products/u-tubes": {               # the English page's address
          "title": "...", "seo_title": "...", "search_description": "...",
          "body": ["<p>...</p>", ...],       # one entry per block, in order
          "slides": ["picture description", ...],
          "rows": [{"text": [...], "image_alt": "..."}]
        }
      },
      "site_details": {"tagline": "...", ...}   # fields of Site details
    }

`manage.py export_page_text` writes the English text in this form, for a
translator. Text blocks are the editor's HTML; a link to another page is
written <a linktype="page" page="/address-of-the-english-page">, and links
in HTML blocks use the English addresses: both are pointed at the
translated pages when the text is put in.
"""
import json
import re

from wagtail.rich_text import RichText

from .sites import SNAPSHOT_DIR

TEXT_FIELDS = ["title", "seo_title", "search_description", "meta_keywords", "heading_text", "image_alt",
               "email_subject"]
STREAM_FIELDS = ["header_text", "body", "intro", "left_column", "right_column", "full_width_text"]


def load(language):
    path = SNAPSHOT_DIR / language / "translation.json"
    if not path.exists():
        return None
    return json.loads(path.read_text(encoding="utf-8"))


# -- export

def _block_text(block, paths_by_id):
    if block.block_type == "image":
        return block.value.contextual_alt_text or ""
    if block.block_type == "text":
        source = re.sub(r' data-block-key="[^"]*"', "", block.value.source)
        return re.sub(r'<a id="(\d+)" linktype="page">|<a linktype="page" id="(\d+)">',
                      lambda m: f'<a linktype="page" page="{paths_by_id.get(int(m.group(1) or m.group(2)), "")}">',
                      source)
    return str(block.value)


def page_text(page, paths_by_id):
    """Everything to translate on one page."""
    out = {}
    for name in TEXT_FIELDS:
        value = getattr(page, name, "")
        if value:
            out[name] = value
    for name in STREAM_FIELDS:
        stream = getattr(page, name, None)
        if stream:
            out[name] = [_block_text(block, paths_by_id) for block in stream]
    if getattr(page, "slides", None):
        out["slides"] = [slide.value.contextual_alt_text or "" for slide in page.slides]
    if getattr(page, "rows", None):
        out["rows"] = [
            {"text": [_block_text(b, paths_by_id) for b in row.value["text"]],
             "image_alt": row.value["image"].contextual_alt_text if row.value["image"] else ""}
            for row in page.rows
        ]
    return out


# -- import

class Translator:
    """Puts translated text into a language's copies of the English pages."""

    def __init__(self, language, data, english_ids, translated_urls):
        """english_ids: English page address -> page id (for rich text links);
        translated_urls: English page address -> this language's page address."""
        self.language = language
        self.data = data
        self.english_ids = english_ids
        self.translated_urls = translated_urls

    def text_block(self, html):
        def link(m):
            page_id = self.english_ids.get(m.group(1))
            if page_id is None:
                raise ValueError(f"[{self.language}] link to an unknown page {m.group(1)!r}")
            # rich text page links show the page in the visitor's language
            return f'<a linktype="page" id="{page_id}">'
        return RichText(re.sub(r'<a linktype="page" page="([^"]*)">', link, html))

    def html_block(self, html):
        def href(m):
            path = m.group(2).rstrip("/") or "/"
            return f'{m.group(1)}"{self.translated_urls.get(path, m.group(2))}"'
        return re.sub(r'(href=)"(/[^"#?]*)"', href, html)

    def stream(self, stream, texts, where):
        if len(texts) != len(stream):
            raise ValueError(f"[{self.language}] {where}: {len(texts)} translated blocks for {len(stream)} blocks")
        blocks = []
        for block, text in zip(stream, texts):
            if block.block_type == "text":
                blocks.append(("text", self.text_block(text)))
            elif block.block_type == "html":
                blocks.append(("html", self.html_block(text)))
            else:
                blocks.append((block.block_type, image_value(block.value, text)))
        return blocks

    def apply(self, page, english_path):
        """Translate page (a copy of the English page at english_path).
        Returns False if there is no translation for it."""
        text = self.data.get("pages", {}).get(english_path)
        if text is None:
            return False
        for name in TEXT_FIELDS:
            if name in text and hasattr(page, name):
                setattr(page, name, text[name])
        for name in STREAM_FIELDS:
            if name in text:
                setattr(page, name, self.stream(getattr(page, name), text[name], f"{english_path} {name}"))
        if "slides" in text:
            if len(text["slides"]) != len(page.slides):
                raise ValueError(f"[{self.language}] {english_path} slides: wrong number of descriptions")
            page.slides = [("slide", image_value(slide.value, alt)) for slide, alt in zip(page.slides, text["slides"])]
        if "rows" in text:
            if len(text["rows"]) != len(page.rows):
                raise ValueError(f"[{self.language}] {english_path} rows: wrong number of rows")
            page.rows = [
                ("row", {"text": self.stream(row.value["text"], t["text"], f"{english_path} row {i + 1}"),
                         "image": image_value(row.value["image"], t.get("image_alt", "")) if row.value["image"] else None})
                for i, (row, t) in enumerate(zip(page.rows, text["rows"]))
            ]
        return True


def image_value(image, alt):
    return {"image": image, "alt_text": alt, "decorative": False}
