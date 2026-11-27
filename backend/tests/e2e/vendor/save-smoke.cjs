// Re-save every "Save" form on settings / store pages unchanged: expects no 4xx/5xx and a success toast or redirect.
const { open, login, BASE } = require('./lib.cjs');
const mode = process.argv[2] || 'en-light-1440';
const PAGES = (process.argv[3] || '').split(',').filter(Boolean);
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  const posts = [];
  page.on('response', (r) => { const m = r.request().method(); if (['POST', 'PUT', 'PATCH'].includes(m) && r.url().includes('/admin/')) posts.push(`${r.status()} ${m} ${r.url().replace(BASE, '')}`); });
  for (const u of PAGES) {
    await page.goto(BASE + u); await page.waitForTimeout(1200);
    const n = await page.locator('button[type=submit]:visible').filter({ hasText: /Save|Update|حفظ/ }).count();
    for (let i = 0; i < n; i++) {
      await page.goto(BASE + u); await page.waitForTimeout(1200);
      errs.length = 0; posts.length = 0;
      const b = page.locator('button[type=submit]:visible').filter({ hasText: /Save|Update|حفظ/ }).nth(i);
      await b.click(); await page.waitForTimeout(2500);
      const toast = (await page.locator('[role=status]:visible, [role=alert]:visible, [data-toast]:visible').allInnerTexts().catch(() => [])).join(' | ').replace(/\s+/g, ' ').slice(0, 160);
      const fieldErr = await page.evaluate(() => [...document.querySelectorAll('[data-field-error]')].map((e) => e.innerText.trim()).filter((t) => t && t !== '*').slice(0, 4));
      console.log(`${u} #${i}: ${posts.join(', ') || '(no request)'} toast="${toast}" fieldErr=${JSON.stringify(fieldErr)} ${errs.filter((e) => /http 5|pageerror/.test(e)).join(' ')}`);
    }
    if (!n) console.log(`${u}: no Save button`);
  }
  await browser.close();
})();
