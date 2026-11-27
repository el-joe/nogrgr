// Limited-role staff: owner creates a role (dashboard + products view + orders view), invites a
// member, the member accepts the e-mailed link (read from the log mailer), signs in, and every
// vendor page is checked: allowed pages 200, others 403, no forbidden menu items / manage buttons.
// Env: QA_PASSWORD (owner), STAFF_PASSWORD (new member), MAIL_LOG (laravel.log path), STAFF_EMAIL.
const fs = require('fs');
const path = require('path');
const { open, login, BASE } = require('./lib.cjs');
const ROLE = 'QA Viewer ' + Date.now().toString(36);
const EMAIL = process.env.STAFF_EMAIL || `qa-vendor-viewer-${Date.now().toString(36)}@example.test`;
const ALLOWED = ['dashboard.view', 'catalog.products.view', 'orders.view'];
(async () => {
  const { browser, page, errs } = await open('en-light-1440');
  if (!process.env.SKIP_SETUP) {
  await login(page);
  // 1. role
  await page.goto(BASE + '/admin/settings/roles/create');
  await page.fill('input[name=name]', ROLE);
  for (const p of ALLOWED) await page.locator(`label:has(input[value="${p}"])`).first().click();
  await page.locator('form[action$="/admin/settings/roles"] button[type=submit]').first().click();
  await page.waitForLoadState('load'); await page.waitForTimeout(1200);
  console.log('ROLE saved ->', page.url());
  // 2. invite
  await page.goto(BASE + '/admin/settings/staff/create');
  await page.fill('input[name=name]', 'QA Viewer Staff'); await page.fill('input[name=email]', EMAIL);
  await page.evaluate((role) => { const s = document.querySelector('select[name=role]'); const o = [...s.options].find((x) => x.text.trim() === role); s.value = o.value; s.dispatchEvent(new Event('change', { bubbles: true })); if (window.jQuery) window.jQuery(s).trigger('change'); }, ROLE);
  await page.locator('button[type=submit][form], form[action$="/admin/settings/staff"] button[type=submit]').last().click();
  await page.waitForLoadState('load'); await page.waitForTimeout(2500);
  console.log('INVITE ->', page.url(), errs.filter((e) => /http 4|http 5/.test(e)).join(' | '));
  }
  await page.context().clearCookies();
  // 3. accept
  const log = fs.readFileSync(process.env.MAIL_LOG, 'utf8');
  const at = log.lastIndexOf('To: ' + EMAIL);
  const m = at >= 0 ? log.slice(at, at + 40000).match(/https?:\/\/[^\s"<>)]*(invitation|invite)[^\s"<>)]*/i) : null;
  if (!m) { console.log('NO INVITE LINK in mail log'); await browser.close(); return; }
  const link = m[0].replace(/&amp;/g, '&'); const u0 = new URL(link); await page.goto(BASE + u0.pathname + u0.search);
  console.log('ACCEPT page', page.url());
  const pw = page.locator('input[name=password]');
  if (await pw.count()) {
    await pw.fill(process.env.STAFF_PASSWORD);
    const pc = page.locator('input[name=password_confirmation]'); if (await pc.count()) await pc.fill(process.env.STAFF_PASSWORD);
    await page.locator('form:has(input[name=password]) [type=submit]').first().click();
    await page.waitForLoadState('load'); await page.waitForTimeout(1500);
  }
  console.log('AFTER ACCEPT', page.url());
  if (/login/.test(page.url())) await login(page, EMAIL, process.env.STAFF_PASSWORD);
  // 4. crawl
  const nav = await page.$$eval('nav a[href], aside a[href]', (as) => [...new Set(as.map((a) => a.textContent.trim().replace(/\s+/g, ' ')).filter(Boolean))]);
  console.log('NAV', nav.join(' / '));
  const pages = fs.readFileSync(path.join(__dirname, 'pages.txt'), 'utf8').split('\n').filter(Boolean);
  const ok = [];
  for (const u of pages) { const r = await page.goto(BASE + u, { waitUntil: 'domcontentloaded' }).catch(() => null); const s = r ? r.status() : 0; if (s < 400) ok.push(s + ' ' + u + (page.url() !== BASE + u ? ' -> ' + page.url().replace(BASE, '') : '')); else if (s !== 403) console.log('UNEXPECTED', s, u); }
  console.log('REACHABLE:\n' + ok.join('\n'));
  // 5. manage actions hidden on allowed pages
  for (const u of ['/admin/sales/orders', '/admin/catalog/products']) {
    await page.goto(BASE + u); await page.waitForTimeout(1500);
    const btns = await page.$$eval('main button, main a.ui-btn, #main button, #main a.ui-btn', (bs) => bs.map((b) => b.textContent.trim().replace(/\s+/g, ' ')).filter(Boolean));
    console.log('BUTTONS', u, JSON.stringify([...new Set(btns)]));
  }
  fs.writeFileSync('/tmp/claude-1000/-var-www-nogrgr/74b242b2-bbb1-4c3d-a9e3-599169a05a4b/scratchpad/qaven/staff-email.txt', EMAIL);
  await browser.close();
})();
