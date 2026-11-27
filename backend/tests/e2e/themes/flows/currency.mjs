// Switch display currency to SAR through the preferences form; checkout must still charge USD.
export default async ({ page, BASE, log, shot }) => {
    await page.goto(BASE + '/', { waitUntil: 'networkidle' });
    await page.evaluate(() => { const f = document.querySelector('[data-preferences-form]'); f.querySelector('select[name=currency]').value = 'SAR'; f.requestSubmit ? f.requestSubmit() : f.submit(); });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    log('home price sample:', await page.locator('main [data-product-id]').first().innerText().then((t) => t.replace(/\s+/g, ' ').slice(0, 100)));
    await page.goto(BASE + '/products/15/demo-smartphone', { waitUntil: 'networkidle' });
    await page.locator('[data-product-page] [data-add-to-cart]').filter({ visible: true }).first().click();
    await page.waitForTimeout(1500);
    await page.goto(BASE + '/checkout', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    log('usd line:', await page.locator('[data-usd-charge]').first().evaluate((e) => (e.hidden ? '(hidden)' : e.innerText.trim())));
    log('pay button:', (await page.locator('[data-pay-button]').innerText()).trim());
    log('summary:', (await page.locator('[data-checkout-summary]').first().innerText()).replace(/\s+/g, ' ').slice(0, 300));
    await shot('checkout-sar', true);
    await page.evaluate(() => { const f = document.querySelector('[data-preferences-form]'); f.querySelector('select[name=currency]').value = 'USD'; f.requestSubmit(); });
    await page.waitForTimeout(1500);
};
