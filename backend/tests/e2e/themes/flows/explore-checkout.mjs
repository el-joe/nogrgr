export default async ({ page, BASE, log, shot }) => {
    await page.goto(BASE + '/products/15/demo-smartphone', { waitUntil: 'networkidle' });
    await page.locator('[data-product-page] [data-add-to-cart]:visible').first().click();
    await page.waitForTimeout(1500);
    await page.goto(BASE + '/checkout', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    const info = await page.evaluate(() => {
        const attrs = new Set();
        for (const el of document.querySelectorAll('main *, [role=dialog] *, dialog *')) for (const a of el.getAttributeNames()) if (a.startsWith('data-')) attrs.add(a);
        const ctrls = [...document.querySelectorAll('input,select,button,textarea')].map((b) => `${b.tagName}[${b.name || ''}]#${b.id} ${b.type} vis=${!!b.offsetParent} "${(b.innerText || b.value || b.getAttribute('aria-label') || '').trim().slice(0, 30)}"`);
        return { attrs: [...attrs].join(' '), ctrls, text: document.querySelector('main').innerText.slice(0, 1500) };
    });
    log(JSON.stringify(info, null, 1));
    await shot('checkout-explore', true);
};
