"""Save a copy of a live site: every page reachable from its links, and the
pictures its pages show from the CMS (/media/images/...).

The copy goes into importer/snapshot/<language>/ and is what the import
reads, so the import does not depend on the old sites still being online.
"""
import json
import re
import time
import urllib.parse
import urllib.request
from pathlib import Path

USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36"
SKIP = re.compile(r"\.(jpg|jpeg|png|gif|pdf|css|js|ico|mp4|webm|swf|xml|zip|docx?|xlsx?)$", re.I)
NOT_PAGES = ("/admin", "/frontend", "/media", "/securimage", "/downloads")
CMS_IMAGE = re.compile(r'src="(?:https?://[^/"]+)?(/media/images/[a-z-]+/\d+\.(?:jpg|jpeg|png|gif))"', re.I)


def _get(url, timeout=30):
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return response.read(), urllib.parse.urlparse(response.geturl())


def page_file_name(path):
    return (path.strip("/").replace("/", "__") or "_home") + ".html"


def fetch_site(base_url, out_dir, log=print, delay=0.4):
    """Crawl base_url and save it under out_dir. Returns the list of pages."""
    base_url = base_url.rstrip("/")
    host = urllib.parse.urlparse(base_url).netloc
    out_dir = Path(out_dir)
    (out_dir / "pages").mkdir(parents=True, exist_ok=True)
    (out_dir / "media").mkdir(parents=True, exist_ok=True)

    seen, queue, pages, images = set(), ["/"], [], set()
    while queue:
        path = queue.pop(0)
        if path in seen:
            continue
        seen.add(path)
        try:
            body, final = _get(base_url + path)
        except Exception as e:  # a broken link on the old site; noted, not fatal
            log(f"  {path}: {e}")
            pages.append({"path": path, "error": str(e)})
            continue
        text = body.decode("utf-8", "replace")
        if final.netloc != host or (final.path.rstrip("/") or "/") != path:
            # a redirect (e.g. /products goes to the home page)
            pages.append({"path": path, "redirect": final.path or "/"})
            log(f"  {path} -> {final.path}")
            continue
        name = page_file_name(path)
        (out_dir / "pages" / name).write_text(text, encoding="utf-8")
        pages.append({"path": path, "file": name})
        log(f"  {path}")
        images.update(CMS_IMAGE.findall(text))
        for href in re.findall(r'href="([^"#]+)"', text):
            if href.startswith(("mailto:", "javascript:", "tel:")) or "@" in href or href.strip() in (";", ""):
                continue
            url = urllib.parse.urlparse(urllib.parse.urljoin(base_url + path, href))
            if url.netloc not in (host, "") or SKIP.search(url.path) or url.path.startswith(NOT_PAGES):
                continue
            p = url.path if url.path.startswith("/") else "/" + url.path
            p = p.rstrip("/") or "/"
            if p not in seen and p not in queue:
                queue.append(p)
        time.sleep(delay)

    for src in sorted(images):
        target = out_dir / "media" / src.lstrip("/").replace("/", "__")
        try:
            data, _ = _get(base_url + src)
        except Exception as e:
            log(f"  {src}: {e}")
            continue
        target.write_bytes(data)
        time.sleep(delay)
    log(f"  {len(images)} pictures")

    (out_dir / "site.json").write_text(
        json.dumps({"base_url": base_url, "fetched": time.strftime("%Y-%m-%d %H:%M"), "pages": pages}, indent=1),
        encoding="utf-8",
    )
    return pages
