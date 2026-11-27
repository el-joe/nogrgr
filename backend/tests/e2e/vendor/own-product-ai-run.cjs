// Run AI translate (review then save) and AI social post generation once on an own product.
const { open, login, shot, BASE } = require('./lib.cjs');
const id = process.argv[2]; const mode = process.argv[3] || 'en-light-1440';
(async () => {
  const { browser, page, errs } = await open(mode);
  await login(page);
  await page.goto(BASE + `/admin/catalog/own-products/${id}/edit`); await page.waitForTimeout(1000);
  await page.getByRole('button', { name: 'Translate with AI' }).click(); await page.waitForTimeout(1500);
  let dlg = page.locator('[role=dialog]:visible, dialog[open]').last();
  await dlg.locator('label:has(input[type=checkbox])').first().click();
  await dlg.getByRole('button', { name: 'Translate', exact: true }).click();
  await page.waitForTimeout(25000);
  console.log('TRANSLATE RESULT:', (await dlg.innerText().catch(() => '')).replace(/\n+/g, ' | ').slice(0, 900));
  await shot(page, `own-ai-translate-result-${mode}`);
  const save = dlg.getByRole('button', { name: /Save|Apply|Use/ }).last();
  if (await save.count()) { await save.click(); await page.waitForTimeout(2500); }
  await page.reload(); await page.waitForTimeout(1000);
  console.log('AR NAME after:', await page.inputValue('input[name="name[ar]"]').catch((e) => 'ERR ' + e.message));
  // social
  await page.getByRole('button', { name: 'AI social posts' }).click(); await page.waitForTimeout(1200);
  dlg = page.locator('[role=dialog]:visible, dialog[open]').last();
  await dlg.getByRole('button', { name: 'Generate' }).click(); await page.waitForTimeout(25000);
  console.log('SOCIAL RESULT:', (await dlg.innerText().catch(() => '')).replace(/\n+/g, ' | ').slice(0, 700));
  await shot(page, `own-ai-social-result-${mode}`);
  console.log('errs:', [...new Set(errs)].join(' || '));
  await browser.close();
})();
