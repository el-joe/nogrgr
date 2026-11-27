/*
 * Remote-modal + validation + export audit on every admin list page.
 * For each page: every distinct [data-remote-modal] opener (by URL pattern) is clicked, the modal
 * must show a body (not the error state). Create modals: text fields are cleared and the form is
 * submitted -> a 422 with inline field errors is expected (nothing is saved).
 * Every visible export link is requested and must answer 200 with a file.
 * Usage: node modals.cjs [modeKey]
 */
const fs = require('fs');
const path = require('path');
const L = require('./lib.cjs');

const pages = fs.readFileSync(path.join(__dirname, 'static-pages.txt'), 'utf8').split('\n').map((s) => s.trim()).filter(Boolean)
    .filter((p) => !/\/(create|edit|form)$/.test(p));
const modeKey = process.argv[2] || 'd-en-light';
const mode = L.MODES.find((m) => m.key === modeKey);
const pattern = (u) => (u || '').replace(/\/[0-9a-f]{8}-[0-9a-f-]{27,}/g, '/{uuid}').replace(/\/\d+(?=\/|$|\?)/g, '/{id}');

(async () => {
    const browser = await L.launch();
    const ctx = await L.newContext(browser, mode);
    await L.login(ctx, 'super');
    const page = await ctx.newPage();
    const out = [];
    const seen = new Set();
    for (const p of pages) {
        const url = '/' + p;
        const log = L.watch(page);
        try { await page.goto(L.BASE + url, { waitUntil: 'domcontentloaded' }); } catch (e) { continue; }
        await L.settle(page);
        await page.waitForTimeout(700);
        const openers = await page.$$eval('[data-remote-modal]', (els) => els.filter((e) => e.offsetParent !== null).map((e, i) => ({ i, url: e.dataset.url || e.getAttribute('href') || '', modal: e.dataset.remoteModal, label: (e.innerText || e.getAttribute('aria-label') || e.title || '').trim().slice(0, 40) })));
        for (const o of openers) {
            const key = pattern(o.url.replace(L.BASE, ''));
            if (seen.has(key) || /delete|destroy|impersonat/.test(key)) continue;
            seen.add(key);
            const res = { page: url, opener: o.label, url: key };
            try {
                const els = await page.$$('[data-remote-modal]');
                const vis = [];
                for (const e of els) if (await e.isVisible()) vis.push(e);
                const el = vis[o.i];
                if (!el) continue;
                await el.scrollIntoViewIfNeeded();
                await el.click({ timeout: 5000 });
                const modal = page.locator(`#${o.modal}`);
                await page.waitForFunction((id) => { const m = document.getElementById(id); if (!m) return false; const b = m.querySelector('[data-remote-body]'); const er = m.querySelector('[data-remote-error]'); return (b && b.children.length && !b.hidden) || (er && !er.hidden); }, o.modal, { timeout: 10000 });
                const st = await modal.evaluate((m) => ({ err: !m.querySelector('[data-remote-error]')?.hidden, open: m.getAttribute('aria-hidden') !== 'true', fields: m.querySelectorAll('[data-remote-body] input:not([type=hidden]),[data-remote-body] select,[data-remote-body] textarea').length, title: m.querySelector('h2')?.innerText }));
                Object.assign(res, st);
                await page.waitForTimeout(250);
                res.shot = await L.shot(page, `modal/${modeKey}/${key.replace(/\//g, '__')}`);
                if (/\/create(\?|$)/.test(key) && !st.err) {
                    await modal.evaluate((m) => m.querySelectorAll('[data-remote-body] input[type=text],[data-remote-body] input[type=email],[data-remote-body] input[type=number],[data-remote-body] input[type=url],[data-remote-body] input:not([type]),[data-remote-body] textarea').forEach((i) => { i.value = ''; }));
                    const respP = page.waitForResponse((r) => ['POST', 'PUT', 'PATCH'].includes(r.request().method()), { timeout: 8000 }).catch(() => null);
                    const sub = modal.locator('[data-remote-submit], [data-remote-body] button[type=submit]').first();
                    if (await sub.count()) {
                        await sub.click({ timeout: 4000 }).catch(() => {});
                        const r = await respP;
                        await page.waitForTimeout(500);
                        res.submitStatus = r ? r.status() : null;
                        res.inlineErrors = await modal.evaluate((m) => [...m.querySelectorAll('[data-field-error],.ui-field-error,[data-error-for],.text-danger')].filter((e) => e.offsetParent && e.innerText.trim()).map((e) => e.innerText.trim()).slice(0, 4));
                    }
                }
                await page.keyboard.press('Escape');
                await page.waitForTimeout(300);
                await page.evaluate(() => document.querySelectorAll('.ui-overlay[aria-hidden=false] [data-overlay-close]').forEach((b) => b.offsetParent && b.click()));
            } catch (e) { res.error = e.message.split('\n')[0].slice(0, 160); }
            out.push(res);
            const bad = res.error || res.err || (res.submitStatus && res.submitStatus !== 422) || (res.submitStatus === 422 && !(res.inlineErrors || []).length);
            console.log(`${bad ? 'XX' : 'ok'} modal ${url} -> ${key} ${JSON.stringify({ t: res.title, f: res.fields, s: res.submitStatus, ie: res.inlineErrors, e: res.error })}`);
        }
        const exps = await page.$$eval('a[href*="export"]', (as) => as.map((a) => a.href)).catch(() => []);
        for (const h of [...new Set(exps)]) {
            const k = pattern(h.replace(L.BASE, ''));
            if (seen.has('X' + k)) continue;
            seen.add('X' + k);
            const r = await ctx.request.get(h, { maxRedirects: 0 }).catch(() => null);
            const ct = r ? r.headers()['content-type'] : '';
            const cd = r ? r.headers()['content-disposition'] || '' : '';
            const ok = r && r.status() === 200 && (/csv|spreadsheet|excel|octet|zip|pdf/.test(ct) || cd);
            console.log(`${ok ? 'ok' : 'XX'} export ${url} ${k} ${r && r.status()} ${ct} ${cd.slice(0, 60)}`);
            out.push({ page: url, export: k, status: r && r.status(), ct, cd });
        }
        if (log.pageErrors.length || log.console.length) console.log(`XX js ${url} ${JSON.stringify([...log.pageErrors, ...log.console].slice(0, 3))}`);
        page.removeAllListeners('console'); page.removeAllListeners('pageerror'); page.removeAllListeners('response'); page.removeAllListeners('requestfailed');
    }
    fs.writeFileSync(process.env.QA_OUT || 'modals.json', JSON.stringify(out, null, 1));
    await browser.close();
})();
