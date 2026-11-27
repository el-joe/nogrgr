// Touch-swipe the home hero at several heights (phones) and report whether the slide changes.
export default async ({ page, BASE, log, mode }) => {
    await page.goto(BASE + '/', { waitUntil: 'networkidle' });
    const hero = page.locator('.mo-hero').first();
    await hero.scrollIntoViewIfNeeded();
    const box = await hero.boundingBox();
    const cdp = await page.context().newCDPSession(page);
    const read = () => hero.evaluate((e) => `${e.swiper?.realIndex}/${e.swiper?.slides.length} autoplay=${e.swiper?.autoplay?.running}`);
    await hero.evaluate((e) => { window.__ev = []; ['touchStart', 'sliderMove', 'touchEnd', 'slideChange', 'sliderFirstMove'].forEach((n) => e.swiper.on(n, () => window.__ev.push(n))); });
    const dir = mode.lang === 'ar' ? 1 : -1;
    for (const f of [0.15, 0.5, 0.85]) {
        const y = box.y + box.height * f, x0 = box.x + box.width * (dir < 0 ? 0.8 : 0.2);
        const before = await read();
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: x0, y }] });
        for (let k = 1; k <= 12; k++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x0 + (dir * box.width * 0.5 * k) / 12, y }] });
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await page.waitForTimeout(900);
        const target = await page.evaluate(([x, yy]) => { const el = document.elementFromPoint(x, yy); return `${el?.tagName}.${String(el?.className).slice(0, 40)}`; }, [x0, y]);
        log(`events ${await page.evaluate(() => { const r = [...new Set(window.__ev)].join(','); window.__ev = []; return r; })} y=${f}: ${before} → ${await read()} (start on ${target})`);
    }
};
