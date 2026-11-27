/*
 * Crawl every parameter-free admin page (static-pages.txt) + one detail page per
 * discovered URL pattern, in the 4 modes (1440 en/light, 1440 ar/dark, 375 ar/light, 375 en/dark).
 * Output: JSON report at $QA_OUT (default crawl-report.json) + screenshots per page/mode.
 * Usage: node crawl.js [modeKey,...]
 */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');

const pagesAll = fs.readFileSync(path.join(__dirname, 'static-pages.txt'), 'utf8').split('\n').map((s) => s.trim()).filter(Boolean)
    .filter((p) => !/\/(create|edit|form)$/.test(p) || process.env.QA_INCLUDE_FRAGMENTS);
const pages = process.env.QA_ONLY ? process.env.QA_ONLY.split(',') : pagesAll;
const pattern = (u) => u.replace(/\/[0-9a-f]{8}-[0-9a-f-]{27,}/g, '/{uuid}').replace(/\/\d+(?=\/|$)/g, '/{id}');
const wanted = (process.argv[2] || L.MODES.map((m) => m.key).join(',')).split(',');

(async () => {
    const browser = await L.launch();
    const report = [];
    for (const mode of L.MODES.filter((m) => wanted.includes(m.key))) {
        const ctx = await L.newContext(browser, mode);
        await L.login(ctx, 'super');
        const page = await ctx.newPage();
        const queue = pages.map((p) => '/' + p.replace(/^\/+/, ''));
        const seen = new Set(queue.map(pattern));
        while (queue.length) {
            const url = queue.shift();
            if (/logout|login|forgot|horizon/.test(url)) continue;
            const log = L.watch(page);
            let status = 0;
            try {
                const resp = await page.goto(L.BASE + url, { waitUntil: 'domcontentloaded', timeout: 30000 });
                status = resp ? resp.status() : 0;
                await L.settle(page);
            } catch (e) { log.pageErrors.push('NAV ' + e.message.slice(0, 200)); }
            const info = await L.inspect(page).catch((e) => ({ err: e.message }));
            const ctype = await page.evaluate(() => document.contentType).catch(() => '');
            let shotFile = null;
            if (mode.key === 'd-en-light' || mode.key === 'm-ar-light' || process.env.QA_ALL_SHOTS) shotFile = await L.shot(page, `crawl/${mode.key}/${url.slice(1).replace(/\//g, '__') || 'root'}`);
            // discover detail links once per pattern
            if (mode.key === wanted[0] && ctype.includes('html') && !process.env.QA_ONLY) {
                const hrefs = await page.$$eval('a[href]', (as) => as.map((a) => a.href)).catch(() => []);
                for (const h of hrefs) {
                    if (!h.startsWith(L.BASE + '/admin/')) continue;
                    const u = new URL(h); const p = u.pathname;
                    if (/logout|export|download|print|pdf|impersonat|\/data$|horizon|preview|starter-kit/.test(p)) continue;
                    const pt = pattern(p);
                    if (pt === p || seen.has(pt)) continue;
                    seen.add(pt); queue.push(p);
                }
            }
            page.removeAllListeners('console'); page.removeAllListeners('pageerror'); page.removeAllListeners('response'); page.removeAllListeners('requestfailed');
            const row = { mode: mode.key, url, status, ctype, ...info, console: log.console, pageErrors: log.pageErrors, failed: log.failed, shot: shotFile };
            report.push(row);
            const bad = status >= 400 || info.serverError || log.pageErrors.length || log.console.length || (info.raw || []).length || info.overflow > 1 || (info.clipped || []).length || info.placeholder || log.failed.length;
            console.log(`${bad ? 'XX' : 'ok'} ${mode.key} ${status} ${url}${bad ? ' ' + JSON.stringify({ raw: info.raw, of: info.overflow, ofEl: info.overflowEl, clip: info.clipped, pe: log.pageErrors, ce: log.console, f: log.failed, ph: info.placeholder, se: info.serverError }) : ''}`);
        }
        await ctx.close();
    }
    fs.writeFileSync(process.env.QA_OUT || 'crawl-report.json', JSON.stringify(report, null, 1));
    await browser.close();
})();
