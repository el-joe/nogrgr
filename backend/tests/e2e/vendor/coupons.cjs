// Coupons CRUD through the list page modal: empty submit → 422 inline, create, search in table, edit, delete (confirm dialog).
const { open, login, shot, BASE } = require('./lib.cjs');
const mode = process.argv[2] || 'en-light-1440';
const CODE = ('QAV' + Date.now().toString(36)).toUpperCase().slice(0, 12);
const dlg = (page) => page.locator('[role=dialog]:visible, dialog[open]').last();
async function errors(page) { return page.evaluate(() => [...document.querySelectorAll('[role=dialog] [data-field-error], dialog [data-field-error], [role=dialog] .text-danger, dialog .text-danger')].map((e) => e.innerText.trim()).filter((t) => t && t !== '*')); }
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  await page.goto(BASE + '/admin/marketing/coupons'); await page.waitForTimeout(1500);
  await page.locator('[data-remote-modal="vendor-coupon-modal"]').first().click(); await page.waitForTimeout(1500);
  await dlg(page).locator('input[name=code]').fill('');
  await dlg(page).locator('button[type=submit]').last().click(); await page.waitForTimeout(1500);
  console.log('EMPTY ->', JSON.stringify(await errors(page)));
  await shot(page, `coupon-01-validation-${mode}`);
  const d = dlg(page);
  await d.locator('input[name=code]').fill(CODE);
  await d.locator('input[name=value]').fill('15');
  await d.locator('input[name=min_spend]').fill('30');
  await page.evaluate(() => { const t = new Date(); const f = (x) => x.toISOString().slice(0, 10); const e = new Date(t.getTime() + 7 * 864e5); document.querySelector('[role=dialog] input[name=dates_from], dialog input[name=dates_from]').value = f(t); document.querySelector('[role=dialog] input[name=dates_to], dialog input[name=dates_to]').value = f(e); });
  await d.locator('input[name=usage_limit]').fill('100');
  await d.locator('button[type=submit]').last().click(); await page.waitForTimeout(2500);
  console.log('SAVE errors ->', JSON.stringify(await errors(page)), 'dialog open:', await page.locator('[role=dialog]:visible, dialog[open]').count());
  await page.waitForTimeout(1000);
  const row = page.locator('table tbody tr', { hasText: CODE });
  console.log('ROW', await row.count(), (await row.first().innerText().catch(() => '')).replace(/\s+/g, ' '));
  await shot(page, `coupon-02-list-${mode}`);
  // edit
  await row.first().locator('[data-remote-modal], a, button').filter({ hasText: /Edit|تعديل/ }).first().click().catch(async () => { await row.first().locator('[aria-label*="Edit"], [aria-label*="تعديل"]').first().click(); });
  await page.waitForTimeout(1500);
  await dlg(page).locator('input[name=value]').fill('20');
  await dlg(page).locator('button[type=submit]').last().click(); await page.waitForTimeout(2500);
  console.log('EDITED ROW', (await page.locator('table tbody tr', { hasText: CODE }).first().innerText().catch(() => '')).replace(/\s+/g, ' '));
  // delete
  const r2 = page.locator('table tbody tr', { hasText: CODE }).first();
  await r2.locator('[aria-haspopup], [data-menu-trigger], button[aria-label*="ction"], button[aria-label*="إجراء"]').last().click(); await page.waitForTimeout(500);
  await page.locator('[role=menuitem][data-confirm-method=DELETE]:visible').first().click(); await page.waitForTimeout(800);
  await shot(page, `coupon-03-confirm-${mode}`);
  await page.getByRole('button', { name: /^(Confirm|تأكيد)$/ }).click(); await page.waitForTimeout(2500);
  console.log('AFTER DELETE rows', await page.locator('table tbody tr', { hasText: CODE }).count());
  console.log('errs:', [...new Set(errs)].filter((e) => !/422/.test(e)).join(' || '));
  await browser.close();
})();
