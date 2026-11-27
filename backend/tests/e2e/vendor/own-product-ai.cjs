// Own product edit page: open each header tool (AI translate, AI price finder, AI social posts, video ad, flash sale) and run it once.
const { open, login, shot, BASE } = require('./lib.cjs');
const id = process.argv[2]; const mode = process.argv[3] || 'en-light-1440';
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  for (const label of ['Translate with AI', 'AI price finder', 'AI social posts', 'Create a video ad', 'Add to flash sale']) {
    await page.goto(BASE + `/admin/catalog/own-products/${id}/edit`); await page.waitForTimeout(1000);
    errs.length = 0;
    const btn = page.getByRole('button', { name: label }).or(page.getByRole('link', { name: label })).first();
    const href = await btn.getAttribute('href').catch(() => null);
    await btn.click(); await page.waitForTimeout(2500);
    const dlg = page.locator('[role=dialog]:visible, dialog[open]').last();
    const txt = (await dlg.count()) ? (await dlg.innerText()).replace(/\n+/g, ' | ').slice(0, 500) : '(no dialog) url=' + page.url();
    console.log(`\n== ${label} (href=${href})\n${txt}`);
    await shot(page, `own-ai-${label.replace(/\W+/g, '-').toLowerCase()}-${mode}`);
    console.log('errs:', [...new Set(errs)].join(' || '));
  }
  await browser.close();
})();
