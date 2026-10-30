// Drives the Wagtail admin in a real browser (Chromium via Playwright):
// log in, edit and publish a page, upload a picture into a page, edit a
// site's details, and check each change on the public site.
//
//   node tools/admin_browser_test.js <new site host:port> <out dir> <test image> <username> <password> <page id>
//
// <page id>: the Spanish "Tubos Soldados" page, e.g. from
//   manage.py shell -c "from website.models import ProductPage; print(ProductPage.objects.get(locale__language_code='es', slug='tubos-soldados').id)"
// The test image must not be in the image library already (Wagtail asks about duplicates).
//
// The site is reached under its real domain names (mapped to host:port), as
// in tools/screenshots.js. Run it against a copy of the site: it changes pages.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const [server, outDir, testImage, username, password, pageId] = process.argv.slice(2);
const UK = 'http://www.tubesupplyinternational.co.uk';
const ES = 'http://www.tubesupplyinternational.es';
const hosts = ['co.uk', 'es', 'fr', 'pt', 'ru'].map(t => `www.tubesupplyinternational.${t}`);
const results = [];
const jsErrors = [];

// "Publish" is in the menu beside "Save draft" at the bottom of the editor
async function publishPage(page) {
    await page.locator('footer [data-w-dropdown-target="toggle"]').first().click();
    await page.locator('button[name="action-publish"]').click();
}

function check(name, ok, detail) {
    results.push({ name, ok: !!ok, detail: detail || '' });
    console.log(`${ok ? 'ok    ' : 'FAILED'} ${name}${ok || !detail ? '' : ' - ' + detail}`);
}

(async () => {
    fs.mkdirSync(outDir, { recursive: true });
    const browser = await chromium.launch({
        executablePath: process.env.CHROMIUM_PATH || undefined,
        args: [`--host-resolver-rules=${hosts.map(h => `MAP ${h} ${server}`).join(',')}`],
    });
    const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
    page.on('pageerror', e => jsErrors.push(`${page.url()}: ${e.message}`));
    page.on('console', m => {
        // (Cross-Origin-Opener-Policy: only a warning because the test runs over plain http)
        if (m.type() === 'error' && !/Failed to load resource|Cross-Origin-Opener-Policy/.test(m.text())) {
            jsErrors.push(`${page.url()}: ${m.text()}`);
        }
    });
    const shot = name => page.screenshot({ path: path.join(outDir, name + '.png') });
    const stamp = `Edited in the browser test ${Date.now()}`;

    try {
        // 1. log in
        await page.goto(UK + '/admin/login/');
        await page.fill('#id_username', username);
        await page.fill('#id_password', password);
        await Promise.all([page.waitForURL(u => !u.toString().includes('/login'), { timeout: 15000 }), page.click('button[type=submit]')]);
        check('log in', page.url().endsWith('/admin/'), page.url());
        await shot('1-dashboard');

        // 2. the page search finds the page; open it in the editor
        await page.goto(UK + '/admin/pages/search/?q=Tubos+Soldados');
        const link = page.locator(`a[href$="/admin/pages/${pageId}/edit/"]`).first();
        check('page search finds the Spanish page', await link.count() > 0);
        await link.click();
        await page.waitForSelector('.Draftail-Editor', { timeout: 20000 });
        check('page editor opens with the text editor', await page.locator('.Draftail-Editor').count() > 0);
        await shot('2-editor');

        // "View live" goes to the Spanish domain
        const live = await page.locator('a.page-status-tag:not(.w-hidden)').first().getAttribute('href').catch(() => null);
        check('"Visit the live page" link is on the .es domain', live && live.startsWith(ES + '/products/tubos-soldados'), live);

        // 3. type into the first text block and publish
        const editor = page.locator('.Draftail-Editor [contenteditable=true]').first();
        await editor.locator('[data-block="true"]').first().click();
        await page.keyboard.press('Home');
        await page.keyboard.type(stamp + ' ', { delay: 5 });
        await page.waitForTimeout(800);  // the editor stores its content as it changes
        check('typed text is in the editor', (await editor.textContent()).includes(stamp));
        await publishPage(page);
        await page.waitForURL(u => !u.toString().endsWith('/edit/'), { timeout: 20000 }).catch(() => {});
        await shot('3-after-publish');
        const pub = await page.goto(ES + '/products/tubos-soldados');
        const body = await page.content();
        check('published text shows on the Spanish site', pub.status() === 200 && body.includes(stamp), String(pub.status()));
        await shot('4-public-page');

        // 4. upload a new picture into the same page's picture field
        await page.goto(`${UK}/admin/pages/${pageId}/edit/`);
        await page.waitForSelector('.Draftail-Editor');
        // the picture field's "..." menu > Change image > Upload
        await page.locator('[aria-describedby="id_image-title"]').click();
        await page.locator('[data-chooser-action-choose]', { hasText: /Change/i }).first().click();
        const modal = page.locator('.modal-dialog');
        await modal.waitFor({ timeout: 15000 });
        await modal.getByRole('tab', { name: 'Upload' }).click();
        await modal.locator('input[type=file]').setInputFiles(testImage);
        await modal.locator('input[name$="title"]').fill('Browser test picture');
        await shot('5a-upload-form');
        await modal.locator('form button[type=submit]').last().click();
        await modal.waitFor({ state: 'detached', timeout: 20000 }).catch(() => {});
        check('uploaded picture is chosen for the page',
              (await page.locator('#id_image-title').textContent()).includes('Browser test picture'));
        await shot('5-image-chosen');
        await publishPage(page);
        await page.waitForURL(u => !u.toString().endsWith('/edit/'), { timeout: 20000 }).catch(() => {});
        await page.goto(ES + '/products/tubos-soldados');
        const img = page.locator('img.pageImg');
        const src = await img.getAttribute('src').catch(() => '');
        const shown = src && await img.evaluate(i => i.complete && i.naturalWidth > 0);
        check('uploaded picture shows on the public page', shown && /browser-test/.test(src), src);
        await shot('6-public-with-new-picture');

        // 5. edit the French site's details (Settings > Site details)
        await page.goto(UK + '/admin/settings/website/sitedetails/');
        await Promise.all([page.waitForURL(u => !u.toString().endsWith('/sitedetails/2/'), { timeout: 15000 }),
                           page.selectOption('#id_site', { label: 'Tube Supply International (fr)' })]);
        await page.waitForSelector('#id_telephone', { timeout: 15000 });
        const url = page.url();
        await page.fill('#id_telephone', '+33 1 23 45 67 89');
        await page.locator('button[type=submit]', { hasText: /Save/i }).first().click();
        await page.waitForLoadState('load');
        await shot('7-site-details');
        await page.goto('http://www.tubesupplyinternational.fr/');
        check('site details change shows on the French site only',
              (await page.content()).includes('+33 1 23 45 67 89'), url);
        await page.goto(UK + '/');
        check('... and not on the English site', !(await page.content()).includes('+33 1 23 45 67 89'));
    } catch (e) {
        check('browser test ran to the end', false, e.message.split('\n')[0]);
        await shot('error').catch(() => {});
    }
    check('no JavaScript errors', jsErrors.length === 0, jsErrors.slice(0, 5).join(' | '));
    fs.writeFileSync(path.join(outDir, 'admin-results.json'), JSON.stringify({ results, jsErrors }, null, 2));
    await browser.close();
    process.exit(results.every(r => r.ok) ? 0 : 1);
})();
