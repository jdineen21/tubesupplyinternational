"""Crawl one of the sites end to end and record everything it does.

    python3 tests/run_tests.py --php php8.5 --site sites/tubesupplyinternational.co.uk --out /tmp/new

The site is copied to a temporary folder (so real config and media are
never touched), the test database is reset from schema.sql + seed.sql,
and PHP's built-in server is started with tests/router.php. Every step
below is then run and its response saved to --out, along with:

  steps/NNN-name.txt  status, redirect and normalised body of each step
  errors.txt          PHP errors/warnings/deprecations, per step
  mail.txt            mail sent by the contact form
  db.txt              database contents at the end
  files.txt           files the site created (uploads, thumbnails...)

compare.py diffs two such folders (old PHP vs new PHP).
"""
import argparse
import hashlib
import http.cookiejar
import io
import json
import os
import re
import shutil
import socket
import struct
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import zlib

HERE = os.path.dirname(os.path.abspath(__file__))
DB = dict(host='localhost', name='atlastest', user='atlas', password='atlaspw')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


class Browser:
    """A cookie-keeping HTTP client that does not follow redirects."""

    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar), NoRedirect(),
            urllib.request.ProxyHandler({}))

    def request(self, path, data=None, files=None, headers=None):
        url = self.base + path
        body = None
        hdrs = dict(headers or {})
        if files is not None:
            boundary = '----atlastest' + hashlib.md5(path.encode()).hexdigest()
            parts = []
            for k, v in (data or []):
                parts.append(('--%s\r\nContent-Disposition: form-data; name="%s"\r\n\r\n%s\r\n'
                              % (boundary, k, v)).encode())
            for k, (fname, content, ctype) in files.items():
                parts.append(('--%s\r\nContent-Disposition: form-data; name="%s"; filename="%s"\r\n'
                              'Content-Type: %s\r\n\r\n' % (boundary, k, fname, ctype)).encode()
                             + content + b'\r\n')
            parts.append(('--%s--\r\n' % boundary).encode())
            body = b''.join(parts)
            hdrs['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        elif data is not None:
            body = urllib.parse.urlencode(data).encode()
            hdrs['Content-Type'] = 'application/x-www-form-urlencoded'
        req = urllib.request.Request(url, data=body, headers=hdrs)
        try:
            resp = self.opener.open(req, timeout=60)
        except urllib.error.HTTPError as e:
            resp = e
        status = resp.status if hasattr(resp, 'status') else resp.code
        # header names are case-insensitive; store them in their usual capitalisation
        headers = {k.title(): v for k, v in resp.headers.items()}
        return status, headers, resp.read()

    def cookie(self, name):
        for c in self.jar:
            if c.name == name:
                return c.value
        return None


def png_size(data):
    if data[:8] == b'\x89PNG\r\n\x1a\n':
        return struct.unpack('>II', data[16:24])
    return None


def jpeg_size(data):
    i = 2
    while i < len(data) - 9:
        if data[i] != 0xFF:
            i += 1
            continue
        marker = data[i + 1]
        if marker in (0xC0, 0xC1, 0xC2):
            h, w = struct.unpack('>HH', data[i + 5:i + 9])
            return (w, h)
        i += 2 + struct.unpack('>H', data[i + 2:i + 4])[0]
    return None


def describe_binary(ctype, data):
    if data[:3] == b'\xff\xd8\xff':
        return 'JPEG image %sx%s' % jpeg_size(data) if jpeg_size(data) else 'JPEG image'
    if png_size(data):
        return 'PNG image %sx%s' % png_size(data)
    if data[:4] == b'RIFF' and data[8:12] == b'WAVE':
        return 'WAV audio (over 1000 bytes: %s)' % (len(data) > 1000)
    if data[:3] == b'ID3' or data[:2] in (b'\xff\xf3', b'\xff\xfb', b'\xff\xf2'):
        return 'MP3 audio (over 1000 bytes: %s)' % (len(data) > 1000)
    if data[:4] == b'%PDF':
        return 'PDF'
    return None


class Runner:
    def __init__(self, args):
        self.args = args
        self.php = args.php
        self.out = os.path.abspath(args.out)
        shutil.rmtree(self.out, ignore_errors=True)
        os.makedirs(os.path.join(self.out, 'steps'))
        self.tmp = tempfile.mkdtemp(prefix='atlastest-')
        self.root = os.path.join(self.tmp, 'site')
        shutil.copytree(os.path.abspath(args.site), self.root,
                        ignore=shutil.ignore_patterns('config.php'))
        self.sessions = os.path.join(self.tmp, 'sessions')
        os.makedirs(self.sessions)
        self.errlog = os.path.join(self.tmp, 'php-errors.log')
        self.maillog = os.path.join(self.tmp, 'mail.txt')
        open(self.errlog, 'w').close()
        # fake sendmail: keeps each message (mail() adds -f ... arguments, which it ignores)
        self.sendmail = os.path.join(self.tmp, 'sendmail.sh')
        with open(self.sendmail, 'w') as f:
            f.write('#!/bin/sh\ncat >> %s\necho >> %s\n' % (self.maillog, self.maillog))
        os.chmod(self.sendmail, 0o755)
        self.step_no = 0
        self.errors = []
        self.failures = []
        self.year = time.strftime('%Y')
        with open(os.path.join(self.root, 'common', 'config.php'), 'w') as f:
            f.write("<?php\n$dbhost = '%s';\n$dbname = '%s';\n$dbuser = '%s';\n$dbpass = '%s';\n"
                    % (DB['host'], DB['name'], DB['user'], DB['password']))
        # images to upload, made with GD (always by PHP 8.5, so both runs get identical input)
        self.jpeg, self.gif, self.png = (self.make_image(640, 480, f) for f in ('jpeg', 'gif', 'png'))
        self.odd_png = self.make_image(641, 479, 'png')

    @staticmethod
    def make_image(w, h, fmt):
        return subprocess.run(
            ['php8.5', '-r', '$i=imagecreatetruecolor(%d,%d);'
             'for($x=0;$x<%d;$x+=40){imagefilledrectangle($i,$x,0,$x+20,%d,imagecolorallocate($i,$x%%255,90,200));}'
             'ob_start();image%s($i);echo ob_get_clean();' % (w, h, w, h, fmt)],
            capture_output=True, check=True).stdout

    # ------------------------------------------------------------ plumbing
    def reset_db(self):
        cmd = ['mysql', '--default-character-set=utf8mb4']
        subprocess.run(cmd + ['-e', 'DROP DATABASE IF EXISTS %s; CREATE DATABASE %s CHARACTER SET utf8mb4'
                              % (DB['name'], DB['name'])], check=True)
        for f in ('schema.sql', 'seed.sql'):
            with open(os.path.join(HERE, f), 'rb') as fh:
                subprocess.run(cmd + [DB['name']], stdin=fh, check=True)

    def start_server(self):
        s = socket.socket()
        s.bind(('127.0.0.1', 0))
        self.port = s.getsockname()[1]
        s.close()
        ini = {
            'error_reporting': '32767',  # E_ALL (and E_STRICT on PHP 5)
            'display_errors': '1' if self.args.display_errors else '0',
            'log_errors': '1',
            'error_log': self.errlog,
            'session.save_path': self.sessions,
            'sendmail_path': self.sendmail,
            'date.timezone': 'Europe/London',
            'upload_max_filesize': '20M',
            'post_max_size': '25M',
            'memory_limit': '256M',
            'default_socket_timeout': '5',  # the old dashboard message server no longer answers
        }
        cmd = [self.php]
        for k, v in ini.items():
            cmd += ['-d', '%s=%s' % (k, v)]
        cmd += ['-S', '127.0.0.1:%d' % self.port, '-t', self.root, os.path.join(HERE, 'router.php')]
        self.server = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                                       cwd=self.root)
        for _ in range(100):
            try:
                socket.create_connection(('127.0.0.1', self.port), timeout=0.2).close()
                return
            except OSError:
                time.sleep(0.1)
        raise RuntimeError('server did not start')

    def stop(self):
        self.server.terminate()
        self.server.wait()

    def new_browser(self):
        return Browser('http://127.0.0.1:%d' % self.port)

    def normalise(self, text):
        text = re.sub(r'(PHPSESSID=|sid=)[0-9a-zA-Z,-]+', r'\1SID', text)
        text = re.sub(r'([?&](?:r|rnd)=)[0-9.]+', r'\1RAND', text)
        text = re.sub(r'(cropAPI_|initCrop_)\d+', r'\1N', text)
        text = text.replace('\u00a9 ' + self.year, '\u00a9 YEAR').replace('&copy; ' + self.year, '&copy; YEAR')
        text = re.sub(r'\d{4}-\d\d-\d\d \d\d:\d\d:\d\d', 'DATETIME', text)
        text = re.sub(r'"date":"[^"]*"', '"date":"DATE"', text)  # file manager listings
        return text

    def step(self, name, browser, path, data=None, files=None, expect=None, binary=False, headers=None):
        """Run one request, save it, and record any PHP errors it caused."""
        self.step_no += 1
        before = os.path.getsize(self.errlog)
        status, headers, body = browser.request(path, data=data, files=files, headers=headers)
        time.sleep(0.05)
        with open(self.errlog, 'rb') as f:
            f.seek(before)
            errs = f.read().decode('utf-8', 'replace')
        errs = [re.sub(r'^\[[^\]]+\] ', '', l) for l in errs.splitlines() if l.strip()]
        label = '%03d-%s' % (self.step_no, re.sub(r'[^a-zA-Z0-9]+', '-', name).strip('-'))
        for e in errs:
            self.errors.append('%s: %s' % (label, e))
        ctype = headers.get('Content-Type', '')
        desc = describe_binary(ctype, body)
        if desc:
            shown = desc
        else:
            shown = self.normalise(body.decode('utf-8', 'replace'))
        location = headers.get('Location', '')
        with open(os.path.join(self.out, 'steps', label + '.txt'), 'w', encoding='utf-8') as f:
            f.write('%s %s\nstatus: %s\nlocation: %s\ncontent-type: %s\n\n%s'
                    % ('POST' if data or files else 'GET', self.normalise(path), status,
                       self.normalise(location), ctype.split(';')[0], shown))
        if self.args.display_errors and re.search(
                rb'(Fatal error|Warning|Deprecated|Notice|Parse error)(</b>)?: ', body):
            self.failures.append('%s: PHP message shown in the page' % label)
        if expect and not expect(status, headers, body):
            self.failures.append('%s: unexpected response (status %s, location %r, body %r)'
                                 % (label, status, location, body[:200]))
        return status, headers, body

    def captcha_code(self, browser):
        sid = browser.cookie('PHPSESSID')
        with open(os.path.join(self.sessions, 'sess_' + sid), encoding='latin-1') as f:
            data = f.read()
        m = re.search(r'securimage_code_value(?:\|s:\d+:"|";s:\d+:")([^"]+)"', data) \
            or re.search(r'securimage_code_value\|a:\d+:\{s:\d+:"default";s:\d+:"([^"]+)"', data)
        return m.group(1) if m else None

    # ------------------------------------------------------------ the tests
    def run(self):
        self.reset_db()
        self.start_server()
        try:
            self.frontend()
            self.contact_form()
            self.admin()
            self.security()
        finally:
            self.stop()
        self.save_results()

    def frontend(self):
        b = self.new_browser()
        ok = lambda s, h, body: s == 200
        pages = ['/', '/sectors', '/quality-systems', '/quality-systems/certificates',
                 '/size-conversion-chart', '/contact-us', '/emergency-delivery', '/products',
                 '/products/seamless-cold-drawn-tubes', '/products/welded-tubes', '/products/u-tubes',
                 '/products/alloy-steel-furnace-tubes', '/products/helical-high-finned-tubes',
                 '/products/high-frequency-helical-finned-tubes', '/products/integrally-finned-tubes',
                 '/products/studded-tube-and-pipe', '/products/longitudinal-finned-tubes',
                 '/products/corrosion-protection-e-fin', '/products/associated-products',
                 '/products/plain-stock-tubes', '/products/stock-heat-exchanger-tubes',
                 '/news-new-mill', '/news-second', '/offline']
        for p in pages:
            self.step('page ' + p, b, p, expect=ok)
        self.step('home with query', b, '/?utm_source=test&x[]=1')
        self.step('uppercase url and trailing slash', b, '/PRODUCTS/Welded-Tubes/')
        self.step('missing page', b, '/does-not-exist')
        self.step('missing child page', b, '/products/no-such-product')
        self.step('old aspx url', b, '/TSI/contact.aspx')
        self.step('link branch redirects', b, '/parent-company',
                  expect=lambda s, h, body: s in (301, 302) and 'example.com' in h.get('Location', ''))
        self.step('static css', b, '/frontend/css/main.css', expect=ok)
        self.step('static image', b, '/media/images/rendered/11.jpg', expect=ok)
        self.step('admin needs login', b, '/admin/',
                  expect=lambda s, h, body: s == 302 and 'login' in h.get('Location', ''))
        self.step('admin page needs login', b, '/admin/content-edit.htm?branchId=1',
                  expect=lambda s, h, body: s == 302)
        self.step('elfinder needs login', b, '/admin/components/elfinder/connectors/php/connector.php?cmd=open&init=true',
                  expect=lambda s, h, body: s == 302)
        self.step('image upload needs login', b, '/admin/editors/image-upload.htm?regionId=1&id=1',
                  expect=lambda s, h, body: s == 302)

    def contact_form(self):
        b = self.new_browser()
        self.step('contact page', b, '/contact-us', expect=lambda s, h, body: s == 200)
        self.step('captcha image', b, '/securimage/securimage_show.php?sid=x',
                  expect=lambda s, h, body: describe_binary('', body) is not None
                  and describe_binary('', body).split()[0] in ('PNG', 'JPEG', 'GIF'))
        code = self.captcha_code(b)
        if not code:
            self.failures.append('could not read captcha code from the session')
            code = 'none'
        self.step('captcha audio', b, '/securimage/securimage_play.php',
                  expect=lambda s, h, body: (describe_binary('', body) or '').split(' ')[1:2] == ['audio'])
        form = [('Name', 'Zoë Müller'), ('Contact', 'Zoë'), ('Email', 'zoe@example.com'),
                ('Job', 'Engineer'), ('Company', 'Трубы Ltd'), ('Country', 'España'),
                ('Message', 'Hello – please quote 100 U tubes.\nThanks'), ('Action', 'send')]
        self.step('contact form wrong captcha', b, '/contact-us', data=form + [('code', 'wrong!')],
                  expect=lambda s, h, body: b'Code incorrect' in body or s == 200)
        self.step('captcha image again', b, '/securimage/securimage_show.php?sid=y')
        code = self.captcha_code(b) or code
        self.step('contact form correct captcha', b, '/contact-us', data=form + [('code', code)],
                  expect=lambda s, h, body: s == 200)

    def login(self, email, password):
        b = self.new_browser()
        self.step('login page ' + email, b, '/admin/login.htm', expect=lambda s, h, body: s == 200)
        self.step('login ' + email, b, '/admin/login.htm',
                  data=[('email', email), ('password', password), ('remember', '1')],
                  expect=lambda s, h, body: body.strip() == b'1')
        return b

    def admin(self):
        ok = lambda s, h, body: s == 200
        redirect = lambda s, h, body: s in (301, 302)
        anon = self.new_browser()
        self.step('login wrong password', anon, '/admin/login.htm',
                  data=[('email', 'admin@example.com'), ('password', 'nope')],
                  expect=lambda s, h, body: body.strip() == b'0')

        b = self.login('admin@example.com', 'secret1')
        for p in ['/admin/', '/admin/index.htm', '/admin/structure.htm', '/admin/content-edit.htm',
                  '/admin/settings.htm', '/admin/users.htm', '/admin/users-edit.htm?userId=1',
                  '/admin/users-new.htm', '/admin/media.htm', '/admin/new-branch.htm?type=page',
                  '/admin/new-branch.htm?type=product', '/admin/new-branch.htm?type=news',
                  '/admin/version.htm', '/admin/ajax/live-slug.htm?slug=' + urllib.parse.quote('Crème Brûlée & Тест 2013')]:
            self.step('admin ' + p, b, p, expect=ok)
        self.step('new branch bad type', b, '/admin/new-branch.htm?type=bogus', expect=redirect)
        self.step('dev tasks as admin', b, '/admin/dev-tasks.htm', expect=redirect)
        for branch in [1, 2, 3, 4, 5, 6, 7, 9, 11, 18, 19, 21, 22, 24]:
            self.step('edit branch %d' % branch, b, '/admin/content-edit.htm?branchId=%d' % branch, expect=ok)

        # preview modes: admin sees the draft on the live page
        self.step('preview draft', b, '/admin/preview.htm?mode=draft&branchId=7', expect=ok)
        self.step('frontend shows draft', b, '/products/seamless-cold-drawn-tubes', expect=ok)
        self.step('frontend draft global', b, '/', expect=ok)
        self.step('edit current page', b, '/admin/content-edit.htm?action=editCurrent', expect=ok)
        self.step('preview live', b, '/admin/preview.htm?mode=live', expect=ok)
        self.step('frontend live again', b, '/products/seamless-cold-drawn-tubes', expect=ok)

        # edit content of branch 21 (page template: every editor type)
        status, h, body = self.step('edit certificates', b, '/admin/content-edit.htm?branchId=21', expect=ok)
        form = self.form_fields(body.decode('utf-8', 'replace'), 'content-edit.htm?branchId=21')
        changed = []
        for k, v in form:
            if k.startswith('content-21-') and 'colourPicker' not in k:
                if v in ('', None):
                    v = 'New text – ñ Ж'
                elif v.startswith('<') or 'Text for' in v:
                    v = v + '<p>Edited – é Ж <script>alert(1)</script><a href="http://x.test" target="_blank">x</a></p>'
            changed.append((k, v))
        self.step('save draft certificates', b, '/admin/content-edit.htm?branchId=21',
                  data=[(k, v) for k, v in changed if k != 'editorActionMode'] + [('editorActionMode', 'draft')],
                  expect=ok)
        self.step('edit certificates after draft', b, '/admin/content-edit.htm?branchId=21', expect=ok)
        status, h, body = self.step('reload certificates', b, '/admin/content-edit.htm?branchId=21', expect=ok)
        form = self.form_fields(body.decode('utf-8', 'replace'), 'content-edit.htm?branchId=21')
        self.step('publish certificates', b, '/admin/content-edit.htm?branchId=21',
                  data=[(k, v) for k, v in form if k != 'editorActionMode'] + [('editorActionMode', 'publish')],
                  expect=ok)
        self.step('certificates live', self.new_browser(), '/quality-systems/certificates', expect=ok)

        # delete draft on product 7
        status, h, body = self.step('edit product 7', b, '/admin/content-edit.htm?branchId=7', expect=ok)
        form = self.form_fields(body.decode('utf-8', 'replace'), 'content-edit.htm?branchId=7')
        self.step('delete draft product 7', b, '/admin/content-edit.htm?branchId=7',
                  data=[(k, v) for k, v in form if k != 'editorActionMode'] + [('editorActionMode', 'delete')],
                  expect=ok)

        # global content
        status, h, body = self.step('edit global', b, '/admin/content-edit.htm', expect=ok)
        form = self.form_fields(body.decode('utf-8', 'replace'), 'content-edit.htm?branchId=')
        form = [(k, ('+44 191 000 0000' if k.startswith('content-') and 'Number' not in k and v.startswith('+') else v))
                for k, v in form if k != 'editorActionMode']
        self.step('publish global', b, '/admin/content-edit.htm?branchId=', data=form + [('editorActionMode', 'publish')],
                  expect=ok)

        # meta tab
        self.step('save meta', b, '/admin/content-edit.php?branchId=19',
                  data=[('title', 'Stock Heat Exchanger Tubes – “Inox”'), ('navTitle', ''),
                        ('subTitle', 'Sub <b>title</b>'), ('urlSegment', 'Stock Tubes Ñandú Трубы'),
                        ('description', 'D' * 400), ('keywords', 'a, b, <i>c</i>'), ('action', 'submitMeta')],
                  expect=ok)
        self.step('renamed page', self.new_browser(), '/products/stock-tubes-nandu', expect=ok)
        self.step('save meta home', b, '/admin/content-edit.php?branchId=1',
                  data=[('title', 'Home'), ('navTitle', 'Home'), ('subTitle', ''), ('urlSegment', 'x'),
                        ('description', 'Home page'), ('keywords', ''), ('action', 'submitMeta'), ('home', 'true')],
                  expect=ok)

        # repeat regions
        self.step('new repeat row', b, '/admin/content-edit.htm?action=newRepeatRegion&repeatRegionId=1&templateId=7&branchId=20',
                  expect=redirect)
        status, h, body = self.step('edit sectors', b, '/admin/content-edit.htm?branchId=20&openRepeatRegionRow=1', expect=ok)
        form = self.form_fields(body.decode('utf-8', 'replace'), 'content-edit.htm?branchId=20')
        form = [(k, ('Renamed slide – ü' if k == 'repeatRegionDisplayName[]' else v)) for k, v in form
                if k != 'editorActionMode']
        self.step('update repeat rows', b, '/admin/content-edit.htm?branchId=20',
                  data=form + [('editorActionMode', 'publish'), ('deleteRepeatRegionRow[]', '2')], expect=ok)
        self.step('sectors after repeat edit', self.new_browser(), '/sectors', expect=ok)

        # downloads tab: upload, rename, delete
        self.step('upload download', b, '/admin/content-edit.htm?branchId=7',
                  data=[('id[]', '1'), ('id[]', '2'), ('id[]', 'new'),
                        ('title[]', 'Brochure 2013'), ('title[]', 'Cert'), ('title[]', 'Price list'),
                        ('description[]', 'b'), ('description[]', 'c'), ('description[]', 'New – ü'),
                        ('action', 'downloads'), ('deleteDownloadItem', ''), ('actionId', '')],
                  files={'uploadNew': ('price-list.pdf', b'%PDF-1.4 test file', 'application/pdf')}, expect=ok)
        self.step('upload same download again', b, '/admin/content-edit.htm?branchId=7',
                  data=[('id[]', 'new'), ('title[]', ''), ('description[]', ''),
                        ('action', 'downloads'), ('deleteDownloadItem', '')],
                  files={'uploadNew': ('price-list.pdf', b'%PDF-1.4 test file v2', 'application/pdf')}, expect=ok)
        self.step('delete download', b, '/admin/content-edit.htm?branchId=7',
                  data=[('id[]', '1'), ('id[]', 'new'), ('title[]', 'Brochure'), ('title[]', ''),
                        ('description[]', ''), ('description[]', ''),
                        ('action', 'downloads'), ('deleteDownloadItem', '2')], expect=ok)

        # image upload (sent with the admin's session cookie, as a browser upload is;
        # the original code also took the session id from ?sid=, which is no longer accepted)
        status, h, body = self.step('edit page 6 for image', b, '/admin/content-edit.htm?branchId=6', expect=ok)
        page6 = body.decode('utf-8', 'replace')
        m = re.search(r"image-upload\.htm\?regionId=(\d+)&id=(\d+)(?:&sid=[^'&]*)?([^']*)'", page6)
        if not m:
            self.failures.append('no image upload URL on branch 6')
        else:
            region, cid, extra = m.groups()
            up = b
            self.step('image upload', up, '/admin/editors/image-upload.htm?regionId=%s&id=%s%s'
                      % (region, cid, extra),
                      files={'Filedata': ('photo.JPG', self.jpeg, 'image/jpeg')},
                      expect=lambda s, h, body: b'Upload successful' in body)
            self.step('thumbnail made', b, '/media/images/thumb/%s.jpg' % cid,
                      expect=lambda s, h, body: s == 200 and jpeg_size(body) is not None)
            form = self.form_fields(page6, 'content-edit.htm?branchId=6')
            form = [(k, v) for k, v in form if k != 'editorActionMode']
            # a 3:2 selection inside the 267x200 thumbnail, as the Jcrop tool would send
            form += [('cropX-' + cid, '12'), ('cropY-' + cid, '9'), ('cropW-' + cid, '237'),
                     ('cropH-' + cid, '159'), ('alt-' + cid, 'Alt – é "quoted"')]
            self.step('save cropped image', b, '/admin/content-edit.htm?branchId=6',
                      data=form + [('editorActionMode', 'publish')], expect=ok)
            self.step('rendered image', b, '/media/images/rendered/%s.jpg' % cid,
                      expect=lambda s, h, body: s == 200 and jpeg_size(body) is not None)
            self.step('products page shows new image', self.new_browser(), '/products', expect=ok)
            self.step('image upload bad type', up, '/admin/editors/image-upload.htm?regionId=%s&id=%s'
                      % (region, cid), files={'Filedata': ('evil.php', b'<?php echo 1;', 'text/plain')},
                      expect=lambda s, h, body: b'Invalid file type' in body)
        # image region without crop params (empty crop fields) on branch 21
        status, h, body = self.step('edit 21 for image', b, '/admin/content-edit.htm?branchId=21', expect=ok)
        page21 = body.decode('utf-8', 'replace')
        for m in re.finditer(r"image-upload\.htm\?regionId=(\d+)&id=(\d+)(?:&sid=[^'&]*)?([^']*)'", page21):
            region, cid, extra = m.groups()
            self.step('image upload 21-%s' % region, b,
                      '/admin/editors/image-upload.htm?regionId=%s&id=%s%s' % (region, cid, extra),
                      files={'Filedata': ('photo.jpeg', self.jpeg, 'image/jpeg')},
                      expect=lambda s, h, body: b'Upload successful' in body)
        form = self.form_fields(page21, 'content-edit.htm?branchId=21')
        form = [(k, v) for k, v in form if k != 'editorActionMode']
        for cid in re.findall(r'name="alt-(\d+)"', page21):
            form += [('cropX-' + cid, ''), ('cropY-' + cid, ''), ('cropW-' + cid, ''), ('cropH-' + cid, ''),
                     ('alt-' + cid, 'alt')]
        self.step('save uncropped images', b, '/admin/content-edit.htm?branchId=21',
                  data=form + [('editorActionMode', 'publish')], expect=ok)
        self.step('page 21 images', self.new_browser(), '/quality-systems/certificates', expect=ok)
        # delete an image
        if m:
            self.step('delete image', b, '/admin/content-edit.htm?branchId=21',
                      data=form + [('deleteImage-' + cid, 'delete'), ('editorActionMode', 'publish')], expect=ok)

        # new pages
        self.step('create page', b, '/admin/new-branch.htm',
                  data=[('templateId', '5'), ('title', 'Nueva página – Тест'), ('parentId', '3'), ('addAsChild', '1'),
                        ('menuId', ''), ('action', 'submit'), ('type', 'page')], expect=redirect)
        self.step('create top page', b, '/admin/new-branch.htm',
                  data=[('templateId', '5'), ('title', 'Top level'), ('parentId', '0'), ('addAsChild', '1'),
                        ('menuId', '0'), ('action', 'submit'), ('type', 'page')], expect=redirect)
        self.step('create product', b, '/admin/new-branch.htm',
                  data=[('templateId', '10'), ('title', 'New Product <b>X</b>'), ('parentId', '6'),
                        ('menuId', '2'), ('action', 'submit'), ('type', 'product')], expect=redirect)
        self.step('new page live', self.new_browser(), '/quality-systems/nueva-pagina-test', expect=ok)
        self.step('new product live', self.new_browser(), '/products/new-product-x', expect=ok)
        self.step('edit new page', b, '/admin/content-edit.htm?branchId=26', expect=ok)

        # ordering and structure (ajax)
        self.step('branch order', b, '/admin/ajax/branch-order-update.htm?type=item2',
                  data=[('item2[]', '8'), ('item2[]', '7'), ('item2[]', '9')], expect=ok)
        self.step('branch sort setting', b, '/admin/ajax/branch-order-sort-update.htm',
                  data=[('typeId', '2'), ('orderBy', 'dateDesc')], expect=ok)
        self.step('branch sort unknown', b, '/admin/ajax/branch-order-sort-update.htm',
                  data=[('typeId', '2'), ('orderBy', 'bogus')], expect=ok)
        self.step('structure update', b, '/admin/ajax/structure-update.htm?menuId=1',
                  data=[('branchId[1]', 'root'), ('branchId[20]', 'root'), ('branchId[3]', 'root'),
                        ('branchId[21]', '3'), ('branchId[4]', 'root'), ('branchId[26]', '20')], expect=ok)
        self.step('menu after structure update', self.new_browser(), '/', expect=ok)
        self.step('structure page', b, '/admin/structure.htm', expect=ok)
        self.step('delete page from structure', b, '/admin/structure.htm',
                  data=[('action', 'delete'), ('actionId', '27')], expect=ok)
        self.step('delete page from editor', b, '/admin/content-edit.htm',
                  data=[('action', 'delete'), ('actionId', '25')], expect=ok)
        self.step('deleted page gone', self.new_browser(), '/news-second')

        # settings: offline mode on, check the public site, then back on line
        self.step('settings offline', b, '/admin/settings.htm',
                  data=[('analyticsUA', 'UA-999-1'), ('action', 'submit')], expect=ok)
        self.step('site offline for public', self.new_browser(), '/sectors', expect=ok)
        self.step('site still online for admin', b, '/sectors', expect=ok)
        self.step('admin dashboard offline', b, '/admin/', expect=ok)
        self.step('settings online', b, '/admin/settings.htm',
                  data=[('offlineMode', 'Y'), ('analyticsUA', ''), ('action', 'submit')], expect=ok)
        self.step('site online again', self.new_browser(), '/sectors', expect=ok)

        # users
        self.step('add user', b, '/admin/users-new.htm',
                  data=[('username', 'Zoë'), ('email', 'zoe@example.com'), ('password', 'pässword'),
                        ('action', 'submit')], expect=redirect)
        self.step('add duplicate user', b, '/admin/users-new.htm',
                  data=[('username', 'Zoë'), ('email', 'zoe@example.com'), ('password', 'x'),
                        ('action', 'submit')], expect=ok)
        self.step('users list', b, '/admin/users.htm?action=added', expect=ok)
        self.step('edit user', b, '/admin/users-edit.htm',
                  data=[('username', 'Zoë M'), ('email', 'zoe@example.com'), ('password', 'newpass'),
                        ('userId', '5'), ('action', 'submit')], expect=redirect)
        self.login('zoe@example.com', 'newpass')
        self.step('delete user', b, '/admin/users.htm', data=[('action', 'delete'), ('actionId', '5')], expect=ok)

        # file manager (elFinder 1.x protocol)
        conn = '/admin/components/elfinder/connectors/php/connector.php'
        status, h, body = self.step('elfinder open', b, conn + '?cmd=open&init=true&target=&tree=true', expect=ok)
        try:
            root = json.loads(body)['cwd']['hash']
        except Exception:
            root = ''
            self.failures.append('elfinder open did not return JSON')
        self.step('elfinder mkdir', b, conn + '?cmd=mkdir&current=%s&name=Fotos%%20%%C3%%BC' % root, expect=ok)
        self.step('elfinder upload', b, conn, data=[('cmd', 'upload'), ('current', root)],
                  files={'upload[]': ('tube.jpg', self.jpeg, 'image/jpeg')}, expect=ok)
        for name, data, ctype in (('anim.gif', self.gif, 'image/gif'), ('odd.png', self.odd_png, 'image/png')):
            self.step('elfinder upload ' + name, b, conn, data=[('cmd', 'upload'), ('current', root)],
                      files={'upload[]': (name, data, ctype)}, expect=ok)
        status, h, body = self.step('elfinder reopen', b, conn + '?cmd=open&target=%s' % root, expect=ok)
        files = {}
        try:
            files = {f['name']: f['hash'] for f in json.loads(body)['cdc']}
        except Exception:
            self.failures.append('elfinder reopen did not return JSON')
        if 'tube.jpg' in files:
            self.step('elfinder thumbnails', b, conn + '?cmd=tmb&current=%s' % root, expect=ok)
            self.step('elfinder rename', b, conn + '?cmd=rename&current=%s&target=%s&name=tube2.jpg'
                      % (root, files['tube.jpg']), expect=ok)
            status, h, body = self.step('elfinder reopen 2', b, conn + '?cmd=open&target=%s' % root, expect=ok)
            try:
                files = {f['name']: f['hash'] for f in json.loads(body)['cdc']}
            except Exception:
                pass
            for name in ('anim.gif', 'odd.png'):
                if name in files:
                    self.step('elfinder resize ' + name, b, conn + '?cmd=resize&current=%s&target=%s&width=33&height=21'
                              % (root, files[name]), expect=ok)
                    self.step('elfinder file ' + name, b, '/media/user/' + name, expect=ok)
            if 'tube2.jpg' in files:
                self.step('elfinder resize', b, conn + '?cmd=resize&current=%s&target=%s&width=100&height=75'
                          % (root, files['tube2.jpg']), expect=ok)
                self.step('elfinder rm', b, conn + '?cmd=rm&current=%s&targets[]=%s' % (root, files['tube2.jpg']),
                          expect=ok)
        self.step('elfinder upload php refused', b, conn, data=[('cmd', 'upload'), ('current', root)],
                  files={'upload[]': ('shell.php', b'<?php echo "x";', 'text/x-php')}, expect=ok)
        self.step('elfinder bad command', b, conn + '?cmd=nonsense', expect=ok)

        # developer user: task list
        d = self.login('dev@example.com', 'devpass')
        self.step('dev tasks', d, '/admin/dev-tasks.htm', expect=ok)
        self.step('dev add task', d, '/admin/dev-tasks.htm', data=[('new', 'Task – ü'), ('action', 'submit')], expect=ok)
        self.step('dev complete task', d, '/admin/ajax/dev-update-task.htm',
                  data=[('taskId', '1'), ('status', 'true')], expect=ok)
        self.step('dev uncomplete task', d, '/admin/ajax/dev-update-task.htm',
                  data=[('taskId', '2'), ('status', 'false')], expect=ok)

        # viewer user is sent back to the site
        v = self.login('viewer@example.com', 'viewpass')
        self.step('viewer blocked from admin', v, '/admin/',
                  expect=lambda s, h, body: s == 302 and h.get('Location') == '/')

        # remembered email on the login page, then log out
        self.step('login page remembers email', b, '/admin/login.htm', expect=ok)
        self.step('logout', b, '/admin/login.htm?action=logout', expect=ok)
        self.step('admin after logout', b, '/admin/', expect=redirect)

    def db_value(self, sql):
        return subprocess.run(['mysql', '--default-character-set=utf8mb4', '-N', DB['name'], '-e', sql],
                              capture_output=True, text=True, check=True).stdout.strip()

    def note(self, name, text, ok):
        """Record a check that is not a single request (e.g. a database value)."""
        self.step_no += 1
        label = '%03d-%s' % (self.step_no, re.sub(r'[^a-zA-Z0-9]+', '-', name).strip('-'))
        with open(os.path.join(self.out, 'steps', label + '.txt'), 'w', encoding='utf-8') as f:
            f.write('CHECK %s\n\n%s\n' % (name, text))
        if not ok:
            self.failures.append('%s: %s' % (label, text))

    def security(self):
        """Attempts against each security fix. On the original code several of
        these succeed; the failed expectations show which."""
        quote = urllib.parse.quote
        no = lambda needle: (lambda s, h, body: needle not in body)
        has = lambda needle: (lambda s, h, body: needle in body)
        missing = lambda s, h, body: s == 404

        # 1. cross-site scripting
        c = self.new_browser()
        self.step('sec contact form xss', c, '/contact-us',
                  data=[('Name', '"><script>alert("xss")</script>'), ('Message', '</textarea><script>alert(2)</script>'),
                        ('Action', 'send'), ('code', 'wrong')],
                  expect=lambda s, h, body: b'<script>alert(' not in body)
        a = self.login('admin@example.com', 'secret1')
        self.step('sec admin branchId xss', a, '/admin/content-edit.htm?branchId=' + quote('7"><script>alert(1)</script>'),
                  expect=no(b'<script>alert(1)</script>'))
        self.step('sec admin preview xss', a, '/admin/preview.htm?mode=' + quote('<script>alert(2)</script>'),
                  expect=no(b'<script>alert(2)</script>'))
        self.step('sec preview back to live', a, '/admin/preview.htm?mode=live')
        self.step('sec ajax does not echo input', a, '/admin/ajax/dev-update-task.htm',
                  data=[('taskId', '<script>alert(3)</script>'), ('status', 'true')], expect=no(b'<script>'))

        # 2. sessions
        f = self.new_browser()
        self.step('sec session id from url ignored', f, '/?sid=attackerchosen0123456789',
                  expect=lambda s, h, body: f.cookie('PHPSESSID') != 'attackerchosen0123456789')
        self.step('sec unknown session cookie replaced', f, '/admin/login.htm',
                  headers={'Cookie': 'PHPSESSID=fixated0123456789abcdef'},
                  expect=lambda s, h, body: 'fixated0123456789abcdef' not in h.get('Set-Cookie', 'fixated0123456789abcdef'))
        l = self.new_browser()
        self.step('sec session cookie flags', l, '/admin/login.htm',
                  expect=lambda s, h, body: 'httponly' in h.get('Set-Cookie', '').lower()
                  and 'samesite=lax' in h.get('Set-Cookie', '').lower())
        before = l.cookie('PHPSESSID')
        self.step('sec login', l, '/admin/login.htm', data=[('email', 'admin@example.com'), ('password', 'secret1')],
                  expect=lambda s, h, body: body.strip() == b'1')
        self.note('sec new session id at login', 'changed: %s' % (l.cookie('PHPSESSID') != before),
                  l.cookie('PHPSESSID') != before)

        # 3. passwords
        stored = self.db_value('SELECT password FROM a_users WHERE userId = 1')
        self.note('sec admin password hash upgraded', 'stored hash type: %s' % ('bcrypt' if stored.startswith('$2y$') else 'md5' if len(stored) == 32 else 'other'),
                  stored.startswith('$2y$'))
        self.step('sec short password refused', a, '/admin/users-new.htm',
                  data=[('username', 'Shorty'), ('email', 'short@example.com'), ('password', '123'), ('action', 'submit')],
                  expect=has(b'at least 6 characters'))
        self.step('sec blank password keeps old one', a, '/admin/users-edit.htm',
                  data=[('username', 'Developer'), ('email', 'dev@example.com'), ('password', ''),
                        ('userId', '2'), ('action', 'submit')])
        self.step('sec login with blank password fails', self.new_browser(), '/admin/login.htm',
                  data=[('email', 'dev@example.com'), ('password', '')], expect=lambda s, h, body: body.strip() == b'0')
        self.step('sec login with real password works', self.new_browser(), '/admin/login.htm',
                  data=[('email', 'dev@example.com'), ('password', 'devpass')], expect=lambda s, h, body: body.strip() == b'1')
        self.step('sec wrong password fails', self.new_browser(), '/admin/login.htm',
                  data=[('email', 'admin@example.com'), ('password', 'secret2')], expect=lambda s, h, body: body.strip() == b'0')

        # 4. uploads of files the server would run
        php = b'<?php echo "RAN" . "-AS-PHP";'
        self.step('sec download upload php', a, '/admin/content-edit.htm?branchId=7',
                  data=[('id[]', 'new'), ('title[]', 'x'), ('description[]', ''), ('action', 'downloads'), ('deleteDownloadItem', '')],
                  files={'uploadNew': ('shell.php', php, 'text/plain')})
        self.step('sec uploaded php not there', self.new_browser(), '/media/downloads/shell.php', expect=no(b'RAN-AS-PHP'))
        self.step('sec download upload php.jpg', a, '/admin/content-edit.htm?branchId=7',
                  data=[('id[]', 'new'), ('title[]', 'x'), ('description[]', ''), ('action', 'downloads'), ('deleteDownloadItem', '')],
                  files={'uploadNew': ('shell.php.jpg', php, 'image/jpeg')})
        conn = '/admin/components/elfinder/connectors/php/connector.php'
        status, h, body = self.step('sec elfinder open', a, conn + '?cmd=open&init=true&target=')
        try:
            root = json.loads(body)['cwd']['hash']
        except Exception:
            root = ''
        for name in ('x.php', 'x.php.jpg', 'x.phtml', '.htaccess'):
            self.step('sec elfinder upload ' + name, a, conn, data=[('cmd', 'upload'), ('current', root)],
                      files={'upload[]': (name, php, 'image/jpeg')})
        self.step('sec elfinder mkfile', a, conn + '?cmd=mkfile&current=%s&name=made.php' % root)
        self.step('sec elfinder upload ok.jpg', a, conn, data=[('cmd', 'upload'), ('current', root)],
                  files={'upload[]': ('ok.jpg', self.jpeg, 'image/jpeg')})
        status, h, body = self.step('sec elfinder list', a, conn + '?cmd=open&target=%s' % root)
        try:
            okhash = {f['name']: f['hash'] for f in json.loads(body)['cdc']}.get('ok.jpg', '')
        except Exception:
            okhash = ''
        self.step('sec elfinder rename to php', a, conn + '?cmd=rename&current=%s&target=%s&name=renamed.php' % (root, okhash))
        self.step('sec elfinder extract disabled', a, conn + '?cmd=extract&current=%s&target=%s' % (root, okhash))
        for name in ('x.php', 'x.php.jpg', 'x.phtml', 'made.php', 'renamed.php'):
            self.step('sec no php in media ' + name, self.new_browser(), '/media/user/' + name, expect=no(b'RAN-AS-PHP'))
        self.step('sec image upload path', a, '/admin/editors/image-upload.htm?regionId=10&id=' + quote('../../../evil'),
                  files={'Filedata': ('photo.jpg', self.jpeg, 'image/jpeg')})
        self.note('sec image upload stays in media', 'file outside media: %s' % os.path.exists(os.path.join(self.root, 'evil.jpg')),
                  not os.path.exists(os.path.join(self.root, 'evil.jpg')))

        # 5. scripts that ran without a login
        x = self.new_browser()
        self.step('sec editor file direct', x, '/admin/editors/text.php',
                  data=[('editorActionMode', 'delete'), ('draftContentId--', '1001')], expect=lambda s, h, body: body.strip() == b'')
        self.step('sec icon file manager', x, '/admin/components/elfinder/connectors/php/connector-icon.php?cmd=open&init=true',
                  expect=missing)
        self.step('sec uploadify script', x, '/admin/components/uploadify/uploadify.php',
                  files={'Filedata': ('a.jpg', self.jpeg, 'image/jpeg')}, expect=missing)
        self.step('sec uploadify check script', x, '/admin/components/uploadify/check-exists.php',
                  data=[('filename', 'x')], expect=missing)
        self.step('sec dashboard proxy needs login', x, '/admin/ajax/dashboard-message-proxy.htm',
                  expect=lambda s, h, body: s == 302)

    @staticmethod
    def form_fields(html, action_fragment):
        """Return the fields of the first <form> whose action contains action_fragment."""
        for m in re.finditer(r'<form([^>]*)>(.*?)</form>', html, re.S | re.I):
            if action_fragment not in m.group(1):
                continue
            body = m.group(2)
            fields = []
            for t in re.finditer(r'<(input|textarea|select)\b([^>]*)>(.*?)(?=<(?:input|textarea|select)\b|$)', body,
                                 re.S | re.I):
                tag, attrs, rest = t.group(1).lower(), t.group(2), t.group(3)
                name = re.search(r'name="([^"]*)"', attrs)
                if not name:
                    continue
                typ = (re.search(r'type="([^"]*)"', attrs) or [None, 'text'])[1].lower()
                if typ in ('checkbox', 'radio', 'file') and 'checked' not in attrs:
                    continue
                if tag == 'textarea':
                    value = rest.split('</textarea>')[0]
                    value = value.replace('&lt;', '<').replace('&gt;', '>').replace('&amp;', '&')
                elif tag == 'select':
                    opts = re.findall(r'<option[^>]*value="([^"]*)"[^>]*selected', rest.split('</select>')[0])
                    value = opts[0] if opts else (re.findall(r'<option[^>]*value="([^"]*)"', rest) or [''])[0]
                else:
                    v = re.search(r'value="([^"]*)"', attrs)
                    value = v.group(1) if v else ''
                    value = value.replace('&quot;', '"').replace('&amp;', '&')
                fields.append((name.group(1), value))
            return fields
        return []

    def save_results(self):
        with open(os.path.join(self.out, 'errors.txt'), 'w', encoding='utf-8') as f:
            f.write('\n'.join(self.errors) + ('\n' if self.errors else ''))
        with open(os.path.join(self.out, 'failures.txt'), 'w', encoding='utf-8') as f:
            f.write('\n'.join(self.failures) + ('\n' if self.failures else ''))
        mail = open(self.maillog, encoding='utf-8', errors='replace').read() if os.path.exists(self.maillog) else ''
        with open(os.path.join(self.out, 'mail.txt'), 'w', encoding='utf-8') as f:
            f.write(mail)
        dump = subprocess.run(['mysql', '--default-character-set=utf8mb4', '-N', DB['name'], '-e',
                               ';'.join('SELECT "== %s"; SELECT * FROM %s ORDER BY 1' % (t, t) for t in
                                        ['a_settings', 'a_tree', 'a_content', 'a_repeatregionsrows', 'a_downloads',
                                         'a_users', 'a_branchtypes', 'dev_tasks'])],
                              capture_output=True, text=True, check=True).stdout
        dump = re.sub(r'\d{4}-\d\d-\d\d \d\d:\d\d:\d\d', 'DATETIME', dump)
        with open(os.path.join(self.out, 'db.txt'), 'w', encoding='utf-8') as f:
            f.write(dump)
        listing = []
        for base in ('media', 'downloads', 'uploads'):
            for dirpath, dirs, names in os.walk(os.path.join(self.root, base)):
                for n in sorted(names):
                    p = os.path.join(dirpath, n)
                    rel = os.path.relpath(p, self.root)
                    data = open(p, 'rb').read()
                    orig = os.path.join(os.path.abspath(self.args.site), rel)
                    if os.path.exists(orig) and open(orig, 'rb').read() == data:
                        continue
                    listing.append('%s %s' % (rel, describe_binary('', data) or '%d bytes' % len(data)))
        with open(os.path.join(self.out, 'files.txt'), 'w') as f:
            f.write('\n'.join(sorted(listing)) + '\n')
        shutil.rmtree(self.tmp, ignore_errors=True)
        print('%s: %d steps, %d PHP log lines, %d failed expectations -> %s'
              % (self.php, self.step_no, len(self.errors), len(self.failures), self.out))


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    ap.add_argument('--php', required=True)
    ap.add_argument('--site', required=True)
    ap.add_argument('--out', required=True)
    ap.add_argument('--display-errors', action='store_true',
                    help='run with display_errors on (as some hosts do) and fail if a PHP message reaches a page')
    Runner(ap.parse_args()).run()
