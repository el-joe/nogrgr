// Shared helpers for the storefront theme release QA (qa-themes).
// Run with: NODE_PATH=<dir with playwright> node tests/e2e/themes/<script>.mjs
// Env: QA_BASE (default http://qastore2.localhost:8203), QA_TENANT_DB (nogrgr_tenant_2_qa),
//      QA_SHOTS (screenshot root), CHROME (chromium executable), MYSQL_PWD.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
export const { chromium } = require('playwright');

export const BASE = process.env.QA_BASE || 'http://qastore2.localhost:8203';
export const CENTRAL = process.env.QA_CENTRAL || 'http://localhost:8203';
export const TENANT_DB = process.env.QA_TENANT_DB || 'nogrgr_tenant_2_qa';
export const SHOTS = process.env.QA_SHOTS || path.resolve('../docs/plan/release-qa/themes');

export const THEMES = [
    { id: 1, slug: 'ecommet', family: 'ecommet' },
    { id: 2, slug: 'elora-1', family: 'elora' },
    { id: 3, slug: 'elora-2', family: 'elora' },
    { id: 4, slug: 'elora-3', family: 'elora' },
    { id: 5, slug: 'elora-4', family: 'elora' },
    { id: 6, slug: 'elora-5', family: 'elora' },
    { id: 7, slug: 'elora-6', family: 'elora' },
    { id: 8, slug: 'souqify-1', family: 'souqify' },
    { id: 9, slug: 'souqify-2', family: 'souqify' },
    { id: 10, slug: 'souqify-3', family: 'souqify' },
    { id: 11, slug: 'souqify-4', family: 'souqify' },
    { id: 12, slug: 'souqify-5', family: 'souqify' },
    { id: 13, slug: 'souqify-6', family: 'souqify' },
];

export const MODES = [
    { name: '1440-en-light', width: 1440, height: 900, lang: 'en', scheme: 'light' },
    { name: '1440-ar-dark', width: 1440, height: 900, lang: 'ar', scheme: 'dark' },
    { name: '768-en-light', width: 768, height: 1024, lang: 'en', scheme: 'light' },
    { name: '375-ar-light', width: 375, height: 812, lang: 'ar', scheme: 'light', mobile: true },
    { name: '375-en-dark', width: 375, height: 812, lang: 'en', scheme: 'dark', mobile: true },
];

export function mysql(db, sql) {
    return execFileSync('mysql', ['-uroot', '-N', '-B', db, '-e', sql], {
        env: { ...process.env, MYSQL_PWD: process.env.MYSQL_PWD || 'root' },
    }).toString().trim();
}

/** Switch the QA store to a catalogue theme (store_settings.store_theme, decision #120). */
export function setTheme(themeId) {
    const value = JSON.stringify({ default: themeId, countries: {} });
    mysql(TENANT_DB, `INSERT INTO store_settings (\`key\`, value, created_at, updated_at) VALUES ('store_theme', '${value}', NOW(), NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()`);
}

export async function launch() {
    return chromium.launch({ executablePath: process.env.CHROME || undefined, headless: true });
}

export async function newContext(browser, mode, extra = {}) {
    const context = await browser.newContext({
        viewport: { width: mode.width, height: mode.height },
        colorScheme: mode.scheme,
        isMobile: !!mode.mobile,
        hasTouch: !!mode.mobile,
        deviceScaleFactor: 1,
        ...extra,
    });
    const host = new URL(BASE).hostname;
    await context.addCookies([
        { name: 'locale', value: mode.lang, domain: host, path: '/' },
        { name: 'locale', value: mode.lang, domain: 'localhost', path: '/' },
    ]);
    return context;
}

/** Attach console / page error / failed request collectors. */
export function collect(page) {
    const bag = { console: [], pageErrors: [], failed: [], http: [] };
    page.on('console', (m) => { if (m.type() === 'error') bag.console.push(m.text().slice(0, 300)); });
    page.on('pageerror', (e) => bag.pageErrors.push(String(e).slice(0, 300)));
    page.on('requestfailed', (r) => {
        const u = r.url();
        // ERR_ABORTED = media preload cancelled; port-less tenancy asset URLs = known dev-port env issue.
        if (r.failure()?.errorText === 'net::ERR_ABORTED' || /^http:\/\/[a-z0-9-]+\.localhost\/tenancy\//.test(u)) return;
        if (!/google|stripe|gstatic|facebook|doubleclick|paypal|ipapi|favicon/.test(u)) bag.failed.push(`${r.failure()?.errorText} ${u}`.slice(0, 300));
    });
    page.on('response', (r) => { if (r.status() >= 400 && !/favicon|stripe|paypal/.test(r.url())) bag.http.push(`${r.status()} ${r.url()}`.slice(0, 300)); });
    return bag;
}

/** DOM audit run inside the page. */
export async function audit(page) {
    return page.evaluate(() => {
        const out = {};
        const de = document.documentElement;
        out.dir = de.getAttribute('dir');
        out.lang = de.getAttribute('lang');
        out.dark = de.classList.contains('dark') || document.body.classList.contains('dark');
        out.overflowX = Math.max(0, de.scrollWidth - de.clientWidth);
        if (out.overflowX > 1) {
            const vw = de.clientWidth;
            const offenders = [];
            for (const el of document.querySelectorAll('body *')) {
                const r = el.getBoundingClientRect();
                if (r.width > 0 && (r.right > vw + 2 || r.left < -2)) {
                    const cs = getComputedStyle(el);
                    if (cs.position === 'fixed' || cs.visibility === 'hidden') continue;
                    let p = el.parentElement; let clipped = false;
                    while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if (o === 'hidden' || o === 'clip' || o === 'auto' || o === 'scroll') { clipped = true; break; } p = p.parentElement; }
                    if (!clipped) offenders.push(`${el.tagName.toLowerCase()}.${String(el.className).slice(0, 60)} [${Math.round(r.left)},${Math.round(r.right)}]`);
                    if (offenders.length > 4) break;
                }
            }
            out.overflowOffenders = offenders;
        }
        const text = document.body.innerText || '';
        const keyRe = /\b(?:[a-z_]+::)?[a-z_]+\.[a-z_]+\.[a-z_.]+\b/g;
        const raw = (text.match(keyRe) || []).filter((k) => !/\.(com|test|loc|png|jpg|webp|js|css|html|localhost)$/.test(k) && !/^www\./.test(k) && !/\d/.test(k));
        out.rawKeys = [...new Set(raw)].slice(0, 10);
        const attrs = [...document.querySelectorAll('[placeholder],[title],[aria-label]')].flatMap((el) => ['placeholder', 'title', 'aria-label'].map((a) => el.getAttribute(a) || '')).filter((v) => /^(?:[a-z_]+::)?[a-z_]+\.[a-z_]+\.[a-z_.]+$/.test(v));
        out.rawKeys.push(...new Set(attrs));
        out.placeholder = /lorem ipsum|coming soon|under construction/i.test(text) ? (text.match(/lorem ipsum|coming soon|under construction/i) || [])[0] : null;
        out.deadLinks = [...document.querySelectorAll('a[href="#"], a[href=""], a:not([href])')].filter((a) => a.offsetParent !== null && !a.getAttribute('role') && !a.dataset.action && !a.hasAttribute('x-on:click') && !a.hasAttribute('@click')).map((a) => (a.innerText || a.getAttribute('aria-label') || a.className).trim().slice(0, 40)).slice(0, 8);
        const imgs = [...document.querySelectorAll('img')].filter((i) => i.offsetParent !== null && i.getBoundingClientRect().width > 40);
        out.imgsCover = imgs.filter((i) => getComputedStyle(i).objectFit === 'cover').map((i) => i.currentSrc.split('/').pop().slice(0, 50)).slice(0, 5);
        out.imgsBroken = imgs.filter((i) => i.complete && i.naturalWidth === 0 && i.loading !== 'lazy').map((i) => i.currentSrc.slice(0, 100)).slice(0, 5);
        out.font = getComputedStyle(document.body).fontFamily.split(',')[0].replace(/["']/g, '').trim();
        const h = document.querySelector('h1,h2');
        out.headingFont = h ? getComputedStyle(h).fontFamily.split(',')[0].replace(/["']/g, '').trim() : null;
        out.title = document.title;
        out.family = document.querySelector('[data-theme-family]')?.getAttribute('data-theme-family');
        out.variant = document.querySelector('[data-theme-variant]')?.getAttribute('data-theme-variant');
        out.swipers = document.querySelectorAll('.swiper, [data-rail], [data-swiper]').length;
        return out;
    });
}

export async function scrollThrough(page) {
    await page.evaluate(async () => {
        const step = Math.round(window.innerHeight * 0.8);
        for (let y = 0; y < document.documentElement.scrollHeight; y += step) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); }
        window.scrollTo(0, 0); await new Promise((r) => setTimeout(r, 200));
    });
}

export function shotPath(theme, name) {
    const dir = path.join(SHOTS, theme);
    fs.mkdirSync(dir, { recursive: true });
    return path.join(dir, `${name}.png`);
}

export function issuesOf(a, bag) {
    const issues = [];
    if (a.overflowX > 1) issues.push(`overflowX ${a.overflowX}px ${(a.overflowOffenders || []).join(' | ')}`);
    if (a.rawKeys?.length) issues.push(`rawKeys ${a.rawKeys.join(', ')}`);
    if (a.placeholder) issues.push(`placeholder "${a.placeholder}"`);
    if (a.deadLinks?.length) issues.push(`deadLinks ${a.deadLinks.join(' / ')}`);
    if (a.imgsCover?.length) issues.push(`object-cover ${a.imgsCover.join(', ')}`);
    if (a.imgsBroken?.length) issues.push(`brokenImg ${a.imgsBroken.join(', ')}`);
    if (bag.pageErrors.length) issues.push(`pageErrors ${bag.pageErrors.join(' | ')}`);
    if (bag.console.length) issues.push(`console ${bag.console.slice(0, 3).join(' | ')}`);
    if (bag.http.length) issues.push(`http ${bag.http.slice(0, 4).join(' | ')}`);
    if (bag.failed.length) issues.push(`failed ${bag.failed.slice(0, 3).join(' | ')}`);
    return issues;
}
