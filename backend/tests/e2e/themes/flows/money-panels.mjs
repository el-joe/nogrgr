// Show an order's money in the vendor panel (store host /admin) and the admin panel. Env: QA_ORDER (number).
export default async ({ page, pw, BASE, CENTRAL, log, shot }) => {
    const number = process.env.QA_ORDER || 'ORD-10004';
    const login = async (url, email) => {
        await page.goto(url, { waitUntil: 'networkidle' });
        await page.fill('input[name=email]', email);
        await page.fill('input[name=password]', pw);
        await page.locator('form:has(input[name=password]) [type=submit]').first().click();
        await page.waitForLoadState('networkidle');
        log('login →', page.url());
    };
    const text = async () => (await page.locator('main').innerText()).replace(/\s+/g, ' ');
    await login(BASE + '/login', 'qa-owner2@example.test');
    await page.goto(BASE + '/admin/sales/orders', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    const vlink = page.locator(`main table a:has-text("${number}"), main a[href*="/orders/"]:has-text("${number}")`).first();
    if (await vlink.count()) {
        await vlink.click(); await page.waitForLoadState('networkidle');
        const t = await text();
        log('VENDOR', page.url().replace(BASE, ''), '::', t.slice(t.indexOf('Subtotal') > 0 ? t.indexOf('Subtotal') - 200 : 0).slice(0, 1200));
        await shot('vendor-order', true);
    } else log('VENDOR: order link not found', (await text()).slice(0, 300));
    await login(CENTRAL + '/admin/login', 'qa-themes@qa.test');
    await page.goto(CENTRAL + '/admin/orders?search=' + number, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    const alink = page.locator(`main table a:has-text("${number}"), main a[href*="/orders/"]:has-text("${number}")`).first();
    if (await alink.count()) {
        await alink.click(); await page.waitForLoadState('networkidle');
        const t = await text();
        log('ADMIN', page.url().replace(CENTRAL, ''), '::', t.slice(0, 1800));
        await shot('admin-order', true);
    } else log('ADMIN: order link not found', (await text()).slice(0, 300));
};
