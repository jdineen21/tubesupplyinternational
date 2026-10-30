# Tube Supply International – one Wagtail site for all six domains

This folder holds the Tube Supply International websites rebuilt as one
Django site with the [Wagtail](https://wagtail.org) CMS: the five old sites
(.co.uk, .es, .fr, .pt, .ru) and a new Arabic site for Saudi Arabia
(.sa.com). The PHP sites in `../sites/` are untouched; this replaces them.

- **One set of pages, translated.** The English pages are the main tree.
  The Spanish, French, Portuguese, Russian and Arabic sites are
  translations of it: every page exists once per language, each with its
  own text, title and address.
- **Each language keeps its domain and its addresses.**
  `www.tubesupplyinternational.es/products/tubos-u` is still that page,
  in Spanish. The bare domains (`tubesupplyinternational.es`) and `http://`
  move to `https://www.…`.
- **The sites look as they did.** The page templates and stylesheets are
  the old ones; screenshots of every page of the old and new sites were
  compared pixel by pixel (see [Testing](#testing)).
- **One admin for everything** at `/admin/` on any of the domains.

## Contents

- [For editors](#for-editors)
- [What is different from the old sites](#what-is-different-from-the-old-sites)
- [The Russian and Arabic sites](#the-russian-and-arabic-sites)
- [Setting up the server](#setting-up-the-server)
- [Switching over from the old sites](#switching-over-from-the-old-sites)
- [Updates and backups](#updates-and-backups)
- [Development and testing](#development-and-testing)
- [How it is built](#how-it-is-built)

## For editors

Log in at `https://www.tubesupplyinternational.co.uk/admin/` (the same
admin is on every domain).

**Pages.** *Pages* shows one tree per language: *Home* (English),
*Inicio* (Spanish), *Page D'Acceil* (French), *Главная* (Russian),
*الرئيسية* (Arabic) and so on. Each page has a
type that matches one of the old page layouts:

| Page type | Used for | Old template |
|---|---|---|
| Home page | the home page | home.php |
| Slider page | Industrial sectors, Emergency delivery, U tubes, Associated products | page-slider(-body-image).php |
| Two column page | Quality systems | page-two-column.php |
| Size chart page | Conversion chart (the tables are fixed) | size-conversion-chart.php |
| Contact page | Contact us, with the form | contact.php |
| Product page | most products | product.php |
| Image column page | Helical high finned tubes (a picture beside each paragraph) | page-image-column.php |
| Stock page | Plain stock tubes, Stock heat exchanger tubes | two-column-lower-image.php |
| Products folder | holds the products, so their addresses start with /products/ | – |

The page **title** is the name in the menus. The **title for search
engines** (tab *Promote*) is the browser tab title and, on most pages, the
heading; *Promote* also has the address (slug), description and keywords.
*Show in menus* puts a page in the top menu or the product menu.

Text areas hold **Text** blocks (the normal editor: headings, bold, lists,
links, pictures) and some **HTML** blocks. HTML blocks keep old content that
the editor cannot show exactly – tables, coloured or underlined words,
pictures from other websites – so that it looks as it did. Edit those as
HTML, or replace them with a Text block.

**Translations.** Every page already exists in each language: find it in
that language's tree. Pages are separate: editing the English page does not
change the Spanish one. For a **new** page, its *…* menu has *Translate*,
which makes draft copies in the other languages to translate (the menu
offers it only while a language is missing). The flags at the top of each
page link to the same page in the other languages (to that site's home page
where a page is not translated).

**Site details** (*Settings › Site details*, one per domain, chosen at the
top) hold what the old sites had written into their templates: phone and
email, the tagline beside the logo, the left column's boxes, the four footer
boxes, the Google Analytics ID and the search engine verification codes.
The *Contact us*, *Emergency delivery* and footer links are chosen here too.

**Contact form messages** go to the email address in that site's Site
details (or the one set on the contact page). A copy of each is kept under
*Snippets › Contact form messages*, so nothing is lost if an email does
not arrive.

**Pictures and documents** are under *Images* and *Documents*. The old
certificate and brochure PDFs keep their old addresses
(`/downloads/TSI_Certificate.pdf` …); they are files in `downloads/`, so a
new certificate is put there on the server under the same name.

## What is different from the old sites

On purpose:

- **Accents put back in titles and menus.** The old .es and .fr sites had
  page titles typed as `'etir'es `a froid`; they now read `étirés à froid`
  (11 pages; elisions like *d'Urgence* are left alone). The import lists
  each one it changed.
- **The flags** go to the same page in the other language instead of
  always to the home page.
- **The E-fin page**, linked from the advert on the helical finned tubes
  page, was broken on every old site (it showed an empty page). It has not
  been brought over, and the advert no longer links to it.
- **The stock chart** on the stock tube pages is shown at its real
  proportions; the old template squashed it to 700×506.
- **The certificate link** on the .es/.fr/.pt sites now opens the current
  certificate (the .co.uk one); those sites had kept an older copy.
- **The French 404 page** has a second sentence, like the English one.
- The **captcha** on the contact forms is a new one (the old Securimage
  one needed PHP); its audio button (Flash) is gone.
- The contact form now also checks the required fields on the server, and
  keeps a copy of each message (see above). The visitor's address is set as
  the email's *Reply-To*.
- Scripts from other websites (fonts, jQuery) are loaded over https.
- A **sitemap** for each domain at `/sitemap.xml`.

Things that were already broken and still are: the certificate picture on
the .es/.fr/.pt *quality systems* pages
(`…co.uk/downloads/TSI_Certificate.jpg` does not exist), and **Google
Analytics**: the sites use Universal Analytics IDs (`UA-…`), which Google
switched off in 2023. Enter a Google Analytics 4 ID (`G-…`) in each site's
Site details to collect statistics again.

## The Russian and Arabic sites

**Their page text is a translation of the English pages**, not original
text, and should be proofread by a native speaker before it is relied on:

- **Russian (.ru).** The old .ru site could not be copied (it was offline,
  `403 Forbidden`, when the content was imported). Its header, left column,
  footer, contact form labels and E-fin advert come from its own templates
  and pictures, which are in this repository. The page text was translated
  from English, using the old site's Russian wording where there was some
  (e.g. *Промышленные сектора*, *Система качества*, *Таблица конверсии*).
  The pages keep the addresses the old .ru templates linked to
  (`/contact-us`, `/industrial-sectors` …).
- **Arabic (.sa.com).** There was no copy of the Saudi site, so it is new:
  every page, the header, left column, footer, contact form, 404 page and
  cookie notice translated from English. It reads **right to left**: the
  whole layout is mirrored (menus and left column on the right, buttons
  and flags on the left) by `website/static/frontend/i18n/ar/css/lang.css`,
  and the *Contact us now* and *Emergency delivery* buttons have Arabic
  words (made by `tools/make_arabic_images.py`). Page addresses are the
  English ones (`/products/u-tubes`). The E-fin advert picture is still
  the English one.

The translations are kept in `website/importer/snapshot/ru/translation.json`
and `.../ar/translation.json`; the import puts them into the pages. After the
import, edit the text in the admin like any other page.
`python manage.py export_page_text` writes all English page text in the
same form, for a translator (for example for another language).

To use the old Russian site's own text instead (if a copy of its database
turns up): run the old .ru PHP site somewhere with that database (the
upgraded PHP site in `../sites/tubesupplyinternational.ru` runs on PHP 8.5),
then, before anything is edited on the new site,

```
python manage.py fetch_old_sites ru --from ru=http://address-of-that-copy
python manage.py import_old_sites --replace --https
```

`--replace` builds all the sites again from the saved copy. Saved pages
take the place of the translated ones.

## Setting up the server

For Ubuntu 24.04 (or Debian 12); other Linux systems are similar. The
site needs Python 3.12 or later, PostgreSQL and nginx. Everything lives in
`/srv/tsi`:

```
/srv/tsi/app       this repository
/srv/tsi/venv      the Python packages
/srv/tsi/media     pictures and documents uploaded in the admin
/srv/tsi/static    stylesheets, scripts and fixed pictures (collected)
/srv/tsi/backups   daily backups
/etc/tsi/tsi.env   passwords and settings (not in git)
```

1. **Packages and a user for the site**

   ```
   sudo apt install python3 python3-venv postgresql nginx certbot git
   sudo useradd --system --home-dir /srv/tsi --create-home --shell /usr/sbin/nologin --gid www-data tsi
   sudo mkdir -p /srv/tsi/media /srv/tsi/static
   sudo git clone --branch main https://github.com/YordalStun/PhpUpgrade.git /srv/tsi/app
   sudo chown -R tsi:www-data /srv/tsi
   sudo -u tsi python3 -m venv /srv/tsi/venv
   sudo -u tsi /srv/tsi/venv/bin/pip install -r /srv/tsi/app/django/requirements.txt
   ```

   The repository is private: give the server a GitHub deploy key (or
   clone with a token). Use the branch this work has been merged into.

2. **Database**

   ```
   sudo -u postgres createuser tsi --pwprompt
   sudo -u postgres createdb tsi --owner tsi
   ```

3. **Settings.** Copy `deploy/tsi.env.example` to `/etc/tsi/tsi.env`, fill
   in the secret key, the database password and the mail server, and make
   it private:

   ```
   sudo mkdir -p /etc/tsi
   sudo cp /srv/tsi/app/django/deploy/tsi.env.example /etc/tsi/tsi.env
   sudo nano /etc/tsi/tsi.env
   sudo chmod 600 /etc/tsi/tsi.env
   ```

   The contact forms need a way to send mail: a mail server on the machine
   (e.g. postfix) or an external one (`EMAIL_HOST`, `EMAIL_PORT`,
   `EMAIL_HOST_USER`, `EMAIL_HOST_PASSWORD`, `EMAIL_USE_TLS=1`).

4. **Build the database and the sites.** `deploy/manage.sh` runs a
   `manage.py` command with the live settings, as the `tsi` user:

   ```
   cd /srv/tsi/app/django
   sudo deploy/manage.sh migrate
   sudo deploy/manage.sh createcachetable
   sudo deploy/manage.sh collectstatic --noinput
   sudo deploy/manage.sh import_old_sites --https
   sudo deploy/manage.sh createsuperuser
   ```

   `import_old_sites` builds all pages, pictures and Site details from the
   copy of the old sites saved in `website/importer/snapshot/` (it does not
   need the old sites to be online). It lists what it did, including the
   titles whose accents it put back.

5. **Run the site** (gunicorn, started by systemd):

   ```
   sudo cp deploy/tsi.service /etc/systemd/system/
   sudo systemctl enable --now tsi
   ```

6. **HTTPS and nginx.** Point the DNS of all twelve names (with and without
   `www.`, for .co.uk .es .fr .pt .ru .sa.com) at the server, then get one
   certificate for all of them:

   ```
   sudo certbot certonly --standalone --cert-name tubesupplyinternational \
       --pre-hook "systemctl stop nginx" --post-hook "systemctl start nginx" \
       -d tubesupplyinternational.co.uk -d www.tubesupplyinternational.co.uk \
       -d tubesupplyinternational.es -d www.tubesupplyinternational.es \
       -d tubesupplyinternational.fr -d www.tubesupplyinternational.fr \
       -d tubesupplyinternational.pt -d www.tubesupplyinternational.pt \
       -d tubesupplyinternational.ru -d www.tubesupplyinternational.ru \
       -d tubesupplyinternational.sa.com -d www.tubesupplyinternational.sa.com
   sudo cp deploy/nginx.conf /etc/nginx/sites-available/tubesupplyinternational
   sudo ln -s /etc/nginx/sites-available/tubesupplyinternational /etc/nginx/sites-enabled/
   sudo rm -f /etc/nginx/sites-enabled/default
   sudo nginx -t && sudo systemctl reload nginx
   ```

   certbot renews the certificate by itself (with the same hooks). If the
   server has no IPv6, remove the `listen [::]…` lines from the nginx file.

7. **Backups**: add `deploy/backup.sh` to root's crontab (`sudo crontab -e`):

   ```
   20 3 * * * /srv/tsi/app/django/deploy/backup.sh
   ```

   It keeps 14 days of database dumps and uploaded files in
   `/srv/tsi/backups`. Copy them off the server as well.

## Switching over from the old sites

1. Set up the new server as above, but get the certificate (step 6) last:
   until the DNS points at the new server, check it by adding the names to
   your computer's hosts file with the new server's address.
2. Check the pages, the admin, and send a test message from a contact form.
3. Move the DNS of the twelve names to the new server, then run step 6.
4. In Google Search Console (and Bing), submit each domain's
   `/sitemap.xml`. The page addresses are the same as before, so search
   results keep working.

Anything edited on the old sites after the content was copied
(`website/importer/snapshot/*/site.json` has the date) is not on the new
site. To copy it again before the switch: `python manage.py fetch_old_sites`
and `import_old_sites --replace --https`.

## Updates and backups

```
sudo /srv/tsi/app/django/deploy/update.sh
```

pulls the repository, installs changed packages, updates the database and
static files and restarts the site without dropping requests.

To restore a backup:

```
sudo systemctl stop tsi
sudo -u postgres dropdb tsi && sudo -u postgres createdb tsi --owner tsi
sudo cat /srv/tsi/backups/tsi-DATE.dump | sudo -u postgres pg_restore --dbname tsi
sudo tar -xzf /srv/tsi/backups/media-DATE.tar.gz -C /srv/tsi
sudo systemctl start tsi
```

Wagtail and Django publish security releases; update `requirements.txt` to
the new versions, run the tests, and deploy with `update.sh`.

## Development and testing

```
cd django
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
.venv/bin/python manage.py migrate
.venv/bin/python manage.py createcachetable
.venv/bin/python manage.py import_old_sites
.venv/bin/python manage.py createsuperuser
.venv/bin/python manage.py runserver
```

Without `DATABASE_NAME` set, development uses a SQLite file
(`db.sqlite3`), and emails are printed instead of sent. `localhost:8000`
shows the English site. For the others, add
`127.0.0.1 www.tubesupplyinternational.es` (etc.) to your hosts file and
open `http://www.tubesupplyinternational.es:8000`, or import with other
names: `import_old_sites --replace --hostname es=es.localhost …`.

### Testing

- `python manage.py test website` (22 tests, about a minute) imports
  the saved copy of the old sites and checks **every page of the four
  sites** against it – title, description, keywords, menus, all visible
  text, pictures and links – and the translations, the Russian and
  right-to-left Arabic sites (including every link on their pages), the
  contact form (labels in each language, email, wrong code, missing
  fields, mail server down), the 404 pages, the sitemaps and editing. Run
  it on PostgreSQL too by setting the `DATABASE_*` variables.
- `tools/visual_compare.py` screenshots every page on the live old site
  and the new one and measures the difference (needs Node and Playwright).
  Before handing over, every page matched pixel for pixel or to within
  anti-aliasing, apart from the intended differences above.
- `tools/admin_browser_test.js` logs into the admin in a real browser,
  edits and publishes a page, uploads a picture into it, edits a site's
  details, and checks each change on the public sites.

The deployment files were tried out on a test machine with PostgreSQL 16,
nginx 1.24 and gunicorn: redirects for all the names, every page over
https, static files, uploads, the old PDFs, the contact form with its
captcha, and the backup script.

## How it is built

```
tsi/settings/            base.py (shared), dev.py, production.py (from /etc/tsi/tsi.env)
website/models.py        page types, Site details, contact form messages
website/templates/       the old PHP templates as Django templates
website/static/frontend/ the old sites' stylesheets, scripts and pictures;
                         i18n/<language>/ has the pictures with words on them
                         (and, for Arabic, the right-to-left stylesheet)
website/locale/          the fixed words (contact form, 404) in each language
website/middleware.py    sets each request's language from its domain
website/apps.py          a page belongs to its own language's domain only
website/importer/        fetch.py saves the old sites; atlas.py reads each
                         editable area back out of a page using the old
                         templates; load.py builds the Wagtail site
website/importer/snapshot/  the saved copy of the old sites (pages, pictures, and in
                         old-site/ their page templates, which the import reads);
                         ru/ and ar/translation.json: the Russian and Arabic text
website/tests/           the tests and the page-by-page comparison
deploy/                  nginx, systemd, settings template, manage/update/backup scripts
downloads/               the old /downloads/ PDFs
tools/                   visual comparison and admin browser test
```
