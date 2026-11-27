/*
 * Shared helpers for the admin release-QA Playwright scripts.
 * Run with: NODE_PATH=<dir with playwright>/node_modules QA_BASE=http://127.0.0.1:8201 QA_CREDS=<creds.json> node <script>.js
 * creds.json = {"super":{"email","password"},"limited":{"email","password"}} (kept outside the repo).
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = process.env.QA_BASE || 'http://127.0.0.1:8201';
const SHOTS = process.env.QA_SHOTS || path.resolve(__dirname, '../../../../docs/plan/release-qa/admin');
const CREDS = JSON.parse(fs.readFileSync(process.env.QA_CREDS, 'utf8'));

const MODES = [
    { key: 'd-en-light', width: 1440, height: 900, locale: 'en', theme: 'light' },
    { key: 'd-ar-dark', width: 1440, height: 900, locale: 'ar', theme: 'dark' },
    { key: 'm-ar-light', width: 375, height: 812, locale: 'ar', theme: 'light' },
    { key: 'm-en-dark', width: 375, height: 812, locale: 'en', theme: 'dark' },
];

async function launch() {
    return chromium.launch({ headless: true });
}

async function newContext(browser, mode) {
    const ctx = await browser.newContext({ viewport: { width: mode.width, height: mode.height }, colorScheme: mode.theme, acceptDownloads: true });
    const host = new URL(BASE).hostname;
    await ctx.addCookies([
        { name: 'locale', value: mode.locale, domain: host, path: '/' },
        { name: 'theme', value: mode.theme, domain: host, path: '/' },
    ]);
    return ctx;
}

async function login(ctx, who = 'super') {
    const page = await ctx.newPage();
    await page.goto(`${BASE}/admin/login`);
    await page.fill('input[name=email]', CREDS[who].email);
    await page.fill('input[name=password]', CREDS[who].password);
    await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), page.click('form[data-login-form] button[type=submit]')]);
    return page;
}

/** Attach listeners; returns an object collecting console / page / request errors. */
function watch(page) {
    const log = { console: [], pageErrors: [], failed: [] };
    page.on('console', (m) => { if (m.type() === 'error') log.console.push(m.text().slice(0, 300)); });
    page.on('pageerror', (e) => log.pageErrors.push(String(e).slice(0, 300)));
    page.on('response', (r) => { if (r.status() >= 400 && !r.url().includes('favicon')) log.failed.push(`${r.status()} ${r.request().method()} ${r.url().replace(BASE, '')}`); });
    page.on('requestfailed', (r) => { const f = r.failure()?.errorText || ''; if (!f.includes('ERR_ABORTED')) log.failed.push(`FAIL ${r.url().replace(BASE, '')} ${f}`); });
    return log;
}

/** DOM checks: raw keys, overflow, placeholders, dead links, dir/dark. */
async function inspect(page) {
    return page.evaluate(() => {
        const text = document.body ? document.body.innerText : '';
        const attrs = [...document.querySelectorAll('[placeholder],[title],[aria-label]')].map((e) => [e.getAttribute('placeholder'), e.getAttribute('title'), e.getAttribute('aria-label')].join(' ')).join(' ');
        const keyRe = /\b(?:[a-z0-9_-]+::)?[a-z][a-z0-9_-]+(?:\.[a-z0-9_-]+){1,}\b/g;
        const hay = text + ' ' + attrs;
        const raw = [...new Set((hay.match(keyRe) || []).filter((k) => /(^|\.)(admin|ui|panel|common|validation|fields|actions|labels|status|nav|menu|messages|catalog|commerce|billing|tenancy|identity|content|marketing|shipping|finance|localization|settings|reports|support|affiliates|storefront|notifications)\./.test(k) && !/\.(com|test|loc|png|jpg|svg|js|css|php|io|net|org|sa|json|webp)$/.test(k) && !/@/.test(k)))].slice(0, 15);
        const docW = document.documentElement.scrollWidth, winW = window.innerWidth;
        let overflowEl = null;
        if (docW > winW + 1) {
            for (const el of document.querySelectorAll('body *')) {
                const r = el.getBoundingClientRect();
                if ((r.right > winW + 1 || r.left < -1) && r.width > 0 && getComputedStyle(el).position !== 'fixed') {
                    let p = el.parentElement, clipped = false;
                    while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if (o === 'hidden' || o === 'auto' || o === 'scroll' || o === 'clip') { clipped = true; break; } p = p.parentElement; }
                    if (!clipped) { overflowEl = el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + '.' + [...el.classList].slice(0, 4).join('.'); break; }
                }
            }
        }
        // Content cut off by an overflow:hidden/clip ancestor (not inside a scroll container).
        const clipped = [];
        for (const el of document.querySelectorAll('main input:not([type=hidden]), main select, main textarea, main label, main button, main h1, main h2, main h3, main p, main td, main th, main span, main a')) {
            if (clipped.length >= 6) break;
            const r = el.getBoundingClientRect();
            if (!r.width || !r.height || !el.offsetParent || getComputedStyle(el).visibility === 'hidden') continue;
            if (r.left > -4 && r.right < winW + 4) continue;
            let p = el.parentElement, scroller = false, hidden = false;
            while (p && p !== document.body) {
                const cs = getComputedStyle(p);
                if (/(auto|scroll)/.test(cs.overflowX)) { scroller = true; break; }
                if (/(hidden|clip)/.test(cs.overflowX)) hidden = true;
                if (p.closest('[aria-hidden=true],[inert],.sr-only')) { scroller = true; break; }
                p = p.parentElement;
            }
            if (!scroller && el.closest('.dataTables_wrapper, [data-table-scroll], .swiper, [role=tablist]') === null) {
                clipped.push(el.tagName.toLowerCase() + '.' + [...el.classList].slice(0, 3).join('.') + ' "' + (el.innerText || el.value || el.name || '').trim().slice(0, 25) + '" L' + Math.round(r.left) + ' R' + Math.round(r.right));
            }
        }
        const placeholder = /(lorem ipsum|coming soon|under construction|TODO|قريباً|قيد الإنشاء)/i.exec(text);
        const dead = [...document.querySelectorAll('a[href="#"]:not([role=button]):not([data-dropdown-trigger]):not([aria-haspopup])')].filter((a) => a.offsetParent).length;
        return {
            title: document.title,
            dir: document.documentElement.getAttribute('dir'),
            lang: document.documentElement.getAttribute('lang'),
            dark: document.documentElement.classList.contains('dark'),
            raw, overflow: docW - winW, overflowEl, clipped,
            placeholder: placeholder ? placeholder[0] : null,
            deadLinks: dead,
            serverError: /Whoops|Server Error|SQLSTATE|Exception|Stack trace|Undefined (variable|array key|index)/.test(text),
            h1: (document.querySelector('h1')?.innerText || '').slice(0, 80),
            bodyLen: text.length,
        };
    });
}

async function shot(page, name, opts = {}) {
    fs.mkdirSync(SHOTS, { recursive: true });
    const file = path.join(SHOTS, `${name.replace(/[^a-z0-9._-]+/gi, '_')}.jpg`);
    await page.screenshot({ path: file, type: 'jpeg', quality: 55, fullPage: !!opts.fullPage });
    return path.relative(path.resolve(__dirname, '../../../..'), file);
}

async function settle(page) {
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(300);
}

module.exports = { BASE, SHOTS, CREDS, MODES, launch, newContext, login, watch, inspect, shot, settle };
