"""Screenshot every saved page on the old (live) sites and on the new site,
and measure how much of each pair differs.

    python tools/visual_compare.py --new 127.0.0.1:8000 --out /tmp/visual [en es ...]

Needs Node with Playwright (as used for the PHP upgrade's browser tests) and
the new site running (manage.py runserver). Writes <page>.old.png,
<page>.new.png and, where they differ, <page>.diff.png (differences in red).
"""
import argparse
import json
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageChops

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))
SNAPSHOT = HERE.parent / "website" / "importer" / "snapshot"


def pages(languages):
    out = []
    for language in languages:
        info = json.loads((SNAPSHOT / language / "site.json").read_text(encoding="utf-8"))
        host = info["base_url"].split("://", 1)[1]
        for entry in info["pages"]:
            if not entry.get("file"):
                continue
            html = (SNAPSHOT / language / "pages" / entry["file"]).read_text(encoding="utf-8")
            if 'class="content' not in html:
                continue  # an empty (broken) page on the old site
            out.append({"language": language, "host": host, "path": entry["path"],
                        "old": info["base_url"] + entry["path"]})
    return out


def compare(old_file, new_file, diff_file):
    old, new = Image.open(old_file).convert("RGB"), Image.open(new_file).convert("RGB")
    width, height = max(old.width, new.width), max(old.height, new.height)
    canvas_old = Image.new("RGB", (width, height), "white")
    canvas_new = Image.new("RGB", (width, height), "white")
    canvas_old.paste(old)
    canvas_new.paste(new)
    diff = ImageChops.difference(canvas_old, canvas_new).convert("L").point(lambda v: 255 if v > 40 else 0)
    changed = sum(1 for v in diff.getdata() if v)
    share = changed / (width * height)
    if changed:
        overlay = canvas_new.copy()
        overlay.paste((255, 0, 0), mask=diff)
        Image.blend(canvas_new, overlay, 0.6).save(diff_file)
    return share, old.size, new.size


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("languages", nargs="*", default=["en", "es", "fr", "pt"])
    parser.add_argument("--new", default="127.0.0.1:8000")
    parser.add_argument("--out", required=True)
    parser.add_argument("--skip-screenshots", action="store_true")
    args = parser.parse_args()
    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    todo = pages(args.languages)
    (out / "pages.json").write_text(json.dumps(todo), encoding="utf-8")
    if not args.skip_screenshots:
        subprocess.run(["node", str(HERE / "screenshots.js"), str(out / "pages.json"), str(out), args.new], check=True)
    results = []
    for p in todo:
        name = f"{p['language']}{'_home' if p['path'] == '/' else p['path'].replace('/', '_')}"
        share, old_size, new_size = compare(out / f"{name}.old.png", out / f"{name}.new.png", out / f"{name}.diff.png")
        results.append((name, share, old_size, new_size))
        print(f"{name:70} {share * 100:6.2f}% differs   old {old_size[0]}x{old_size[1]}  new {new_size[0]}x{new_size[1]}")
    (out / "results.json").write_text(json.dumps(results, indent=1), encoding="utf-8")


if __name__ == "__main__":
    main()
