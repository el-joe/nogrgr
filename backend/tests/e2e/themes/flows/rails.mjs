// Drag every horizontally overflowing rail on the home page with the mouse (and touch-swipe on phones).
export default async ({ page, BASE, log, mode, shot }) => {
    await page.goto(BASE + (process.env.QA_URL || '/'), { waitUntil: 'networkidle' });
    const rails = await page.evaluate(() => {
        const out = [];
        document.querySelectorAll('.swiper, [data-rail]').forEach((el, i) => {
            el.setAttribute('data-qa-rail', String(i));
            const wrap = el.querySelector('.swiper-wrapper') || el;
            const r = el.getBoundingClientRect();
            const overflow = wrap.scrollWidth - el.clientWidth;
            const sw = el.swiper;
            out.push({ i, cls: String(el.className).slice(0, 50), w: Math.round(r.width), h: Math.round(r.height), slides: el.querySelectorAll('.swiper-slide').length, overflow, swiper: !!sw, locked: sw ? sw.isLocked : null, simulateTouch: sw ? sw.params.simulateTouch : null, effect: sw ? sw.params.effect : null, native: !sw && getComputedStyle(el).overflowX });
        });
        return out;
    });
    let moved = 0, stuck = [];
    for (const r of rails) {
        if (r.w < 50 || r.h < 50) continue;
        const el = page.locator(`[data-qa-rail="${r.i}"]`);
        if (!(await el.isVisible())) continue;
        const canMove = r.swiper ? !r.locked : r.overflow > 5;
        if (!canMove) continue;
        await el.scrollIntoViewIfNeeded();
        const read = () => el.evaluate((e) => (e.swiper ? `${e.swiper.realIndex}|${e.swiper.activeIndex}|${Math.round(e.swiper.translate)}` : String(Math.round(e.scrollLeft))));
        const before = await read();
        const box = await el.boundingBox();
        const dir = mode.lang === 'ar' ? 1 : -1;
        const y = box.y + Math.min(box.height / 2, 120);
        const x0 = box.x + box.width / 2;
        const dx = dir * Math.min(300, box.width / 3);
        if (mode.mobile) {
            // Real touch swipe through CDP (phones): touchStart → touchMove × n → touchEnd.
            const cdp = await page.context().newCDPSession(page);
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: x0, y }] });
            for (let k = 1; k <= 12; k++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x0 + (dx * k) / 12, y }] });
            await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {
            await page.mouse.move(x0, y);
            await page.mouse.down();
            await page.mouse.move(x0 + dx, y, { steps: 15 });
            await page.mouse.up();
        }
        await page.waitForTimeout(900);
        const after = await read();
        if (before !== after) moved++; else stuck.push(`${r.i}:${r.cls}(${r.effect || r.native})`);
    }
    log(`rails total ${rails.length}, movable+dragged ${moved}, stuck ${stuck.length} ${stuck.join(' ; ')}`);
    log(JSON.stringify(rails.map((r) => `${r.i} ${r.cls} ${r.w}x${r.h} slides=${r.slides} sw=${r.swiper} locked=${r.locked} touch=${r.simulateTouch} eff=${r.effect} ov=${r.overflow}`), null, 0));
};
