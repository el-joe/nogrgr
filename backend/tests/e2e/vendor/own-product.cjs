// Own product: validation → create (2 variants, image + video) → list → edit (AR text, price) → AI translate modal → delete.
const path = require('path');
const { open, login, audit, shot, BASE } = require('./lib.cjs');
const mode = process.argv[2] || 'en-light-1440';
const NAME = 'QA Vendor Lamp ' + Date.now().toString(36);
async function sel(page, name, pick) {
  await page.evaluate(([n, p]) => { const s = document.querySelector(`select[name="${n}"]`); const opts = [...s.options].filter((o) => o.value); (p === 'first' ? opts.slice(0, 1) : opts.filter((o) => o.text.includes(p))).forEach((o) => { o.selected = true; }); s.dispatchEvent(new Event('change', { bubbles: true })); if (window.jQuery) window.jQuery(s).trigger('change'); }, [name, pick]);
}
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  await page.goto(BASE + '/admin/catalog/own-products/create'); await page.waitForTimeout(800);
  // validation
  await page.getByRole('button', { name: /Save product|حفظ المنتج/ }).first().click(); await page.waitForTimeout(1500);
  const v = await page.evaluate(() => [...document.querySelectorAll('[data-field-error], .text-danger, [role=alert]')].map((e) => e.innerText.trim()).filter(Boolean));
  console.log('VALIDATION', page.url(), JSON.stringify(v).slice(0, 600));
  await shot(page, `own-01-validation-${mode}`);
  // fill
  await page.goto(BASE + '/admin/catalog/own-products/create'); await page.waitForTimeout(800);
  await page.fill('input[name=sku]', 'QAV-' + Date.now().toString(36));
  await page.fill('input[name=weight_grams]', '750');
  await sel(page, 'category_ids[]', 'first');
  await page.locator('label:has(input[name=all_countries])').first().click();
  await page.fill('input[name="name[en]"]', NAME);
  await page.fill('input[name="summary[en]"]', 'Warm desk lamp for QA.');
  await page.fill('textarea[name="description[en]"]', 'A dimmable LED desk lamp used by the release QA run.');
  await page.fill('input[name=price]', '49.90'); await page.fill('input[name=sale_price]', '44.50'); await page.fill('input[name=cost_price]', '20');
  await page.fill('input[name=stock]', '12');
  for (let i = 0; i < 2; i++) { await page.locator('[data-variant-add]').click(); await page.waitForTimeout(300); }
  const rows = page.locator('[data-variant-list] > *');
  console.log('variant rows', await rows.count());
  for (let i = 0; i < 2; i++) {
    const r = rows.nth(i);
    await r.locator('input[name$="[title][en]"]').fill(i ? 'Black' : 'White');
    const ar = r.locator('input[name$="[title][ar]"]'); if (await ar.isVisible()) await ar.fill(i ? 'أسود' : 'أبيض');
    await r.locator('input[name$="[sku]"]').fill('QAV-VAR-' + i + Date.now().toString(36));
    await r.locator('input[name$="[price]"]').fill(i ? '52.00' : '49.90');
    await r.locator('input[name$="[stock]"]').fill(String(5 + i));
  }
  await page.setInputFiles('input[type=file][name="images[]"]', [path.join(__dirname, 'fixtures/p1.jpg'), path.join(__dirname, 'fixtures/v1.mp4')]);
  await page.waitForTimeout(1500);
  await shot(page, `own-02-filled-${mode}`);
  await page.getByRole('button', { name: /Save product|حفظ المنتج/ }).first().click();
  await page.waitForLoadState('load'); await page.waitForTimeout(2500);
  console.log('AFTER SAVE', page.url(), (await page.locator('[role=status], .toast, [data-toast]').allInnerTexts().catch(() => [])).join(' | ').slice(0, 200));
  const errsNow = await page.evaluate(() => [...document.querySelectorAll('[data-field-error], .text-danger')].map((e) => e.innerText.trim()).filter(Boolean));
  if (errsNow.length) console.log('SAVE ERRORS', JSON.stringify(errsNow));
  await shot(page, `own-03-after-save-${mode}`);
  // list
  await page.goto(BASE + '/admin/catalog/own-products'); await page.waitForTimeout(2000);
  const found = await page.getByText(NAME).count();
  console.log('IN LIST', found);
  console.log('AUDIT', JSON.stringify(await audit(page)));
  console.log('ERRS', [...new Set(errs)].join('\n'));
  require('fs').writeFileSync('/tmp/claude-1000/-var-www-nogrgr/74b242b2-bbb1-4c3d-a9e3-599169a05a4b/scratchpad/qaven/own-name.txt', NAME);
  await browser.close();
})();
