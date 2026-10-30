// Drives the admin in a real browser (Chromium via Playwright): log in, upload
// an image into an image region, crop and publish it, and check the result on
// the public site. Run through tests/browser_check.py, which starts the site.
//
//   node tests/browser_test.js <base url> <output folder> <test image>

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const [base, outDir, testImage] = process.argv.slice(2);
const results = [];
const jsErrors = [];
const missing = [];

function check(name, ok, detail) {
    results.push({ name, ok: !!ok, detail: detail || '' });
    console.log(`${ok ? 'ok    ' : 'FAILED'} ${name}${ok || !detail ? '' : ' - ' + detail}`);
}

(async () => {
    fs.mkdirSync(outDir, { recursive: true });
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
    const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
    page.on('pageerror', e => jsErrors.push(`${page.url()}: ${e.message}`));
    page.on('console', m => {
        // failed downloads are listed separately (from the responses), not as script errors
        if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) jsErrors.push(`${page.url()}: console: ${m.text()}`);
    });
    page.on('response', r => { if (r.status() >= 400) missing.push(`${r.status()} ${r.url().replace(base, '')}`); });
    const shot = name => page.screenshot({ path: path.join(outDir, name + '.png'), fullPage: false });

    try {
        // 1. log in through the login form (logincheck.js posts it and redirects)
        await page.goto(base + '/admin/login.htm');
        await page.fill('#username_id', 'admin@example.com');
        await page.fill('#password', 'secret1');
        await Promise.all([
            page.waitForURL(u => !u.toString().includes('login'), { timeout: 15000 }).catch(() => {}),
            page.click('#but_login'),
        ]);
        check('log in through the form', !page.url().includes('login'), page.url());

        // 2. open the editor for a page with a cropped image region (branch 6, region 10)
        await page.goto(base + '/admin/content-edit.htm?branchId=6');
        await page.waitForLoadState('load');
        const region = '6-10-';
        const control = page.locator(`#upload-${region}`);
        const fileInput = page.locator(`input[type=file][data-upload-for="upload-${region}"]`);
        const hasInput = await fileInput.count() > 0;
        check('image region has an upload control a browser can use', hasInput,
              'no file input - the Flash uploader cannot run');
        await shot('1-editor-before-upload');

        if (hasInput) {
            check('upload button is visible', await page.locator(`[data-upload-button-for="upload-${region}"]`).isVisible());
            const thumbBefore = await page.locator(`#image-thumb-${region}`).getAttribute('src');

            // 3. upload an image
            await fileInput.setInputFiles(testImage);
            const message = page.locator(`#upload-message-${region}`);
            await message.waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
            const text = (await message.textContent() || '').trim();
            check('upload reports success', text.includes('Upload successful'), text);
            await shot('2-after-upload');

            const thumb = page.locator(`#image-thumb-${region}`);
            await page.waitForFunction(id => {
                const i = document.getElementById(id);
                return i && i.complete && i.naturalWidth > 0;
            }, `image-thumb-${region}`, { timeout: 10000 }).catch(() => {});
            const thumbAfter = await thumb.getAttribute('src');
            const thumbSize = await thumb.evaluate(i => [i.naturalWidth, i.naturalHeight]);
            check('thumbnail reloaded after upload', thumbAfter !== thumbBefore && thumbSize[0] > 0,
                  `${thumbAfter} ${thumbSize}`);

            // 4. the crop tool comes up with a selection
            await page.waitForSelector('.jcrop-holder', { timeout: 10000 }).catch(() => {});
            check('crop tool shown', await page.locator('.jcrop-holder').count() > 0);
            const cid = (await page.locator(`input[name^="alt-"]`).first().getAttribute('name')).replace('alt-', '');
            await page.waitForFunction(c => document.getElementById('cropW-' + c) && document.getElementById('cropW-' + c).value !== '',
                                       cid, { timeout: 5000 }).catch(() => {});
            const crop = await page.evaluate(c => ['X', 'Y', 'W', 'H'].map(k => (document.getElementById('crop' + k + '-' + c) || {}).value), cid);
            check('crop selection filled in', crop.every(v => v !== undefined && v !== ''), crop.join(','));
            await shot('3-crop-tool');

            // 5. a file that is not an image is refused
            const badFile = path.join(outDir, 'notes.txt');
            fs.writeFileSync(badFile, 'not an image');
            await page.waitForTimeout(2500); // let the first message fade
            await fileInput.setInputFiles(badFile);
            await page.waitForFunction(id => /Invalid file type/.test(document.getElementById(id).textContent),
                                       `upload-message-${region}`, { timeout: 10000 }).catch(() => {});
            const badText = (await message.textContent() || '').trim();
            check('non-image upload refused with a message', badText.includes('Invalid file type'), badText);
            await page.waitForTimeout(2500);

            // 6. save and publish, then check the cropped image and the public page
            await Promise.all([
                page.waitForNavigation({ timeout: 20000 }).catch(() => {}),
                page.click('a.uibutton:has-text("Save and Publish")'),
            ]);
            await page.waitForLoadState('load');
            await shot('4-after-publish');
            const rendered = await page.request.get(`${base}/media/images/rendered/${cid}.jpg`);
            check('cropped image created', rendered.ok(), String(rendered.status()));
            await page.goto(base + '/products');
            const img = page.locator(`img[src*="/media/images/rendered/${cid}.jpg"]`);
            const shown = await img.count() > 0 && await img.first().evaluate(i => i.complete && i.naturalWidth > 0);
            const size = shown ? await img.first().evaluate(i => [i.naturalWidth, i.naturalHeight]) : [];
            check('image shows on the public page at the crop size (300x200)', shown && size[0] === 300 && size[1] === 200,
                  JSON.stringify(size));
            await shot('5-public-page');
        }

        // 7. other image regions: one without cropping that also makes a custom
        //    thumbnail (branch 21, region 49), and a slider image in a repeat region
        //    (branch 20, region 50, row 1)
        for (const [branchId, region, label, extraFile] of [
            [21, '21-49-', 'custom thumbnail region', 'thumb-custom'],
            [20, '20-50-1', 'slider image in a repeat region', null],
        ]) {
            await page.goto(`${base}/admin/content-edit.htm?branchId=${branchId}&openRepeatRegionRow=1`);
            await page.waitForLoadState('load');
            const input = page.locator(`input[type=file][data-upload-for="upload-${region}"]`);
            if (await input.count() === 0) {
                check(`${label}: upload control`, false, 'missing');
                continue;
            }
            await input.setInputFiles(testImage);
            await page.waitForFunction(id => /Upload successful/.test(document.getElementById(id).textContent),
                                       `upload-message-${region}`, { timeout: 15000 }).catch(() => {});
            const msg = (await page.locator(`#upload-message-${region}`).textContent() || '').trim();
            check(`${label}: upload reports success`, msg === 'Upload successful.', msg);
            const src = await page.locator(`#image-thumb-${region}`).getAttribute('src');
            const id = (src.match(/thumb\/(\d+)\.jpg/) || [])[1];
            const thumb = await page.request.get(`${base}/media/images/thumb/${id}.jpg`);
            check(`${label}: thumbnail made`, thumb.ok(), src);
            if (extraFile) {
                const extra = await page.request.get(`${base}/media/images/${extraFile}/${id}.jpg`);
                check(`${label}: ${extraFile} image made`, extra.ok(), String(extra.status()));
            }
        }
        await shot('6-slider-upload');

        // 8. logged out (session gone) while the editor is open: a clear message, no upload
        await page.goto(`${base}/admin/content-edit.htm?branchId=6`);
        await page.waitForLoadState('load');
        await page.context().clearCookies();
        await page.locator(`input[type=file][data-upload-for="upload-6-10-"]`).setInputFiles(testImage);
        await page.waitForFunction(id => /logged out/.test(document.getElementById(id).textContent),
                                   'upload-message-6-10-', { timeout: 15000 }).catch(() => {});
        const loggedOut = (await page.locator('#upload-message-6-10-').textContent() || '').trim();
        check('logged out: upload refused with a message', /logged out/.test(loggedOut), loggedOut);
    } catch (e) {
        check('browser test ran to the end', false, e.message);
    }
    check('no JavaScript errors', jsErrors.length === 0, jsErrors.slice(0, 5).join(' | '));
    const uniqueMissing = [...new Set(missing)];
    console.log('files the pages asked for that are missing (' + uniqueMissing.length + '):\n  ' + uniqueMissing.join('\n  '));
    fs.writeFileSync(path.join(outDir, 'browser-results.json'), JSON.stringify({ results, jsErrors, missing: uniqueMissing }, null, 2));
    await browser.close();
    process.exit(results.every(r => r.ok) ? 0 : 1);
})();
