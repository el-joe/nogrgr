// Shared helpers for the vendor-panel release QA scripts (Playwright, headless Chromium).
// Env: QA_BASE (store origin, default http://qastore1.localhost:8202), QA_PASSWORD (required),
// QA_EMAIL (default qa-owner1@example.test), PW_PATH (playwright module path), SHOTS (screenshot dir).
const fs = require('fs');
const path = require('path');
const PW_PATH = process.env.PW_PATH || 'playwright';
const { chromium } = require(PW_PATH);
const BASE = process.env.QA_BASE || 'http://qastore1.localhost:8202';
const CENTRAL = process.env.QA_CENTRAL || 'http://localhost:8202';
const SHOTS = process.env.SHOTS || path.resolve(__dirname, '../../../../docs/plan/release-qa/vendor');

const RAW_KEY = /\b[a-z][a-z0-9_-]*::[a-z0-9_-]+(?:\.[a-z0-9_-]+)+\b|\b(?:ui|validation|auth|passwords|pagination|vendor|admin|storefront|catalog|commerce|finance|platform|identity|marketing|notifications|reporting|support|affiliates|ai|sf|theme|themes|common|panel|panels|central|owner|affiliate|mail|enums)\.[a-z0-9_]+(?:\.[a-z0-9_]+)+\b/g;

function parseMode(mode = 'en-light-1440') {
  const [lang, theme, width] = mode.split('-');
  return { lang, theme, width: +width };
}

async function open(mode, opts = {}) {
  const { lang, theme, width } = parseMode(mode);
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width, height: width <= 400 ? 812 : 900 }, colorScheme: theme, locale: lang === 'ar' ? 'ar-SA' : 'en-US', ...opts });
  for (const b of [BASE, CENTRAL]) {
    const host = new URL(b).hostname;
    await ctx.addCookies([{ name: 'locale', value: lang, domain: host, path: '/' }, { name: 'theme', value: theme, domain: host, path: '/' }]);
  }
  const page = await ctx.newPage();
  const errs = [];
  page.on('console', (m) => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 220)); });
  page.on('pageerror', (e) => errs.push('pageerror: ' + String(e.message).slice(0, 220)));
  page.on('response', (r) => { if (r.status() >= 400 && !/favicon/.test(r.url())) errs.push(`http ${r.status()} ${r.request().method()} ${r.url().slice(0, 160)}`); });
  return { browser, ctx, page, errs, lang, theme, width };
}

async function login(page, email = process.env.QA_EMAIL || 'qa-owner1@example.test', password = process.env.QA_PASSWORD, base = BASE) {
  if (!password) throw new Error('QA_PASSWORD is required');
  await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await Promise.all([page.waitForLoadState('load').catch(() => {}), page.locator('form:has(input[name="password"]) [type="submit"]').first().click()]);
  await page.waitForTimeout(1200);
  if (/\/login/.test(page.url())) throw new Error('LOGIN FAILED ' + email + ' ' + page.url());
}

async function audit(page) {
  return page.evaluate((rk) => {
    const RAW = new RegExp(rk, 'g');
    const text = document.body ? document.body.innerText : '';
    const raw = [...new Set((text.match(RAW) || []).filter((k) => !/\.(com|test|loc|png|jpg|svg|webp|net|org|sa|io|zip|twig|json|css|js)\b|localhost|@/.test(k)))].slice(0, 8);
    const attrText = [...document.querySelectorAll('[placeholder],[title],[aria-label]')].map((e) => [e.getAttribute('placeholder'), e.getAttribute('title'), e.getAttribute('aria-label')].join(' ')).join(' ');
    const rawAttr = [...new Set(attrText.match(RAW) || [])].slice(0, 5);
    const over = document.documentElement.scrollWidth - window.innerWidth;
    let wide = [];
    if (over > 1) wide = [...document.querySelectorAll('body *')].filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && (r.right > window.innerWidth + 2 || r.left < -2) && getComputedStyle(el).position !== 'fixed'; }).slice(-4).map((el) => el.tagName.toLowerCase() + '.' + String(el.className).split(' ').slice(0, 3).join('.') + ` [${Math.round(el.getBoundingClientRect().left)}..${Math.round(el.getBoundingClientRect().right)}]`);
    const ph = /lorem ipsum|coming soon|under construction|not implemented|\bTODO\b/i.exec(text);
    const dead = [...document.querySelectorAll('a')].filter((a) => { const h = a.getAttribute('href'); return (h === '#' || h === '' || h === null) && !a.closest('[x-data]') && ![...a.attributes].some((at) => /^(data-|x-|@|aria-|role)/.test(at.name)); }).slice(0, 4).map((a) => a.outerHTML.slice(0, 120));
    return { title: document.title, raw, rawAttr, over: over > 1 ? over : 0, wide, ph: ph ? ph[0] : '', dead, dir: document.documentElement.dir, dark: document.documentElement.classList.contains('dark') };
  }, RAW_KEY.source);
}

async function shot(page, name) {
  fs.mkdirSync(SHOTS, { recursive: true });
  await page.screenshot({ path: path.join(SHOTS, name + '.png'), fullPage: false });
}

module.exports = { open, login, audit, shot, BASE, CENTRAL, SHOTS, parseMode };
