// Page crawl per theme: every storefront page × MODES, DOM audit + screenshots.
// Usage: node tests/e2e/themes/crawl.mjs [theme-slug ...]   (default: all 13)
// Output: JSON per theme in $QA_OUT (default ./crawl-results), screenshots in docs/plan/release-qa/themes/<slug>/
import fs from 'node:fs';
import path from 'node:path';
import { BASE, MODES, THEMES, audit, collect, issuesOf, launch, newContext, scrollThrough, setTheme, shotPath } from './lib.mjs';

const OUT = process.env.QA_OUT || path.resolve('crawl-results');
fs.mkdirSync(OUT, { recursive: true });

const PAGES = [
    ['home', '/'],
    ['categories', '/categories'],
    ['category', '/category/electronics'],
    ['category-sorted', '/category/electronics?sort=price_asc'],
    ['search', '/search?q=demo'],
    ['offers', '/offers'],
    ['new-in', '/new-in'],
    ['best-selling', '/best-selling'],
    ['top-rated', '/top-rated'],
    ['product', '/products/15/demo-smartphone'],
    ['product-2', '/products/1/qa-phone-x'],
    ['cart-empty', '/cart'],
    ['login', '/account/login'],
    ['register', '/account/register'],
    ['forgot', '/account/forgot-password'],
    ['page-about', '/pages/about'],
    ['page-faq', '/pages/faq'],
    ['page-contact', '/pages/contact'],
    ['not-found', '/products/999999/nope'],
];
const SHOT = new Set(['home', 'category', 'product', 'cart-empty', 'login', 'search', 'not-found']);
const modes = process.env.QA_MODES ? MODES.filter((m) => process.env.QA_MODES.split(',').includes(m.name)) : MODES;

const wanted = process.argv.slice(2);
// `twig-starter`: crawl the store's live custom Twig theme as it is (no theme switch, no family check).
const themes = wanted.length ? [...THEMES, { id: null, slug: 'twig-starter', family: null }].filter((t) => wanted.includes(t.slug)) : THEMES;
const browser = await launch();

for (const theme of themes) {
    if (theme.id) setTheme(theme.id);
    const results = [];
    for (const mode of modes) {
        const context = await newContext(browser, mode);
        const page = await context.newPage();
        for (const [name, url] of PAGES) {
            const bag = collect(page);
            let status = 0;
            try {
                const res = await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 45000 });
                status = res?.status() ?? 0;
            } catch (e) {
                bag.pageErrors.push(`goto ${String(e).slice(0, 120)}`);
            }
            await scrollThrough(page).catch(() => {});
            const a = await audit(page).catch((e) => ({ error: String(e) }));
            const issues = issuesOf(a, bag);
            if (name === 'not-found') {
                if (status !== 404) issues.push(`expected 404 got ${status}`);
                const i = issues.findIndex((x) => x.startsWith('http ') || x.startsWith('console '));
                // the 404 document itself is expected
                for (let k = issues.length - 1; k >= 0; k--) if (/^(http|console) /.test(issues[k]) && /404/.test(issues[k]) && !/,|\|/.test(issues[k].replace(/^\w+ /, ''))) issues.splice(k, 1);
                void i;
            } else if (status !== 200) issues.push(`status ${status}`);
            if (theme.family && a.family && a.family !== theme.family) issues.push(`family ${a.family} != ${theme.family}`);
            if (a.dir !== (mode.lang === 'ar' ? 'rtl' : 'ltr')) issues.push(`dir ${a.dir}`);
            const expectFont = mode.lang === 'ar' ? /noto sans arabic|ibm plex sans arabic|tajawal/i : /outfit|inter|manrope/i;
            if (theme.family && a.font && !expectFont.test(a.font)) issues.push(`font ${a.font}`);
            results.push({ theme: theme.slug, mode: mode.name, page: name, status, font: a.font, headingFont: a.headingFont, variant: a.variant, issues });
            if (SHOT.has(name)) {
                await page.screenshot({ path: shotPath(theme.slug, `${name}-${mode.name}`), fullPage: name === 'home' || name === 'product' }).catch(() => {});
            }
            page.removeAllListeners('console'); page.removeAllListeners('pageerror'); page.removeAllListeners('requestfailed'); page.removeAllListeners('response');
        }
        await context.close();
    }
    fs.writeFileSync(path.join(OUT, `${theme.slug}.json`), JSON.stringify(results, null, 1));
    const bad = results.filter((r) => r.issues.length);
    console.log(`${theme.slug}: ${results.length} checks, ${bad.length} with issues`);
    for (const r of bad) console.log(`  [${r.mode}] ${r.page}: ${r.issues.join(' ;; ')}`);
}
await browser.close();
