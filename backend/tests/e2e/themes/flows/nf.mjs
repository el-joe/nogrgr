export default async ({ page, BASE, log }) => {
    for (const u of ['/pages/contact', '/products/999999/nope', '/products/999999/nope']) {
        try { const r = await page.goto(BASE + u, { waitUntil: 'networkidle' }); log(u, r.status()); } catch (e) { log(u, 'ERR', String(e).slice(0, 150)); }
    }
};
