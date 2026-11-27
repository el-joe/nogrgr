// Cart → checkout → pay. Env: QA_GATEWAY=stripe|cod, QA_CARD (stripe test card), QA_PRODUCT (path), QA_COUPON.
export default async ({ page, BASE, log, shot }) => {
    const gateway = process.env.QA_GATEWAY || 'stripe';
    const card = (process.env.QA_CARD || '4242424242424242');
    const product = process.env.QA_PRODUCT || '/products/15/demo-smartphone';
    await page.goto(BASE + product, { waitUntil: 'networkidle' });
    const opt = page.locator('[data-product-page] [data-option]:visible, form[data-nt-product-form] input[type=radio]:visible').first();
    if (await opt.count()) { await opt.click(); await page.waitForTimeout(500); }
    await page.locator('[data-product-page] [data-add-to-cart]:visible, form[data-nt-product-form] [data-nt-add-to-cart]').first().click();
    await page.waitForTimeout(1500);
    await shot('added-to-cart');
    await page.goto(BASE + '/cart', { waitUntil: 'networkidle' });
    if (process.env.QA_COUPON) {
        const input = page.locator('[data-coupon-form] input[name=code]:visible').first();
        await input.fill(process.env.QA_COUPON);
        await page.locator('[data-coupon-apply]:visible').first().click();
        await page.waitForTimeout(2000);
        log('cart after coupon:', (await page.locator('main').innerText()).replace(/\s+/g, ' ').slice(0, 700));
    }
    await shot('cart', true);
    await page.goto(BASE + '/checkout', { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const hasAddress = await page.locator('[data-address-list] input[type=radio]').count();
    if (!hasAddress) {
        await page.locator('[data-address-add]:visible').first().click();
        await page.waitForTimeout(800);
        const f = '[data-address-form]';
        await page.fill(`${f} input[name=full_name]`, 'QA Themes Shopper');
        await page.fill(`${f} input[name=phone]`, '551234567');
        await page.fill(`${f} input[name=email]`, 'qa-themes-shopper@example.test');
        await page.fill(`${f} input[name=address_line_1]`, 'King Fahd Road 12');
        const country = await page.locator(`${f} select[name=country_id]`).inputValue();
        if (!country) {
            await page.locator(`${f} [data-country-select] button, ${f} #sf-checkout-address-modal-form-country`).first().click();
            await page.locator(`${f} [data-sf-combobox] input[type=search]`).first().fill('Saudi');
            await page.waitForTimeout(400);
            await page.locator(`${f} [role=option]:visible`).first().click();
        }
        await page.fill(`${f} input[name=city]`, 'Riyadh');
        await page.waitForTimeout(800);
        const sug = page.locator(`${f} [data-city-list] [role=option]:visible`).first();
        if (await sug.count()) await sug.click();
        await page.fill(`${f} input[name=state]`, 'Riyadh');
        await shot('address-modal');
        await page.locator(`${f} button[type=submit]`).click();
        await page.waitForTimeout(2500);
        log('form errors:', await page.locator(`${f} [data-error-for]:visible, ${f} [data-form-error]:visible`).allInnerTexts());
    }
    await page.waitForTimeout(1500);
    const radios = await page.locator('[data-payment-section] input[type=radio]').evaluateAll((r) => r.map((x) => x.value));
    log('gateways offered:', radios);
    const usd = await page.locator('[data-usd-charge]').first().evaluate((e) => (e.hidden ? '(hidden)' : e.innerText)).catch(() => 'n/a');
    log('usd charge line:', usd);
    const radio = page.locator(`[data-payment-section] input[type=radio][value="${gateway}"]`);
    if (await radio.count()) await radio.first().check({ force: true });
    await page.waitForTimeout(800);
    if (gateway === 'stripe') {
        const frame = page.frameLocator('[data-stripe-element] iframe').first();
        await frame.locator('input[name=cardnumber]').fill(card, { timeout: 30000 });
        await frame.locator('input[name=exp-date]').fill('12 / 34');
        await frame.locator('input[name=cvc]').fill('123');
        const zip = frame.locator('input[name=postal]');
        if (await zip.count()) await zip.fill('12345');
    }
    log('summary:', (await page.locator('[data-checkout-summary]').first().innerText()).replace(/\s+/g, ' '));
    await shot('checkout-ready', true);
    await page.locator('[data-pay-button]').click();
    await page.waitForTimeout(12000);
    log('after pay url:', page.url());
    log('page text:', (await page.locator('main').innerText()).replace(/\s+/g, ' ').slice(0, 900));
    const err = await page.locator('[data-stripe-error]:visible, [data-payment-error]:visible').allInnerTexts();
    if (err.length) log('payment error shown:', err);
    await shot('after-pay', true);
};
