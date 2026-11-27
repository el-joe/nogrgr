// The platform demo store must not take orders / accounts (read-only preview store).
export default async ({ page, log, shot }) => {
    const B = 'http://theme-demo.localhost:8203';
    await page.goto(B + '/', { waitUntil: 'networkidle' });
    const link = await page.locator('a[href*="/products/"]').first().getAttribute('href');
    await page.goto(link, { waitUntil: 'networkidle' });
    const btn = page.locator('[data-product-page] [data-add-to-cart]').filter({ visible: true }).first();
    const resp = page.waitForResponse((r) => r.url().includes('/cart'), { timeout: 8000 }).catch(() => null);
    await btn.click().catch((e) => log('add click failed', String(e).slice(0, 80)));
    const r = await resp;
    log('add-to-cart response:', r ? `${r.status()} ${(await r.text()).slice(0, 160)}` : 'none');
    await page.waitForTimeout(1000);
    await shot('demo-add');
    for (const u of ['/checkout', '/account/register', '/account/login']) {
        const res = await page.goto(B + u, { waitUntil: 'networkidle' });
        log(u, res.status(), page.url(), (await page.locator('main').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 160));
    }
    const reg = page.locator('form:has(input[name=password_confirmation])');
    if (await reg.count()) {
        await page.fill('#sf-name', 'Demo Probe'); await page.fill('#sf-email', 'demo-probe@example.test');
        await page.fill('#sf-password', 'DemoProbe12345!'); await page.fill('#sf-password-confirmation', 'DemoProbe12345!');
        await reg.locator('button[type=submit]').click(); await page.waitForTimeout(1500);
        log('register on demo →', page.url(), (await page.locator('main').innerText()).replace(/\s+/g, ' ').slice(0, 200));
    }
};
