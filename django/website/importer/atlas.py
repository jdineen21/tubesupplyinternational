"""Read content back out of pages rendered by the old Atlas CMS (PHP).

The live sites are rendered from PHP templates like

    <div class="content grid_12">
    <?php echo $this->content('Page Header Text'); ?>
    <?php echo $this->image($this->content('Page Image'), 'rendered', 'pageImg') ?>
    ...

so a template minus its PHP is the fixed HTML around each editable region.
template_pattern() turns a template into a regular expression with one
group per region; extract() matches it against a live page and returns
each region's HTML. No knowledge of the database is needed.
"""
import html
import re

PHP_BLOCK = re.compile(r'<\?php(.*?)\?>\n?', re.S)

# PHP code -> (kind, region name)
CONTENT = re.compile(r"^\s*echo\s+\$this->content\('([^']+)'(?:\s*,\s*0\s*,\s*'global')?\s*\)\s*;?\s*$")
IMAGE = re.compile(r"^\s*echo\s+\$this->image\(\s*\$this->content\('([^']+)'")
TITLE = re.compile(r"^\s*echo\s+(\$this->getTitle\(\)|\$pageInfo\['navTitle'\])\s*;?\s*$")
INCLUDE = re.compile(r"include\('includes/([a-z-]+)\.php'\)")
LOOP_START = re.compile(r'\$this->repeatRegion\(\'([^\']+)\'\)')


def _static(text):
    """Literal template text as a pattern; whitespace runs may differ."""
    out = []
    for piece in re.split(r'(\s+)', text):
        if not piece:
            continue
        out.append(r'\s*' if piece.isspace() else re.escape(piece))
    return ''.join(out)


def template_tokens(php_source):
    """Split a template into ('static', pattern) and ('hole', kind, region) tokens."""
    tokens = []
    pos = 0
    loop_open = None
    cond_depth = 0  # inside an if/else spread over several PHP blocks
    for m in PHP_BLOCK.finditer(php_source):
        code = m.group(1)
        if cond_depth:
            # either branch may be on the page: the whole if/else is one wildcard
            cond_depth += code.count('{') - code.count('}')
            pos = m.end()
            if cond_depth <= 0:
                cond_depth = 0
                tokens.append(('hole', 'other', None))
            continue
        if loop_open is None:
            tokens.append(('static', php_source[pos:m.start()]))
        pos = m.end()

        opens = code.count('{') - code.count('}')
        if loop_open is None and opens > 0 and not LOOP_START.search(code):
            cond_depth = opens
            continue

        if loop_open is not None:
            # the loop body repeats on the page; capture it as one region
            if re.match(r'^\s*}\s*$', code):
                tokens.append(('hole', 'repeat', loop_open))
                loop_open = None
            continue

        loop = LOOP_START.search(code)
        if loop and 'while' in code:
            loop_open = loop.group(1)
            continue

        for kind, rx in (('content', CONTENT), ('image', IMAGE), ('title', TITLE)):
            r = rx.match(code)
            if r:
                tokens.append(('hole', kind, r.group(1) if kind != 'title' else 'title'))
                break
        else:
            inc = INCLUDE.search(code)
            tokens.append(('hole', 'include', inc.group(1)) if inc else ('hole', 'other', None))
    tokens.append(('static', php_source[pos:]))

    # merge: drop empty statics; holes separated only by whitespace become one group
    merged = []
    for tok in tokens:
        if tok[0] == 'static':
            if tok[1].strip():
                merged.append(('static', re.compile(_static(tok[1].strip()), re.S)))
            continue
        if merged and merged[-1][0] == 'holes':
            merged[-1][1].append(tok[1:])
        else:
            merged.append(('holes', [tok[1:]]))
    return merged


def _split_holes(text, holes):
    """Share text between holes that had only whitespace between them.

    Images are single <img> tags, so they are peeled off either end; whatever
    is left goes to the first remaining (content) hole.
    """
    values = [''] * len(holes)
    lo, hi = 0, len(holes) - 1
    rest = text
    while lo < hi:
        if holes[hi][0] == 'image':
            m = re.search(r'(<img\b[^>]*>)\s*\Z', rest, re.I)
            if m:
                values[hi], rest = m.group(1), rest[:m.start()]
            hi -= 1
        elif holes[lo][0] == 'image':
            m = re.match(r'\s*(<img\b[^>]*>)', rest, re.I)
            if m:
                values[lo], rest = m.group(1), rest[m.end():]
            lo += 1
        else:
            break
    values[lo] = rest
    return values


def extract(php_source, page_html):
    """Regions of a live page, or None if the page was not made from this template.

    Returns {'content': {name: html}, 'image': {name: <img> html},
             'title': str, 'repeat': {name: html}, 'include': {name: html}}
    Each fixed piece of template HTML is found in turn, and what lies between
    two pieces is the region (non-greedy, like .*? but without backtracking).
    """
    tokens = template_tokens(php_source)
    out = {'content': {}, 'image': {}, 'title': None, 'repeat': {}, 'include': {}, 'score': 0}
    pos = 0
    pending = None  # holes waiting for the next fixed piece
    for tok in tokens:
        if tok[0] == 'holes':
            pending = tok[1]
            continue
        if pending is None:
            m = tok[1].match(page_html, len(page_html) - len(page_html[pos:].lstrip()))
        else:
            m = tok[1].search(page_html, pos)
        if not m:
            return None
        if pending is not None:
            _store(out, pending, page_html[pos:m.start()])
            pending = None
        out['score'] += m.end() - m.start()  # how much of the page the template's own HTML covers
        pos = m.end()
    if pending is not None:
        _store(out, pending, page_html[pos:])
    elif page_html[pos:].strip():
        return None
    return out


def best_match(templates, page_html):
    """(template name, regions) of the template that fits the page best.

    templates: {name: php source}. Several templates can fit a page (a loose
    one matches almost anything), so the one whose fixed HTML covers the most
    of the page wins.
    """
    best = None
    for name, source in templates.items():
        found = extract(source, page_html)
        if found and (best is None or found['score'] > best[1]['score']):
            best = (name, found)
    return best


def _store(out, holes, text):
    for (kind, region), value in zip(holes, _split_holes(text.strip(), holes)):
        value = value.strip()
        if kind == 'title':
            out['title'] = html.unescape(value)
        elif kind in out and region:
            out[kind].setdefault(region, value)


IMG_TAG = re.compile(r'<img\b[^>]*>', re.I)


def img_attrs(tag):
    """{'src': ..., 'alt': ..., 'class': ...} of an <img> tag."""
    return {k.lower(): html.unescape(v) for k, v in re.findall(r'([A-Za-z-]+)="([^"]*)"', tag)}


def first_image(fragment):
    m = IMG_TAG.search(fragment or '')
    return img_attrs(m.group(0)) if m else None
