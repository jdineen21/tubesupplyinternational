# Tube Supply International websites – PHP 8.5 upgrade

The five Tube Supply International sites (.co.uk, .es, .fr, .pt, .ru) run on
the Atlas CMS (Climb Creative, 2012–2013), which was written for PHP 5. This
repository holds all five sites upgraded to run on **PHP 8.5** (the newest
release, 8.5.12 at the time of the upgrade).

```
sites/tubesupplyinternational.co.uk/   one folder per website, uploaded as-is
sites/tubesupplyinternational.es/
...
tests/                                 the test harness used to check the upgrade
django/                                the sites rebuilt as one Wagtail site (plus a new Arabic .sa.com site)
```

**The sites have also been rebuilt as one Django/Wagtail site** that serves
all five domains, and a new Arabic site for .sa.com, from one set of
translated pages: see
[django/README.md](django/README.md). The rest of this file is about the
PHP 8.5 upgrade of the original sites.

## What changed

**Database code (the reason the sites could not run on PHP 7 or 8).** PHP 7
removed the `mysql_*` functions. Every call (about 660 per site) now goes
through small helpers in `common/db.php` (`db_query`, `db_escape`,
`db_fetch_assoc`, ...), built on mysqli. They keep the old behaviour the site
relies on: a failed query returns false and is written to the error log
rather than stopping the page.

**Database login** moved out of `common/settings.php` into
`common/config.php`, which is not stored in git. See `config.example.php`.
It has one new, optional setting, `$dbcharset` (see "After switching" below).

**Libraries.**

| Library | Before | After | Used for |
|---|---|---|---|
| HTMLPurifier | 4.4.0 | 4.19.1 | cleaning HTML saved in the editor |
| phpThumb | 1.7.11 | 1.7.24 (patched) | image thumbnails and cropping |
| Securimage | 2.0.2 | 3.6.8 (patched) | contact form captcha |
| elFinder | 1.2 | 1.2 (patched) | admin file manager |
| Uploadify | 3.1 (Flash) | removed, replaced by `admin/js/image-upload.js` | image uploads in the page editor |

Securimage 3.6.8 is the last version that works with the contact form as it
is; 4.x needs changes to the form. Its settings (the site's small white
captcha) are in `securimage/config.inc.php`. elFinder stays on 1.2 because
2.x needs a different editor integration. The patches only fix what PHP 8.5
rejects or warns about.

**PHP 8 fixes in the site code.**

- Crashes fixed: "Create new page" in the admin (a function was called with a
  missing argument), saving an image without a crop (`'' * number`), resizing
  a GIF in the file manager, and the captcha image.
- PHP 8 reports missing form fields, empty database rows and so on as
  warnings, where PHP 5 kept quiet. These now have defaults, so the PHP error
  log stays empty across the whole test run.
- On the live site (`$liveMode = "live"` in `common/settings.php`) PHP errors
  go to the error log instead of the page, so a warning can never break a page.
- The database connection turns MySQL strict mode off for the site, as it was
  on the MySQL 5 servers the CMS was written for.

## Security fixes

Weaknesses found in the original code, each now covered by an attack test
in `tests/run_tests.py` (they succeed against the original code and fail
against this one):

- **Uploaded PHP files could run.** The file manager and the page editor's
  download upload accepted `.php` files into `media/`, where the web server
  ran them. Names a server could run (`.php`, `.phtml`, `.phar`, `.asp`,
  `.cgi`, ..., also `x.php.jpg`) are now refused on upload, rename and new
  file, and the file manager's archive extract/create commands are off.
- **Image upload path.** The image uploader built the file name from the
  URL, so it could write outside `media/`. The id is now a number.
- **Cross-site scripting.** The contact form echoed what visitors typed
  without escaping, and admin pages echoed `branchId`/`mode` from the URL;
  three admin AJAX scripts echoed their SQL (with the posted values).
- **Sessions.** Any page accepted a session id from the URL (`?sid=`), so a
  visitor could be handed a known session. That is gone (the Flash uploader
  that needed it cannot run in any current browser), unknown session ids
  are rejected, a new id is issued at login, and the cookie is `HttpOnly`
  and `SameSite=Lax` (and `Secure` over HTTPS).
- **Passwords** were stored as an md5-based hash. They now use PHP's
  `password_hash()`; each account is upgraded the next time it logs in.
  Editing a user with the password box empty used to set an empty password
  (anyone could then log in with no password); now it keeps the old one,
  and passwords under 6 characters are refused, as the form says.
- **Scripts without a login.** A second file manager connector
  (`connector-icon.php`) and Uploadify's `uploadify.php`/`check-exists.php`
  were reachable without logging in and are not used: removed. The page
  editor parts (`admin/editors/*.php`) refuse to run when opened directly.
- **Dashboard message.** The admin dashboard loaded HTML from
  atlas.climbcreative.co.uk, which no longer answers (each dashboard visit
  waited for it to time out). It is switched off, and the proxy now needs a
  login.
- Removed files that should not be on a web server: a `.bak` copy of old
  code (.es), Dreamweaver `_notes` folders (they name the old FTP server)
  and Securimage's sample database.

Not changed: admin forms have no CSRF tokens (the `SameSite=Lax` cookie
stops other sites posting them in current browsers), and HTML entered in
the editors by admins is trusted, as before.

## Differences you may notice

- **The image uploader in the page editor works again.** It used Flash
  (Uploadify 3.1), which no browser has run since January 2021, so images
  could not be uploaded into image regions. It is now a normal browser
  upload (`admin/js/image-upload.js`): a green "Browse for image..." button
  in the same place, showing the upload progress, then the same thumbnail
  and crop tool as before. If the admin session has expired it says so.
- **Image cropping in the admin now crops.** With the old phpThumb the crop
  selection was ignored in testing and the whole image was scaled down; the
  new one cuts out the selected area at the size set for the region.
- **Links that open in a new tab** get `rel="noreferrer noopener"` added when
  a page is saved (a security default in the newer HTMLPurifier).
- **Captcha audio** is a WAV file instead of MP3. (The contact page's old
  Flash audio button no longer works in any browser, before or after.)

## Deploying

1. **Back up the live site files and database first.**
2. Make sure the server has PHP 8.5 with the `mysqli`, `gd`, `mbstring` and
   `iconv` extensions (all standard).
3. Upload the contents of `sites/tubesupplyinternational.<domain>/` over the
   site. Keep the server's `media/` and `downloads/` folders – they hold the
   images and files added through the admin, which may be newer than the copy
   here.
4. Create `common/config.php` from `common/config.example.php` with the
   database login (the old values are in the old `common/settings.php`).
5. Switch the site's PHP version to 8.5 in the hosting control panel.
6. These folders must be writable by PHP, as before: `media/` (and below),
   `admin/inc/htmlpurifier/HTMLPurifier/DefinitionCache/Serializer/`.
7. Recommended: widen the password column so passwords can be stored with
   the stronger hash (until then the site keeps the old hash and says so in
   the error log; logins work either way):

   ```sql
   ALTER TABLE a_users MODIFY password VARCHAR(255) NOT NULL;
   ```
8. Recommended: tell the web server never to run scripts from the upload
   folders, as a second line of defence. The sites are served by nginx, so
   add this to each site's `server { }` block, *before* the `\.php$` block:

   ```nginx
   location ~* ^/(media|downloads)/.*\.(php[0-9]?|phtml|phar|pht)$ { deny all; }
   ```

### After switching

Open a few pages on each site, especially the Russian one, and check that
accented and Cyrillic text looks right. If it shows as garbage (for example
`Ã©` or `Ð¢`), the new server talks to MySQL in a different character set:
set `$dbcharset = 'latin1';` (or `'utf8mb4'`) in `common/config.php`.

Then log in to the admin, edit and save a page, and send a test message from
the contact form.

## Testing

`tests/` contains the harness used to check the upgrade. It runs a site with
PHP's built-in web server against a test database (reconstructed from the
code, with the live sites' page structure) and goes through 214 steps: every
public page, the contact form with captcha and email, admin login for each
user type, editing, drafts, publishing, every editor type, new pages, image
upload and cropping, downloads, users, settings, menu ordering, the file
manager, and an attempt at each of the security weaknesses above. It
records each page, the emails sent, the database at the end and every PHP
error.

`tests/browser_check.py` also drives the admin in a real browser (Chromium,
through Playwright): it logs in through the login form, uploads images into
a cropped region, a custom-thumbnail region and a slider, uses the crop
tool, publishes, checks the image on the public page, tries a non-image
and an upload after the session has expired, and fails on any JavaScript
error.

Running the original code on PHP 5.6 and the upgraded code on PHP 8.5 and
comparing the two shows the differences listed above and nothing else, with
no PHP errors, warnings or deprecation notices on PHP 8.5.

```
python3 tests/make_seed.py                        # (re)build tests/seed.sql
python3 tests/run_tests.py --php php8.5 --site sites/tubesupplyinternational.co.uk --out /tmp/new
python3 tests/run_tests.py --php php5.6 --site <original site> --out /tmp/old
python3 tests/compare.py /tmp/old /tmp/new
python3 tests/extra_checks.py --php php8.5 --site sites/tubesupplyinternational.co.uk
python3 tests/browser_check.py --php php8.5 --site sites/tubesupplyinternational.co.uk --out /tmp/browser
```

It needs MySQL/MariaDB with a database user `atlas`/`atlaspw` that may create
the `atlastest` database.
