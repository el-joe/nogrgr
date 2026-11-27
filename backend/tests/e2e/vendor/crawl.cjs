// Crawl every parameter-free vendor GET page (+ followed detail links) in one mode and audit it.
// node crawl.cjs <mode> [follow] ; mode = en-light-1440 | ar-dark-1440 | ar-light-375 | en-dark-375
const fs = require('fs');
const path = require('path');
const { open, login, audit, BASE } = require('./lib.cjs');
const [mode = 'en-light-1440', follow = ''] = process.argv.slice(2);
const pages = fs.readFileSync(path.join(__dirname, 'pages.txt'), 'utf8').split('\n').map((s) => s.trim()).filter(Boolean);
const norm = (u) => u.replace(/\/\d+(?=\/|$|\?)/g, '/{id}').replace(/\/[0-9a-f-]{20,}(?=\/|$)/g, '/{uuid}').replace(/\?.*$/, '');
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  const queue = [...pages];
  const seen = new Set(queue.map(norm));
  const out = [];
  for (let i = 0; i < queue.length && i < 400; i++) {
    const u = queue[i];
    errs.length = 0;
    let status = 0;
    try { const r = await page.goto(BASE + u, { waitUntil: 'load', timeout: 45000 }); status = r ? r.status() : 0; await page.waitForTimeout(900); } catch (e) { errs.push('nav: ' + e.message.slice(0, 120)); }
    let a = {};
    try { a = await audit(page); } catch (e) { errs.push('eval ' + e.message.slice(0, 80)); }
    const bad = status >= 400 || errs.length || a.raw?.length || a.rawAttr?.length || a.over || a.ph || a.dead?.length;
    const rec = { u, status, final: page.url().replace(BASE, ''), errs: [...new Set(errs)], ...a };
    out.push(rec);
    console.log((bad ? 'BAD ' : 'ok  ') + status + ' ' + u + (rec.final !== u ? ' -> ' + rec.final : '') + (bad ? ' ' + JSON.stringify({ e: rec.errs, raw: a.raw, ra: a.rawAttr, over: a.over, wide: a.wide, ph: a.ph, dead: a.dead }) : ''));
    if (follow) {
      const links = await page.$$eval('a[href]', (as) => as.map((x) => x.href)).catch(() => []);
      for (const l of links) {
        if (!l.startsWith(BASE + '/admin')) continue;
        const p = l.slice(BASE.length).split('#')[0];
        if (/logout|export|download|print|\/data|delete|destroy|lang=|impersonation|starter-kit|\/open$/.test(p)) continue;
        const n = norm(p);
        if (seen.has(n)) continue;
        seen.add(n); queue.push(p);
      }
    }
  }
  fs.writeFileSync(path.join(process.env.OUT || '/tmp', `vendor-crawl-${mode}.json`), JSON.stringify(out, null, 1));
  await browser.close();
})();
