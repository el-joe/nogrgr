// Central theme gallery (/themes) + each theme preview on the platform demo store (read-only).
export default async ({ page, CENTRAL, log, shot, mode }) => {
    await page.goto(CENTRAL + '/themes', { waitUntil: 'networkidle' });
    await shot('gallery', true);
    const cards = await page.evaluate(() => [...document.querySelectorAll('a[href*="/themes/"]')].map((a) => a.href).filter((h, i, all) => all.indexOf(h) === i));
    log('gallery links:', cards.length, cards.slice(0, 20).join(' '));
    const names = await page.locator('main h2, main h3').allInnerTexts();
    log('headings:', names.slice(0, 20).join(' | '));
    for (const href of cards.filter((h) => !/[?#]/.test(h)).slice(0, 14)) {
        const res = await page.goto(href, { waitUntil: 'networkidle' }).catch((e) => ({ status: () => String(e).slice(0, 80) }));
        const frame = page.locator('iframe').first();
        const src = (await frame.count()) ? await frame.getAttribute('src') : null;
        let inner = null;
        if (src) {
            const f = page.frames().find((fr) => fr.url() !== 'about:blank' && fr !== page.mainFrame());
            inner = f ? await f.evaluate(() => ({ fam: document.querySelector('[data-theme-family]')?.dataset.themeFamily, v: document.querySelector('[data-theme-variant]')?.dataset.themeVariant, dir: document.documentElement.dir, cart: !!document.querySelector('[data-add-to-cart]') })).catch((e) => String(e).slice(0, 60)) : 'no frame';
        }
        const slug = href.split('/').pop();
        await shot(`preview-${slug}`);
        log(`${slug}: ${res.status()} iframe=${src ? src.replace(/\?.*/, '') : 'none'} inner=${JSON.stringify(inner)}`);
    }
    void mode;
};
