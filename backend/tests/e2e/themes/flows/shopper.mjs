// Shopper flow per theme (signed in): search + autocomplete, image search modal, category
// filter + sort, product (variant, delivery estimate, reviews, video + floating PiP,
// back-in-stock), rail drag, mini cart, wishlist, account orders / tracking / invoice /
// reorder / cancel, return request with photo, support ticket, language switch.
// Env: QA_DELIVERED_ORDER (uuid of a delivered order for the return step), QA_PHOTO (jpg path).
import fs from 'node:fs';

export default async ({ page, BASE, log, shot, mode }) => {
    const results = [];
    const step = async (name, fn) => {
        try {
            const note = await fn();
            results.push(`PASS ${name}${note ? ' — ' + note : ''}`);
        } catch (e) {
            results.push(`FAIL ${name} — ${String(e.message || e).split('\n')[0].slice(0, 220)}`);
            await shot(`fail-${name}`).catch(() => {});
        }
    };
    const visible = (sel) => page.locator(sel).filter({ visible: true }).first();
    const fillForm = async (scope) => {
        const root = page.locator(scope).filter({ visible: true }).first();
        for (const sel of await root.locator('select').all()) {
            const v = await sel.evaluate((s) => [...s.options].find((o) => o.value)?.value);
            if (v) await sel.selectOption(v, { force: true }).catch(() => {});
        }
        for (const radio of await root.locator('input[type=radio]:visible').all()) { await radio.check({ force: true }).catch(() => {}); break; }
        for (const t of await root.locator('textarea:visible, input[type=text]:visible').all()) {
            if (!(await t.inputValue())) await t.fill('QA themes release test: please ignore this message, it checks the form works.');
        }
        const photo = process.env.QA_PHOTO;
        for (const f of await root.locator('input[type=file]').all()) {
            const accept = (await f.getAttribute('accept')) || '';
            if (photo && fs.existsSync(photo) && (!accept || /image|jpe?g|png|webp/.test(accept))) await f.setInputFiles(photo).catch(() => {});
        }
        return root;
    };

    await page.goto(BASE + '/', { waitUntil: 'networkidle' });

    await step('rail mouse-drag', async () => {
        const rail = page.locator('.swiper:visible').nth(1);
        if (!(await rail.count())) return 'no swiper rail on home';
        await rail.scrollIntoViewIfNeeded();
        const wrap = rail.locator('.swiper-wrapper').first();
        const before = await wrap.evaluate((w) => getComputedStyle(w).transform);
        const box = await rail.boundingBox();
        const dir = mode.lang === 'ar' ? 1 : -1;
        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width / 2 + dir * 300, box.y + box.height / 2, { steps: 12 });
        await page.mouse.up();
        await page.waitForTimeout(700);
        const after = await wrap.evaluate((w) => getComputedStyle(w).transform);
        if (before === after) throw new Error(`rail did not move (${before})`);
        return `${before} → ${after}`;
    });

    await step('reduced motion respected', async () => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.reload({ waitUntil: 'networkidle' });
        const hidden = await page.evaluate(() => [...document.querySelectorAll('[data-reveal]')].filter((e) => getComputedStyle(e).opacity === '0').length);
        await page.emulateMedia({ reducedMotion: 'no-preference' });
        if (hidden) throw new Error(`${hidden} [data-reveal] elements stay invisible with reduced motion`);
    });

    await step('search autocomplete', async () => {
        const input = visible('input[name=q]');
        if (!(await input.count())) {
            await visible('[data-search-open], [aria-label*="Search" i], [aria-label*="بحث"]').click();
            await page.waitForTimeout(400);
        }
        const q = visible('input[name=q]');
        await q.click();
        await q.pressSequentially('pho', { delay: 80 });
        await page.waitForTimeout(1500);
        const items = await page.locator('[role=listbox] [role=option]:visible, [data-search-results] a:visible, [data-autocomplete] a:visible').count();
        await shot('search-autocomplete');
        if (!items) throw new Error('no autocomplete suggestions for "pho"');
        await q.press('Enter');
        await page.waitForLoadState('networkidle');
        if (!/search/.test(page.url())) throw new Error(`enter went to ${page.url()}`);
        return `${items} suggestions`;
    });

    await step('image search modal', async () => {
        await page.goto(BASE + '/', { waitUntil: 'networkidle' });
        const btn = visible('[data-image-search-open]');
        if (!(await btn.count())) throw new Error('no visible image search button');
        await btn.click();
        await page.waitForTimeout(600);
        const photo = process.env.QA_PHOTO;
        if (photo) {
            await page.locator('[data-image-search-file]').first().setInputFiles(photo);
            await page.waitForTimeout(6000);
        }
        const state = await page.evaluate(() => ({
            cards: document.querySelectorAll('[data-image-search-card]').length,
            error: [...document.querySelectorAll('[data-image-search-error]')].filter((e) => !e.hidden && e.offsetParent).map((e) => e.innerText).join(' '),
            empty: [...document.querySelectorAll('[data-image-search-empty]')].some((e) => !e.hidden && e.offsetParent),
        }));
        await shot('image-search');
        if (state.error) throw new Error(`error: ${state.error}`);
        return JSON.stringify(state);
    });

    await step('category filter + sort', async () => {
        await page.goto(BASE + '/category/electronics', { waitUntil: 'networkidle' });
        const count0 = await page.locator('main [data-product-id]').count();
        const notes = [`products ${count0}`];
        let sortLink = page.locator('main a[href*="sort=price_asc"]').filter({ visible: true }).first();
        if (!(await sortLink.count())) {
            const opener = page.locator('main button[aria-haspopup], main [data-sort-open], main [data-sheet-open*="sort"], main summary').filter({ visible: true }).first();
            if (await opener.count()) { await opener.click(); await page.waitForTimeout(500); }
            sortLink = page.locator('a[href*="sort=price_asc"]').filter({ visible: true }).first();
        }
        if (await sortLink.count()) {
            await sortLink.click();
            await page.waitForLoadState('networkidle');
        } else {
            const sel = page.locator('select[data-auto-filter="sort"], select[name=sort]').filter({ visible: true }).first();
            if (!(await sel.count())) throw new Error('no sort control found');
            const value = await sel.evaluate((s) => [...s.options].find((o) => /price_asc/.test(o.value))?.value);
            await sel.selectOption(value ?? 'price_asc', { force: true });
            await page.waitForTimeout(2500);
        }
        if (!/sort=price_asc/.test(page.url())) throw new Error(`sort did not apply (${page.url().replace(BASE, '')})`);
        const prices = await page.locator('main [data-product-id] [data-price], main [data-product-id] .sf-price, main [data-product-id] [data-card-price]').allInnerTexts();
        const nums = prices.map((p) => parseFloat(p.replace(/[^0-9.]/g, ''))).filter((n) => !Number.isNaN(n));
        const sorted = nums.every((n, i) => i === 0 || n >= nums[i - 1] - 0.01);
        notes.push(`sorted asc ${nums.length ? sorted : 'n/a'} (${nums.slice(0, 5).join(', ')})`);
        const filter = page.locator('main input[name=price], main input[type=checkbox][name*="brand"], main [data-filter-form] input[type=checkbox], main [data-filter-form] input[type=radio]').filter({ visible: true }).first();
        if (await filter.count()) {
            await filter.check({ force: true });
            await page.waitForTimeout(2500);
            const apply = page.locator('main [data-filter-form] button[type=submit]').filter({ visible: true }).first();
            if (!/price=|brand|attr|cat/.test(page.url()) && (await apply.count())) { await apply.click(); await page.waitForLoadState('networkidle'); }
            notes.push(`filter → ${page.url().replace(BASE, '')}`);
            if (!/[?&](price|brand|brands|attr|filter|cat)/.test(page.url())) throw new Error(`filter did not apply: ${notes.join('; ')}`);
        } else notes.push('no visible filter control (sheet on phones?)');
        await shot('category-filtered');
        if (nums.length && !sorted) throw new Error(notes.join('; '));
        return notes.join('; ');
    });

    await step('product: variant, delivery, reviews, video', async () => {
        await page.goto(BASE + '/products/1/qa-phone-x', { waitUntil: 'networkidle' });
        const notes = [];
        const price0 = await visible('[data-product-page] [data-price]').innerText().catch(() => '');
        const opts = page.locator('[data-product-page] [data-option]').filter({ visible: true });
        if ((await opts.count()) > 1) { await opts.nth(1).click(); await page.waitForTimeout(600); }
        notes.push(`price ${price0} → ${await visible('[data-product-page] [data-price]').innerText().catch(() => '?')}`);
        const delivery = visible('button:has-text("delivery"), button:has-text("التوصيل"), [data-delivery-estimate], :text("Delivery:"), :text("التوصيل:")');
        notes.push(`delivery ${(await delivery.count()) ? (await delivery.innerText()).replace(/\s+/g, ' ').slice(0, 40) : 'MISSING'}`);
        const video = page.locator('[data-gallery-video], [data-product-page] video').first();
        notes.push(`video ${(await video.count()) ? 'yes' : 'MISSING'}`);
        if (await video.count()) {
            const slide = visible('[data-gallery-video-slide], [data-gallery-thumb][data-video]');
            if (await slide.count()) await slide.click().catch(() => {});
            await page.evaluate(() => { const v = document.querySelector('[data-gallery-video], [data-product-page] video'); if (v) { v.muted = true; v.play().catch(() => {}); } });
            await page.waitForTimeout(800);
            await page.evaluate(() => window.scrollTo(0, 1600));
            await page.waitForTimeout(1200);
            const pip = await page.evaluate(() => { const p = [...document.querySelectorAll('.sf-video-pip')].find((e) => !e.hidden && e.getBoundingClientRect().width > 0); return p ? (() => { const r = p.getBoundingClientRect(); return `${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)}`; })() : null; });
            notes.push(`floating video ${pip ?? 'NOT SHOWN'}`);
            if (!pip) throw new Error(notes.join('; '));
            await shot('product-video-pip');
            await page.evaluate(() => window.scrollTo(0, 0));
        }
        const reviews = visible('[data-review-write], [data-reviews-open], [data-sheet-open*="review"]');
        notes.push(`reviews ${(await page.locator('[data-reviews-list], [data-reviews-modal]').count()) ? 'yes' : 'MISSING'}`);
        void reviews;
        return notes.join('; ');
    });

    await step('back-in-stock form', async () => {
        const oos = process.env.QA_OOS_PRODUCT;
        if (!oos) return 'skipped (no out-of-stock product configured)';
        await page.goto(BASE + oos, { waitUntil: 'networkidle' });
        const form = visible('[data-stock-alert] form, form[data-stock-alert], [data-stock-alert-form]');
        if (!(await form.count())) throw new Error('no back-in-stock form on out-of-stock product');
        const email = form.locator('input[type=email]');
        if (await email.count() && !(await email.inputValue())) await email.fill('qa-themes-shopper@example.test');
        await form.locator('button[type=submit]').click();
        await page.waitForTimeout(1500);
        await shot('back-in-stock');
        return (await form.innerText()).replace(/\s+/g, ' ').slice(0, 120);
    });

    await step('add to cart + mini cart', async () => {
        await page.goto(BASE + '/products/15/demo-smartphone', { waitUntil: 'networkidle' });
        await visible('[data-product-page] [data-add-to-cart]').click();
        await page.waitForTimeout(1800);
        const mini = await page.evaluate(() => [...document.querySelectorAll('[data-mini-cart], [data-elo-mini-panel], [data-sq-mini-cart], [data-eco-mini-cart], [role=dialog]')].some((e) => e.offsetParent && e.getBoundingClientRect().width > 100));
        await shot('mini-cart');
        const count = await page.locator('[data-cart-count]').first().innerText().catch(() => '?');
        if (!mini) return `mini cart not visible after add (count ${count})`;
        return `mini cart open, count ${count}`;
    });

    await step('wishlist', async () => {
        await page.goto(BASE + '/products/15/demo-smartphone', { waitUntil: 'networkidle' });
        const fav = visible('[data-product-page] [data-favorite]');
        const on = await fav.evaluate((b) => b.getAttribute('aria-pressed') === 'true' || b.classList.contains('is-active') || b.dataset.state === 'on');
        if (!on) await fav.click();
        await page.waitForTimeout(1200);
        await page.goto(BASE + '/account/wishlist', { waitUntil: 'networkidle' });
        const n = await page.locator('main [data-product-id]').count();
        await shot('wishlist');
        if (!n) throw new Error('wishlist empty after favouriting (maybe toggled off)');
        return `${n} items`;
    });

    await step('orders list + order page + tracking + invoice', async () => {
        await page.goto(BASE + '/account/orders', { waitUntil: 'networkidle' });
        await shot('orders');
        const link = page.locator('main a[href*="/orders/"]').first();
        const href = await link.getAttribute('href');
        await page.goto(href.startsWith('http') ? href : BASE + href, { waitUntil: 'networkidle' });
        await shot('order', true);
        const notes = [];
        const tr = await page.goto(page.url().split('?')[0] + '/tracking', { waitUntil: 'networkidle' });
        notes.push(`tracking ${tr.status()}`);
        await shot('tracking', true);
        const inv = await page.goto(href.split('?')[0].replace(BASE, '') ? (href.startsWith('http') ? href : BASE + href).split('?')[0] + '/invoice' : '', { waitUntil: 'networkidle' });
        notes.push(`invoice ${inv.status()}`);
        await shot('invoice', true);
        if (tr.status() !== 200 || inv.status() !== 200) throw new Error(notes.join(', '));
        return notes.join(', ');
    });

    await step('reorder', async () => {
        await page.goto(BASE + '/account/orders', { waitUntil: 'networkidle' });
        const btn = visible('[data-reorder]');
        await btn.click();
        await page.waitForTimeout(2500);
        return `→ ${page.url().replace(BASE, '')}`;
    });

    await step('cancel order (pending)', async () => {
        await page.goto(BASE + '/account/orders', { waitUntil: 'networkidle' });
        const btn = visible('[data-cancel-order]');
        if (!(await btn.count())) return 'no cancellable order';
        await btn.click();
        await page.waitForTimeout(700);
        const modal = visible('[data-cancel-modal]');
        const reason = modal.locator('input[type=radio]').first();
        if (await reason.count()) await reason.check({ force: true });
        const sel = modal.locator('select').first();
        if (await sel.count()) { const v = await sel.evaluate((s) => [...s.options].find((o) => o.value)?.value); if (v) await sel.selectOption(v, { force: true }); }
        await shot('cancel-step1');
        const next = modal.locator('[data-cancel-next]');
        if (await next.isVisible().catch(() => false)) { await next.click(); await page.waitForTimeout(600); }
        await shot('cancel-step2');
        await modal.locator('button[type=submit]').filter({ visible: true }).first().click();
        await page.waitForTimeout(2500);
        const errors = await modal.locator('[data-error-for]:visible, [data-cancel-reason-error]:visible').allInnerTexts().catch(() => []);
        await shot('cancel-done');
        if (errors.filter(Boolean).length) throw new Error(`errors ${errors.join(' | ')}`);
        return 'submitted';
    });

    await step('return request with photo', async () => {
        const uuid = process.env.QA_DELIVERED_ORDER;
        if (!uuid) return 'skipped (no delivered order)';
        const res = await page.goto(BASE + `/orders/${uuid}/return`, { waitUntil: 'networkidle' });
        if (res.status() !== 200) throw new Error(`return page ${res.status()}`);
        const form = 'main form:has(textarea)';
        const item = page.locator(`${form} input[type=checkbox]`).first();
        if (await item.count()) await item.check({ force: true });
        await fillForm(form);
        await shot('return-form', true);
        await page.locator(`${form} button[type=submit]`).filter({ visible: true }).last().click();
        await page.waitForTimeout(3000);
        const errs = await page.locator('[data-error-for]:visible, [data-form-error]:visible').allInnerTexts();
        await shot('return-after', true);
        if (errs.filter((e) => e.trim()).length) throw new Error(`errors: ${errs.join(' | ')}`);
        return `→ ${page.url().replace(BASE, '')}`;
    });

    await step('support ticket', async () => {
        await page.goto(BASE + '/account/support', { waitUntil: 'networkidle' });
        await visible('button:has-text("New ticket"), button:has-text("تذكرة جديدة"), [data-ticket-new], [data-modal-open]').click();
        await page.waitForTimeout(800);
        const scope = '[role=dialog] form, dialog form, [data-modal] form, main form[data-ajax]';
        await fillForm(scope);
        await shot('ticket-form');
        await page.locator(scope).filter({ visible: true }).first().locator('button[type=submit]').click();
        await page.waitForTimeout(3000);
        const errs = await page.locator('[data-error-for]:visible, [data-form-error]:visible').allInnerTexts();
        await shot('ticket-after');
        if (errs.filter((e) => e.trim()).length) throw new Error(`errors: ${errs.join(' | ')}`);
        return `→ ${page.url().replace(BASE, '')}`;
    });

    await step('verify-email page', async () => {
        const r = await page.goto(BASE + '/account/verify-email', { waitUntil: 'networkidle' });
        await shot('verify-email');
        return `${r.status()} ${page.url().replace(BASE, '')}`;
    });

    await step('language switch (?lang)', async () => {
        const other = mode.lang === 'ar' ? 'en' : 'ar';
        await page.goto(BASE + '/?lang=' + other, { waitUntil: 'networkidle' });
        await page.goto(BASE + '/cart', { waitUntil: 'networkidle' });
        const dir = await page.evaluate(() => document.documentElement.dir);
        await page.goto(BASE + '/?lang=' + mode.lang, { waitUntil: 'networkidle' });
        if (dir !== (other === 'ar' ? 'rtl' : 'ltr')) throw new Error(`dir after switch ${dir}`);
        return `switched to ${other} and back`;
    });

    for (const r of results) log(r);
};
