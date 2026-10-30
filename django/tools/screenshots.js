// Screenshots of the same pages on the old (live) sites and on the new site,
// for tools/visual_compare.py.
//
//   node tools/screenshots.js <pages.json> <out dir> <new site host:port>
//
// pages.json: [{"language": "en", "host": "www.tubesupplyinternational.co.uk",
//               "old": "https://...", "path": "/sectors"}, ...]
// The new site is reached under the real domain names (the browser is told
// they are at <new site host:port>), so it picks the right site per domain.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const [pagesFile, outDir, newSite] = process.argv.slice(2);
const pages = JSON.parse(fs.readFileSync(pagesFile, 'utf8'));
const proxy = process.env.HTTPS_PROXY || process.env.https_proxy;
const hosts = [...new Set(pages.map(p => p.host))];

// things that move or differ on every load
const HIDE = '.homeFeature video, #siimage, .contactForm img[src*="captcha"], iframe, .cc-cookies { visibility: hidden !important; }';

async function shoot(browser, url, file) {
    const page = await browser.newPage({ viewport: { width: 1100, height: 900 } });
    // the cookie notice is shown until accepted; accept it on both
    await page.context().addCookies(hosts.map(h => ({ name: 'cc_cookie_accept', value: 'cc_cookie_accept', domain: h, path: '/' })));
    try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    } catch (e) {
        console.log(`  ${url}: ${e.message.split('\n')[0]}`);
    }
    await page.addStyleTag({ content: HIDE }).catch(() => {});
    await page.waitForTimeout(800);
    await page.screenshot({ path: file, fullPage: true });
    await page.close();
}

(async () => {
    fs.mkdirSync(outDir, { recursive: true });
    const exe = process.env.CHROMIUM_PATH || undefined;
    // the sandbox's proxy only carries https; plain http goes direct
    const oldBrowser = await chromium.launch({ executablePath: exe, proxy: proxy ? { server: proxy, bypass: 'http://*' } : undefined,
                                              args: ['--ignore-certificate-errors'] });
    const newBrowser = await chromium.launch({
        executablePath: exe,
        proxy: proxy ? { server: proxy, bypass: ['http://*', ...hosts].join(',') } : undefined,
        args: ['--ignore-certificate-errors', `--host-resolver-rules=${hosts.map(h => `MAP ${h} ${newSite}`).join(',')}`],
    });
    for (const p of pages) {
        const name = `${p.language}${p.path === '/' ? '_home' : p.path.replace(/\//g, '_')}`;
        console.log(name);
        await shoot(oldBrowser, p.old, path.join(outDir, `${name}.old.png`));
        await shoot(newBrowser, `http://${p.host}${p.path}`, path.join(outDir, `${name}.new.png`));
    }
    await oldBrowser.close();
    await newBrowser.close();
})();
