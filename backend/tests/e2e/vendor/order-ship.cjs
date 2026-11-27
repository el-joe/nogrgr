// Vendor order (own product): Update status → shipped (with tracking) → delivered; checks the timeline + storefront mail queued.
const { open, login, shot, BASE } = require('./lib.cjs');
const uuid = process.argv[2]; const mode = process.argv[3] || 'en-light-1440';
const dlg = (page) => page.locator('[role=dialog]:visible, dialog[open]').last();
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  for (const step of ['shipped', 'delivered']) {
    await page.goto(BASE + '/admin/sales/orders/' + uuid); await page.waitForTimeout(1200);
    await page.getByRole('button', { name: /Update status|تحديث الحالة/ }).click(); await page.waitForTimeout(1500);
    const d = dlg(page);
    console.log('DIALOG', (await d.innerText()).replace(/\n+/g, ' | ').slice(0, 400));
    const fields = await d.evaluate((el) => [...el.querySelectorAll('input,select,textarea')].map((e) => `${e.tagName}[${e.type}] ${e.name}${e.tagName === 'SELECT' ? ' opts=' + [...e.options].map((o) => o.value).join('/') : ''}`));
    console.log('FIELDS', fields.join(', '));
    await d.evaluate((el, st) => { const s = el.querySelector('select[name=status], select[name=fulfilment_status], select'); if (s) { const o = [...s.options].find((x) => x.value === st || x.text.toLowerCase().includes(st)); if (o) { s.value = o.value; s.dispatchEvent(new Event('change', { bubbles: true })); if (window.jQuery) window.jQuery(s).trigger('change'); } } const r = el.querySelector(`input[type=radio][value="${st}"]`); if (r) r.click(); }, step);
    await page.waitForTimeout(500);
    const tn = d.locator('input[name=tracking_number]'); if (step === 'shipped' && await tn.count() && await tn.isVisible()) await tn.fill('QA-TRK-' + Date.now().toString(36));
    const co = d.locator('input[name=shipping_company], input[name=carrier], input[name=company]'); if (step === 'shipped' && await co.count() && await co.first().isVisible()) await co.first().fill('QA Express');
    await shot(page, `ord-02-update-${step}-${mode}`);
    await d.locator('button[type=submit]').last().click(); await page.waitForTimeout(2500);
    const left = await page.locator('[role=dialog]:visible, dialog[open]').count();
    console.log(step, 'dialog left open:', left, left ? (await dlg(page).innerText()).replace(/\n+/g, ' | ').slice(0, 300) : '');
    await page.reload(); await page.waitForTimeout(1200);
    console.log('PAGE', (await page.locator('main, #main').first().innerText()).replace(/\n+/g, ' | ').slice(0, 500));
  }
  await shot(page, `ord-03-delivered-${mode}`);
  console.log('errs:', [...new Set(errs)].join(' || '));
  await browser.close();
})();
