// Ecommet QA round 2: every public storefront page at 1440 / 375 in en and ar against a local store.
// Usage (from backend/): NODE_PATH=<playwright dir> node tests/e2e/themes/ecommet-round2.mjs [page-name…]
// Env: QA_BASE (default http://vendor.nogrgr.loc), QA_MODES (comma list), QA_SHOTS, QA_STATE (storage state).
import fs from 'node:fs';
import path from 'node:path';
import * as lib from './lib.mjs';

const BASE = process.env.QA_BASE || 'http://vendor.nogrgr.loc';
const SHOTS = process.env.QA_SHOTS || path.resolve('../docs/plan/release-qa/ecommet-round2');
const MODES = [
    { name: '1440-en', width: 1440, height: 900, lang: 'en', scheme: 'light' },
    { name: '1440-ar', width: 1440, height: 900, lang: 'ar', scheme: 'light' },
    { name: '375-en', width: 375, height: 812, lang: 'en', scheme: 'light', mobile: true },
    { name: '375-ar', width: 375, height: 812, lang: 'ar', scheme: 'light', mobile: true },
].filter((m) => !process.env.QA_MODES || process.env.QA_MODES.split(',').includes(m.name));

const PAGES = {
    home: '/',
    categories: '/categories',
    'category-all': '/category',
    category: '/category/jewelry',
    search: '/search?q=hair',
    'search-empty': '/search?q=zzqqxx',
    'best-selling': '/best-selling',
    'new-in': '/new-in',
    'top-rated': '/top-rated',
    offers: '/offers',
    product: '/products/35',
    'cart-empty': '/cart',
    checkout: '/checkout',
    favorites: '/account/wishlist',
    login: '/account/login',
    register: '/account/register',
    forgot: '/account/forgot-password',
    reset: '/account/reset-password/abc',
    about: '/pages/about',
    contact: '/pages/contact',
    faq: '/pages/faq',
    'not-found': '/nope-nothing',
};

const only = process.argv.slice(2);
fs.mkdirSync(SHOTS, { recursive: true });
const browser = await lib.launch();
const host = new URL(BASE).hostname;
for (const mode of MODES) {
    const extra = process.env.QA_STATE && fs.existsSync(process.env.QA_STATE) ? { storageState: process.env.QA_STATE } : {};
    const context = await browser.newContext({ viewport: { width: mode.width, height: mode.height }, isMobile: !!mode.mobile, hasTouch: !!mode.mobile, ...extra });
    await context.addCookies([{ name: 'locale', value: mode.lang, domain: host, path: '/' }]);
    for (const [name, url] of Object.entries(PAGES)) {
        if (only.length && !only.includes(name)) continue;
        const page = await context.newPage();
        const bag = lib.collect(page);
        try {
            const res = await page.goto(`${BASE}${url}`, { waitUntil: 'load', timeout: 45000 });
            await lib.scrollThrough(page);
            await page.waitForTimeout(900);
            const a = await lib.audit(page);
            await page.screenshot({ path: path.join(SHOTS, `${name}-${mode.name}.png`), fullPage: true, animations: 'disabled' });
            const expected = name === 'not-found' ? /404 / : /\b404\b/;
            if (name === 'not-found') bag.http = bag.http.filter((l) => !expected.test(l));
            const issues = lib.issuesOf(a, bag).filter((i) => !(name === 'not-found' && i.startsWith('console')));
            console.log(`${mode.name} ${name} ${res.status()} ${page.url().replace(BASE, '')} dir=${a.dir}${issues.length ? '\n    ' + issues.join('\n    ') : ''}`);
        } catch (e) {
            console.log(`${mode.name} ${name} FAILED ${String(e).split('\n')[0]}`);
        }
        await page.close();
    }
    await context.close();
}
await browser.close();
