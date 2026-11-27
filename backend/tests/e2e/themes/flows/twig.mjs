// Custom Twig starter theme (data-nt-* hooks): search, product, add to cart, favourite, cart, checkout handoff.
export default async ({ page, BASE, log, shot }) => {
    const out = [];
    const step = async (name, fn) => { try { out.push(`PASS ${name} — ${(await fn()) ?? ''}`); } catch (e) { out.push(`FAIL ${name} — ${String(e.message).split('\n')[0].slice(0, 200)}`); await shot(`fail-${name}`).catch(() => {}); } };
    await step('home', async () => { await page.goto(BASE + '/', { waitUntil: 'networkidle' }); await shot('home', true); return `${await page.locator('[data-nt-add-to-cart]').count()} add buttons`; });
    await step('search', async () => { await page.goto(BASE + '/search?q=phone', { waitUntil: 'networkidle' }); await shot('search'); return `${await page.locator('[data-nt-add-to-cart]').count()} results`; });
    await step('category sort', async () => { await page.goto(BASE + '/category/electronics?sort=price_asc', { waitUntil: 'networkidle' }); await shot('category'); return `${await page.locator('[data-nt-add-to-cart]').count()} cards`; });
    await step('product + add to cart', async () => {
        await page.goto(BASE + '/products/1/qa-phone-x', { waitUntil: 'networkidle' });
        const radios = page.locator('form[data-nt-product-form] input[type=radio]');
        if (await radios.count()) await radios.last().check({ force: true });
        const resp = page.waitForResponse((r) => r.url().includes('/cart/items') && r.request().method() === 'POST', { timeout: 10000 });
        await page.locator('form[data-nt-product-form] [data-nt-add-to-cart]').click();
        const r = await resp;
        await page.waitForTimeout(800);
        await shot('product-added');
        if (r.status() >= 300) throw new Error(`add ${r.status()} ${(await r.text()).slice(0, 120)}`);
        return `cart POST ${r.status()}, count ${await page.locator('[data-nt-cart-count], [data-cart-count]').first().innerText().catch(() => '?')}`;
    });
    await step('favourite', async () => {
        const b = page.locator('[data-nt-favorite]').first();
        await b.click(); await page.waitForTimeout(1000);
        return `aria-pressed ${await b.getAttribute('aria-pressed')}`;
    });
    await step('cart → checkout', async () => {
        await page.goto(BASE + '/cart', { waitUntil: 'networkidle' });
        await shot('cart', true);
        const link = page.locator('a[href*="/checkout"]').first();
        await link.click(); await page.waitForLoadState('networkidle');
        await shot('checkout', true);
        return `${page.url().replace(BASE, '')} family=${await page.evaluate(() => document.querySelector('[data-theme-family]')?.dataset.themeFamily ?? 'twig')}`;
    });
    await step('404', async () => { const r = await page.goto(BASE + '/products/999999/x', { waitUntil: 'networkidle' }); await shot('404'); return String(r.status()); });
    for (const l of out) log(l);
};
