/*
 * Role-matrix enforcement for a limited admin role (qa-admin-limited-role:
 * dashboard.view + catalog.products.view/manage/export only).
 * Every static admin page must answer 403 (panel error page) unless it belongs to an allowed
 * permission; the sidebar must only list allowed sections; write endpoints of other modules 403.
 * Usage: node permissions.cjs [modeKey]
 */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');

const allowed = [/^admin$/, /^admin\/dashboard$/, /^admin\/catalog\/products(\/|$)/, /^admin\/account/, /^admin\/notifications/, /^admin\/help/, /^admin\/onboarding/];
const pages = fs.readFileSync(path.join(__dirname, 'static-pages.txt'), 'utf8').split('\n').map((s) => s.trim()).filter(Boolean);

(async () => {
    const mode = L.MODES.find((m) => m.key === (process.argv[2] || 'd-en-light'));
    const browser = await L.launch();
    const ctx = await L.newContext(browser, mode);
    const page = await L.login(ctx, 'limited');
    await L.settle(page);
    const nav = await page.$$eval('aside a[href], nav a[href]', (as) => [...new Set(as.filter((a) => a.offsetParent || a.closest('[data-nav-group]')).map((a) => new URL(a.href).pathname))]);
    const navBad = nav.filter((p) => p.startsWith('/admin') && !allowed.some((re) => re.test(p.slice(1))) && !/logout|login/.test(p));
    console.log(`${navBad.length ? 'FAIL' : 'PASS'} sidebar shows only allowed links ${JSON.stringify(navBad)}`);
    await L.shot(page, `perm/${mode.key}_limited_dashboard`);
    let fails = 0;
    for (const p of pages) {
        if (/logout|login|forgot/.test(p)) continue;
        const ok = allowed.some((re) => re.test(p));
        const r = await page.goto(`${L.BASE}/${p}`, { waitUntil: 'domcontentloaded' }).catch(() => null);
        const st = r ? r.status() : 0;
        const expect = ok ? st === 200 : st === 403;
        if (!expect) { fails++; console.log(`FAIL ${p} status=${st} expected ${ok ? 200 : 403}`); }
    }
    await page.goto(`${L.BASE}/admin/system/admins`);
    const errPage = await L.inspect(page);
    await L.shot(page, `perm/${mode.key}_limited_403`);
    console.log(`${/403|not allowed|ليس|صلاحية/i.test(await page.innerText('body')) ? 'PASS' : 'FAIL'} 403 page rendered in panel (${errPage.h1})`);
    // write attempts on other modules
    const token = await page.evaluate(() => document.querySelector('meta[name=csrf-token]')?.content);
    for (const [m, u] of [['POST', '/admin/promotions/coupons'], ['POST', '/admin/system/admins'], ['DELETE', '/admin/catalog/categories/1'], ['POST', '/admin/system/cache/clear']]) {
        const r = await ctx.request.fetch(L.BASE + u, { method: m, headers: { 'X-CSRF-TOKEN': token || '', Accept: 'application/json' }, data: {} });
        console.log(`${r.status() === 403 ? 'PASS' : 'FAIL'} ${m} ${u} -> ${r.status()}`);
    }
    console.log(`SUMMARY page-fails=${fails}`);
    await browser.close();
})();
