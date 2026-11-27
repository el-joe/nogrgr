// Vendor return processing: add internal note, reply, then walk the primary action (Approve → … → Refund) until closed.
const { open, login, shot, BASE } = require('./lib.cjs');
const id = process.argv[2]; const mode = process.argv[3] || 'en-light-1440';
const dlg = (page) => page.locator('[role=dialog]:visible, dialog[open]').last();
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  const url = BASE + '/admin/sales/returns/' + id;
  await page.goto(url); await page.waitForTimeout(1000);
  await page.fill('textarea[name=body], textarea[placeholder*="note" i]', 'Checked photos, approving.').catch(() => {});
  for (let i = 0; i < 6; i++) {
    await page.goto(url); await page.waitForTimeout(1200);
    const status = await page.locator('h1 + *, [data-status]').first().innerText().catch(() => '');
    const acts = await page.$$eval('[data-url*="/actions/"]', (as) => as.map((a) => a.innerText.trim() + '|' + a.dataset.url.split('/actions/')[1]));
    console.log(`STEP ${i} status=${status.trim()} actions=${JSON.stringify(acts)}`);
    const primary = acts.find((a) => !/reject|request_info/.test(a));
    if (!primary) break;
    await page.locator(`[data-url$="/actions/${primary.split('|')[1]}"]`).first().click(); await page.waitForTimeout(1500);
    const d = dlg(page);
    const fields = await d.evaluate((el) => [...el.querySelectorAll('input,select,textarea')].filter((e) => e.type !== 'hidden').map((e) => `${e.name}[${e.type}]${e.required ? '*' : ''}${e.tagName === 'SELECT' ? '=' + [...e.options].map((o) => o.value).join('/') : ''}`));
    console.log('  dialog:', (await d.innerText()).replace(/\n+/g, ' | ').slice(0, 300), '\n  fields:', fields.join(', '));
    for (const ta of await d.locator('textarea').all()) await ta.fill('QA vendor release test step ' + i + '.');
    for (const s of await d.locator('select').all()) { await s.evaluate((x) => { if (!x.value && x.options.length > 1) { x.value = x.options[1].value; x.dispatchEvent(new Event('change', { bubbles: true })); if (window.jQuery) window.jQuery(x).trigger('change'); } }); }
    for (const c of await d.locator('input[type=checkbox][required]').all()) await c.check({ force: true });
    await shot(page, `ret-02-action-${i}-${mode}`);
    await d.locator('button[type=submit]').last().click(); await page.waitForTimeout(3500);
    const still = await page.locator('[role=dialog]:visible, dialog[open]').count();
    if (still) { console.log('  STILL OPEN:', (await dlg(page).innerText()).replace(/\n+/g, ' | ').slice(0, 300)); break; }
  }
  await page.goto(url); await page.waitForTimeout(1000);
  console.log('FINAL', (await page.locator('main, #main').first().innerText()).replace(/\n+/g, ' | ').slice(0, 700));
  await shot(page, `ret-03-final-${mode}`);
  console.log('errs:', [...new Set(errs)].join(' || '));
  await browser.close();
})();
