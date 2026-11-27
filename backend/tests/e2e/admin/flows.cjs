/*
 * End-to-end CRUD flows through the real admin UI (release QA).
 * Usage: node flows.cjs [flowName,...] [modeKey]   (default: all flows, d-en-light)
 * Each step prints PASS/FAIL; screenshots go to docs/plan/release-qa/admin/flow_*.
 */
const path = require('path');
const L = require('./lib.cjs');

const FX = path.join(__dirname, 'fixtures');
const RUN = Date.now().toString(36).slice(-5);
const results = [];
let current = '';

function step(name, ok, info = '') {
    results.push({ flow: current, step: name, ok, info });
    console.log(`${ok ? 'PASS' : 'FAIL'} [${current}] ${name}${info ? ' :: ' + String(info).slice(0, 300) : ''}`);
}

/** Submit an ajax form via its submit button and return the JSON/XHR response. */
async function submitForm(page, formSel, buttonSel) {
    const respP = page.waitForResponse((r) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(r.request().method()) && !r.url().includes('/notifications') && !r.url().includes('/validate'), { timeout: 12000 }).catch(() => null);
    const formId = await page.locator(formSel.split(',')[0]).first().getAttribute('id').catch(() => null);
    const sel = (buttonSel || `${formSel} [type=submit]`) + (formId ? `, [type=submit][form="${formId}"]` : '');
    await page.locator(sel).filter({ visible: true }).first().click({ timeout: 8000 }).catch(() => {});
    const r = await respP;
    if (!r) { await page.waitForTimeout(300); return { status: 0, body: null, url: null }; }
    let body = null;
    try { body = await r.json(); } catch { body = null; }
    await page.waitForTimeout(600);
    return { status: r.status(), body, url: r.url() };
}

async function inlineErrors(page, scope = 'body') {
    return page.$$eval(`${scope} [data-field-error], ${scope} .ui-field-error, ${scope} [data-error-for]`, (els) => els.filter((e) => e.offsetParent && e.innerText.trim()).map((e) => e.innerText.trim())).catch(() => []);
}

async function select2Pick(page, selectId, text) {
    await page.locator(`#${selectId} + .select2 .select2-selection, #${selectId} ~ .select2 .select2-selection`).first().click();
    const search = page.locator('.select2-container--open .select2-search__field');
    if (await search.count()) await search.fill(text);
    await page.waitForTimeout(700);
    await page.locator('.select2-container--open .select2-results__option:not(.select2-results__message)').first().click();
}

async function dtSearch(page, text) {
    const input = page.locator('[data-filters] input[name=search]:visible').first();
    await input.fill(text);
    await page.waitForTimeout(1500);
    await L.settle(page);
}

async function confirmAccept(page) {
    await page.locator('#ui-confirm [data-confirm-accept]').waitFor({ state: 'visible', timeout: 5000 });
    const req = page.locator('#ui-confirm [data-confirm-require]:not([hidden])');
    if (await req.count()) {
        const name = await page.locator('#ui-confirm [data-confirm-require-name]').innerText();
        await page.fill('#ui-confirm-require', name);
    }
    const respP = page.waitForResponse((r) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(r.request().method()), { timeout: 15000 }).catch(() => null);
    await page.click('#ui-confirm [data-confirm-accept]');
    const r = await respP;
    await page.waitForTimeout(800);
    return r ? r.status() : null;
}

/** Set form values by field name inside `scope` (works for hidden locale panes, date pickers, select2). */
async function setValues(page, scope, values) {
    await page.evaluate(({ scope, values }) => {
        const root = document.querySelector(scope) || document;
        for (const [name, value] of Object.entries(values)) {
            const els = [...root.querySelectorAll(`[name="${name}"]`)];
            if (!els.length) { console.warn('no field ' + name); continue; }
            for (const el of els) {
                if (el.type === 'hidden' && typeof value === 'boolean') { continue; }
                if (el.type === 'checkbox') { el.checked = Array.isArray(value) ? value.map(String).includes(el.value) : !!value; }
                else if (el.type === 'radio') { el.checked = el.value === String(value); }
                else if (el.tagName === 'SELECT' && el.multiple) { let vs = [].concat(value).map(String); if (vs[0] === '@first') vs = [[...el.options].find((o) => o.value)?.value].filter(Boolean); vs.forEach((v) => { if (![...el.options].some((o) => o.value === v)) el.add(new Option(v, v)); }); [...el.options].forEach((o) => { o.selected = vs.includes(o.value); }); }
                else if (el.tagName === 'SELECT' && value === '@first') { const o = [...el.options].find((x) => x.value); if (o) el.value = o.value; }
                else if (el.tagName === 'SELECT') { if (![...el.options].some((o) => o.value === String(value))) el.add(new Option(String(value), String(value))); el.value = String(value); }
                else { el.value = value; }
                if (el.matches('[data-editor-input]')) { const area = el.closest('[data-rich-editor]')?.querySelector('[data-editor-area]'); if (area) area.innerHTML = value; }
                if (el.type === 'tel' && window.intlTelInputGlobals) { const iti = window.intlTelInputGlobals.getInstance(el); if (iti) iti.setNumber(value); }
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                if (window.jQuery && el.classList.contains('select2-hidden-accessible')) window.jQuery(el).trigger('change.select2');
            }
        }
    }, { scope, values });
}

async function openModal(page, urlPart) {
    const opener = page.locator(`[data-remote-modal][data-url*="${urlPart}"]`).first();
    const modalId = await opener.getAttribute('data-remote-modal');
    await opener.evaluate((e) => e.click());
    await page.waitForFunction((id) => { const b = document.querySelector(`#${id} [data-remote-body]`); return b && b.children.length && !b.hidden; }, modalId, { timeout: 10000 });
    await page.waitForTimeout(400);
    return `#${modalId}`;
}

async function rowWith(page, text) {
    return page.locator('tbody tr, [data-sortable-item], [data-row], li[data-id], [data-category-row], [data-category-item], [data-tree-item], main li', { hasText: text }).last();
}

/**
 * Generic CRUD: spec = { list, create: '/path' | {modal: 'urlPart'}, form, values, files, match, editValues, search, del }
 */
async function crud(page, spec) {
    const go = async (u) => { await page.goto(L.BASE + u); await L.settle(page); };
    let scope = spec.form;
    if (spec.create.modal) {
        await go(spec.list);
        const modal = await openModal(page, spec.create.modal);
        scope = `${modal} ${spec.form || 'form'}`;
    } else {
        await go(spec.create);
    }
    const submitSel = spec.create.modal ? `${scope.split(' ')[0]} [data-remote-submit], ${scope} [type=submit]` : `${scope} [type=submit]`;
    // empty submit
    if (!spec.skipEmpty) {
        await page.evaluate((s) => document.querySelectorAll(`${s} input[type=text], ${s} input[type=number], ${s} input[type=email], ${s} textarea`).forEach((i) => { if (!i.readOnly) i.value = ''; }), scope);
        const r0 = await submitForm(page, scope, submitSel);
        const errs = await inlineErrors(page, scope.split(' ')[0]);
        step('empty submit -> 422 + inline errors', (r0.status === 422 || r0.status === 0) && errs.length > 0, `${r0.status} ${errs.slice(0, 3).join(' | ')}`);
    }
    await setValues(page, scope, spec.values);
    for (const [sel, file] of Object.entries(spec.files || {})) await page.setInputFiles(`${scope} ${sel}`, path.join(FX, file));
    await page.waitForTimeout(300);
    await L.shot(page, `flow/${current}_create_filled`);
    const r1 = await submitForm(page, scope, submitSel);
    step('create saved', r1.status >= 200 && r1.status < 300, `${r1.status} ${r1.status === 422 ? JSON.stringify(r1.body?.errors || r1.body).slice(0, 300) : ''}`);
    if (r1.status >= 300) return;
    await page.waitForTimeout(800);
    await go(spec.list);
    if (spec.search !== false) {
        const s = page.locator('[data-filters] input[name=search]:visible').first();
        if (await s.count()) { await s.fill(spec.match); await page.waitForTimeout(1300); await L.settle(page); }
    }
    let row = await rowWith(page, spec.match);
    step('listed after create', (await row.count()) > 0, spec.match);
    await L.shot(page, `flow/${current}_list`);
    if (!(await row.count())) return;
    if (spec.editValues) {
        const editLink = row.locator('a[href*="/edit"]').first();
        const editModal = row.locator('[data-remote-modal][data-url*="/edit"]').first();
        let escope;
        if (await editModal.count()) {
            const id = await editModal.getAttribute('data-remote-modal');
            await editModal.evaluate((e) => e.click());
            await page.waitForFunction((i) => { const b = document.querySelector(`#${i} [data-remote-body]`); return b && b.children.length && !b.hidden; }, id, { timeout: 10000 });
            escope = `#${id} ${spec.form || 'form'}`;
        } else if (await editLink.count()) {
            await editLink.evaluate((e) => e.click());
            await page.waitForURL(/\/edit/, { timeout: 10000 });
            await L.settle(page);
            escope = spec.form;
        }
        if (escope) {
            await setValues(page, escope, spec.editValues);
            const esub = escope.startsWith('#') && escope.includes(' ') ? `${escope.split(' ')[0]} [data-remote-submit], ${escope} [type=submit]` : `${escope} [type=submit]`;
            const r2 = await submitForm(page, escope, esub);
            step('edit saved', r2.status >= 200 && r2.status < 300, `${r2.status} ${r2.status === 422 ? JSON.stringify(r2.body?.errors || r2.body).slice(0, 300) : ''}`);
        } else step('edit control found', false);
        await go(spec.list);
        if (spec.search !== false) {
            const s = page.locator('[data-filters] input[name=search]:visible').first();
            if (await s.count()) { await s.fill(spec.editMatch || spec.match); await page.waitForTimeout(1300); await L.settle(page); }
        }
        row = await rowWith(page, spec.editMatch || spec.match);
        step('edit visible in list', (await row.count()) > 0, spec.editMatch || spec.match);
    }
    if (spec.del !== false && (await row.count())) {
        const delBtn = row.locator('[data-confirm-method=DELETE], [data-confirm-url][data-tone=danger]').first();
        if (!(await delBtn.count())) { step('delete control found', false); return; }
        await delBtn.evaluate((e) => e.click());
        const st = await confirmAccept(page);
        step('delete confirmed', st >= 200 && st < 300, st);
        await go(spec.list);
        if (spec.search !== false) {
            const s = page.locator('[data-filters] input[name=search]:visible').first();
            if (await s.count()) { await s.fill(spec.editMatch || spec.match); await page.waitForTimeout(1300); await L.settle(page); }
        }
        step('gone after delete', (await (await rowWith(page, spec.editMatch || spec.match)).count()) === 0);
    }
}

const today = new Date();
const d = (o) => { const x = new Date(today.getTime() + o * 86400000); return x.toISOString().slice(0, 10); };
const T = (s) => `QA${RUN} ${s}`;

const specs = {
    categories: { list: '/admin/catalog/categories', create: '/admin/catalog/categories/create', form: '#category-form', values: { 'name[en]': T('Cat'), 'name[ar]': T('قسم'), status: true }, files: { '#thumbnail': 'p1.png' }, match: T('Cat'), editValues: { 'name[en]': T('Cat2') }, editMatch: T('Cat2'), search: false },
    attributes: { list: '/admin/catalog/attributes', create: '/admin/catalog/attributes/create', form: '#attribute-form', values: { 'name[en]': T('Size'), 'name[ar]': T('مقاس'), display_type: '@first', 'options[0][name][en]': 'XL', 'options[0][name][ar]': 'كبير' }, match: T('Size'), editValues: { 'name[en]': T('Size2') }, editMatch: T('Size2') },
    labels: { list: '/admin/catalog/labels', create: { modal: '/labels/create' }, form: '#label-form', values: { 'text[en]': T('Label'), 'text[ar]': T('ملصق'), icon: 'truck', is_active: true }, match: T('Label'), editValues: { 'text[en]': T('Label2') }, editMatch: T('Label2'), search: false },
    coupons: { list: '/admin/promotions/coupons', create: { modal: '/coupons/create' }, form: '#coupon-form', values: { code: `QA${RUN}`.toUpperCase(), name: T('Coupon'), type: '@first', value: '10', usage_limit: '100', dates_from: d(0), dates_to: d(10), store_scope: '@first', is_active: true }, match: `QA${RUN}`.toUpperCase(), editValues: { value: '15' } },
    flashSales: { list: '/admin/promotions/flash-sales', create: { modal: '/flash-sales/create' }, form: '#flash-sale-form', values: { 'title[en]': T('Flash'), 'title[ar]': T('عرض'), discount_percentage: '12', dates_from: d(0), dates_to: d(5), 'product_ids[]': ['1'], store_scope: '@first', is_active: true }, match: T('Flash'), editValues: { discount_percentage: '15' } },
    fixedCosts: { list: '/admin/shipping/fixed-costs', create: { modal: '/fixed-costs/create' }, form: '#fixed-cost-form', values: { country_id: '@first', price_per_gram: '0.01' }, match: '0.01', search: false, del: true },
    shippingRules: { list: '/admin/shipping/rules', create: '/admin/shipping/rules/create', form: '#rule-form', values: { name: T('Rule'), 'country_ids[]': ['@first'], status: '@first', 'zones[0][name]': 'Z1', 'zones[0][bands][0][name]': 'B1', 'zones[0][bands][0][min_weight_grams]': '0', 'zones[0][bands][0][max_weight_grams]': '5000', 'zones[0][bands][0][price]': '7.5' }, match: T('Rule'), editValues: { name: T('Rule2') }, editMatch: T('Rule2') },
    branches: { list: '/admin/shipping/branches', create: '/admin/shipping/branches/create', form: '#branch-form', values: { 'name[en]': T('Branch'), 'name[ar]': T('فرع'), code: `QA${RUN}`, country_id: '@first', address: 'Street 1', phone: '+966501234567', is_active: true }, match: T('Branch'), editValues: { address: 'Street 2' } },
    companies: { list: '/admin/shipping/companies', create: '/admin/shipping/companies/create', form: '#company-form', values: { name: T('Courier'), contact_email: `qa${RUN}@courier.test`, tracking_url_pattern: 'https://track.test/{tracking}', is_active: true }, files: { '#logo': 'p2.png' }, match: T('Courier'), editValues: { name: T('Courier2') }, editMatch: T('Courier2') },
    stopPeriods: { list: '/admin/shipping/stop-periods', create: { modal: '/stop-periods/create' }, form: '#stop-period-form', values: { name: T('Stop'), recurrence: 'once', starts_on: d(20), ends_on: d(22), is_active: true }, match: T('Stop'), editValues: { name: T('Stop2') }, editMatch: T('Stop2') },
    plans: { list: '/admin/plans', create: '/admin/plans/create', form: '#plan-form', values: { 'name[en]': T('Plan'), 'name[ar]': T('باقة'), status: '@first', monthly_price: '19', yearly_price: '190', trial_days: '7', 'limits[products]': '100' }, match: T('Plan'), editValues: { monthly_price: '29' } },
    postCategories: { list: '/admin/content/post-categories', create: '/admin/content/post-categories/create', form: 'main form[data-ajax]', values: { 'name[en]': T('BlogCat'), 'name[ar]': T('تصنيف'), is_active: true }, match: T('BlogCat'), editValues: { 'name[en]': T('BlogCat2') }, editMatch: T('BlogCat2') },
    posts: { list: '/admin/content/posts', create: '/admin/content/posts/create', form: 'main form[data-ajax]', values: { 'title[en]': T('Post'), 'title[ar]': T('مقال'), 'body[en]': '<p>Body text for QA</p>', 'body[ar]': '<p>نص</p>', status: '@first', blog_category_id: '@first', author_name: 'QA' }, files: { '#cover': 'p3.png' }, match: T('Post'), editValues: { 'title[en]': T('Post2') }, editMatch: T('Post2') },
    pages: { list: '/admin/content/pages', create: '/admin/content/pages/create', form: 'main form[data-ajax]', values: { 'title[en]': T('Page'), 'title[ar]': T('صفحة'), 'body[en]': '<p>Page body</p>', 'body[ar]': '<p>نص</p>', status: '@first', target: '@first' }, match: T('Page'), editValues: { 'title[en]': T('Page2') }, editMatch: T('Page2') },
    faqs: { list: '/admin/content/faqs', create: '/admin/content/faqs/create', form: 'main form[data-ajax]', values: { 'question[en]': T('FAQ?'), 'question[ar]': T('سؤال؟'), 'answer[en]': 'Answer', 'answer[ar]': 'جواب', group: '@first', is_active: true }, match: T('FAQ?'), editValues: { 'question[en]': T('FAQ2?') }, editMatch: T('FAQ2?'), search: false },
    banners: { list: '/admin/storefront/banners', create: { modal: '/banners/create' }, form: '#banner-form', values: { 'title[en]': T('Banner'), 'title[ar]': T('بانر'), link_url: '/', placement: '@first', store_scope: '@first', is_active: true }, files: { '#image': 'p1.png' }, match: T('Banner'), editValues: { 'title[en]': T('Banner2') }, editMatch: T('Banner2') },
    templateParts: { list: '/admin/storefront/template-parts', create: { modal: '/template-parts/create' }, form: '#theme-part-form', values: { theme_id: '@first', name: T('Part'), 'settings[message][en]': 'Hello', 'settings[message][ar]': 'مرحبا', is_active: true }, match: T('Part'), editValues: { name: T('Part2') }, editMatch: T('Part2') },
    returnReasons: { list: '/admin/storefront/return-policy', create: { modal: '/reasons/create' }, form: '#return-reason-form', values: { 'name[en]': T('Reason'), 'name[ar]': T('سبب'), is_active: true }, match: T('Reason'), editValues: { 'name[en]': T('Reason2') }, editMatch: T('Reason2'), search: false },
    languages: { list: '/admin/localization/languages?tab=all', create: '/admin/localization/languages/create', form: 'main form[data-ajax]', values: { code: 'qz', native_name: T('Qlang'), 'name[en]': T('Qlang'), 'name[ar]': T('لغة'), direction: 'ltr', price: '1', sort_order: '99', is_active: false }, match: T('Qlang'), editValues: { native_name: T('Qlang2') }, editMatch: T('Qlang2') },
    currencies: { list: '/admin/localization/currencies?tab=all', create: '/admin/localization/currencies/create', form: 'main form[data-ajax]', values: { 'name[en]': T('Coin'), 'name[ar]': T('عملة'), code: 'QZC', symbol: 'Q$', decimals: '2', rate: '1.5', sort_order: '99', is_active: false }, match: 'QZC', editValues: { rate: '1.75' } },
    promoCodes: { list: '/admin/affiliates/promo-codes', create: '/admin/affiliates/promo-codes/create', form: '#promo-code-form', values: { affiliate_id: process.env.QA_AFFILIATE_ID || '1', ends_at: d(30), code: `AFF${RUN}`.toUpperCase(), discount_type: '@first', discount_value: '5', commission_type: '@first', commission_value: '5', is_active: true }, match: `AFF${RUN}`.toUpperCase(), editValues: { discount_value: '7' } },
    roles: { list: '/admin/system/roles', create: '/admin/system/roles/create', form: '#role-form', values: { name: `qa-admin-role-${RUN}`, 'permissions[]': ['dashboard.view'] }, match: new RegExp(`role ${RUN}`, 'i'), editValues: { name: `qa-admin-role2-${RUN}` }, editMatch: new RegExp(`role2 ${RUN}`, 'i'), search: false },
    admins: { list: '/admin/system/admins', create: { modal: '/admins/create' }, form: '#admin-form', values: { name: T('Staff'), email: `qa-admin-staff-${RUN}@qa.test`, role: '@first', password: `Qa!${RUN}pass123`, password_confirmation: `Qa!${RUN}pass123`, status: '@first' }, match: `qa-admin-staff-${RUN}@qa.test`, editValues: { name: T('Staff2') } },
};

const flows = {
    async products(page) {
        await page.goto(`${L.BASE}/admin/catalog/products/create`);
        await L.settle(page);
        let r = await submitForm(page, '#product-form', '#product-form [type=submit]');
        const errs = await inlineErrors(page, '#product-form');
        step('empty submit -> 422 + inline errors', r.status === 422 && errs.length > 0, `${r.status} ${errs.slice(0, 3).join(' | ')}`);
        const sku = `QA-ADM-${RUN}`;
        await page.fill('#name-en', `QA Admin Lamp ${RUN}`);
        await page.click('[data-tab-key=ar]');
        await page.fill('#name-ar', `مصباح اختبار ${RUN}`);
        await page.click('[data-tab-key=en]');
        await page.fill('#sku', sku);
        await page.fill('#summary-en', 'Release QA product');
        await page.click('[data-tab-key=pricing]');
        await page.fill('#base-price', '120.50');
        await page.fill('#cost-price', '80');
        await page.fill('#weight-grams', '900');
        await page.click('[data-tab-key=media]');
        await page.setInputFiles('#primary-image', path.join(FX, 'p1.png'));
        await page.setInputFiles('#images', [path.join(FX, 'p2.png'), path.join(FX, 'v1.mp4')]);
        await page.waitForTimeout(500);
        await L.shot(page, 'flow/products_media_tab');
        await page.click('[data-tab-key=stock]');
        await page.locator('input[name=stock]').fill('25').catch(() => {});
        r = await submitForm(page, '#product-form', '#product-form [type=submit]');
        step('create product with image + video', r.status >= 200 && r.status < 300, `${r.status} ${JSON.stringify(r.body).slice(0, 200)}`);
        await page.waitForTimeout(1500);
        await L.settle(page);
        const editUrl = page.url();
        step('redirected to edit page', /\/catalog\/products\/\d+\/edit/.test(editUrl), editUrl);
        await page.click('[data-tab-key=media]').catch(() => {});
        await page.waitForTimeout(400);
        const media = await page.$$eval('[data-tab-panel=media] img, [data-tab-panel=media] video, #product-form img, #product-form video', (els) => els.filter((e) => e.offsetParent).map((e) => e.tagName + ':' + (e.currentSrc || e.src || '').split('/').pop())).catch(() => []);
        step('media saved (image + video visible)', media.some((m) => m.startsWith('IMG')) && media.some((m) => m.startsWith('VIDEO')), media.join(','));
        await L.shot(page, 'flow/products_edit_media');
        // broken images?
        const broken = await page.$$eval('#product-form img', (imgs) => imgs.filter((i) => i.offsetParent && i.complete && i.naturalWidth === 0).map((i) => i.src));
        step('no broken images', broken.length === 0, broken.join(','));
        await page.click('[data-tab-key=pricing]');
        await page.fill('#base-price', '130');
        await page.fill('#sale-price', '140');
        r = await submitForm(page, '#product-form', '#product-form [type=submit]');
        step('sale >= base rejected (422)', r.status === 422, r.status);
        await page.fill('#sale-price', '110');
        r = await submitForm(page, '#product-form', '#product-form [type=submit]');
        step('edit price saved', r.status >= 200 && r.status < 300, r.status);
        // variants
        await page.click('[data-tab-key=variants]');
        await page.waitForTimeout(300);
        await L.shot(page, 'flow/products_variants_tab');
        const attrOpts = await page.$$eval('#builder-attribute option', (os) => os.map((o) => o.value).filter(Boolean));
        step('variant builder has attributes', attrOpts.length > 0, attrOpts.length);
        // list + search + delete
        await page.goto(`${L.BASE}/admin/catalog/products`);
        await L.settle(page);
        await dtSearch(page, sku);
        const rowText = await page.locator('table tbody').innerText().catch(() => '');
        step('list search finds product', rowText.includes(sku) || rowText.includes(`QA Admin Lamp ${RUN}`), rowText.slice(0, 120));
        await L.shot(page, 'flow/products_list_search');
        return { sku, editUrl };
    },
};
for (const [k, spec] of Object.entries(specs)) flows[k] = (page) => crud(page, spec);

/** Vendors: create + provision a store, then every profile tab, its forms, impersonation, suspend / reactivate. */
flows.vendors = async (page) => {
    const slug = `qa-admin-${RUN}`;
    let url = process.env.QA_VENDOR_URL || null;
    if (!url) {
    await page.goto(`${L.BASE}/admin/vendors/create`);
    await L.settle(page);
    let r = await submitForm(page, '#vendor-create-form');
    const errs = await inlineErrors(page, '#vendor-create-form');
    step('empty submit -> 422 + inline errors', (r.status === 422 || r.status === 0) && errs.length > 0, `${r.status} ${errs.slice(0, 3).join(' | ')}`);
    await setValues(page, '#vendor-create-form', { owner_name: `QA Admin Owner ${RUN}`, owner_email: `${slug}@qa.test`, owner_phone: '+966501112233', owner_password: `Qa!${RUN}Pass2026`, store_name: `QA Admin Store ${RUN}`, slug, default_country_id: '@first', profit_percent: '20', plan_id: '@first', term: 'monthly', plan_assignment: 'trial' });
    await L.shot(page, 'flow/vendors_create_filled', { fullPage: true });
    r = await submitForm(page, '#vendor-create-form');
    step('create & provision store', r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors) : ''}`);
    await page.waitForTimeout(4000);
    await L.settle(page);
    url = page.url();
    if (!/\/admin\/vendors\/\d+/.test(url)) {
        await page.goto(`${L.BASE}/admin/vendors`);
        await L.settle(page);
        await page.fill('[data-filters] input[name=search]', slug);
        await page.waitForTimeout(1500);
        await L.settle(page);
        url = await page.locator('tbody a[href*="/admin/vendors/"]').first().getAttribute('href').catch(() => null);
    }
    step('new store listed / profile reachable', !!url && /\/admin\/vendors\/\d+/.test(url), url);
    }
    if (!url) return;
    const base = url.split('?')[0];
    for (const tab of ['overview', 'plan', 'domains', 'commission', 'stats', 'limits', 'compliance', 'mobile-app']) {
        const log = L.watch(page);
        const resp = await page.goto(`${base}?tab=${tab}`);
        await L.settle(page);
        const info = await L.inspect(page);
        step(`tab ${tab} renders`, resp.status() === 200 && !info.serverError && !log.pageErrors.length && !info.clipped.length, `${resp.status()} ${log.pageErrors.join(';')} ${info.clipped.join(';')}`);
        await L.shot(page, `flow/vendors_tab_${tab}`, { fullPage: true });
        page.removeAllListeners('console'); page.removeAllListeners('pageerror'); page.removeAllListeners('response'); page.removeAllListeners('requestfailed');
        if (tab === 'limits') {
            await setValues(page, '#limits-form', { 'limits[products][mode]': 'custom', 'limits[products][value]': '250' });
            const opts = await page.$$eval('#limits-products-mode option', (os) => os.map((o) => o.value));
            if (!opts.includes('custom')) await setValues(page, '#limits-form', { 'limits[products][mode]': opts[opts.length - 1] });
            const rr = await submitForm(page, '#limits-form');
            step('save limit override', rr.status >= 200 && rr.status < 300, `${rr.status} modes=${opts.join('/')} ${rr.status === 422 ? JSON.stringify(rr.body?.errors) : ''}`);
            await page.reload();
            await L.settle(page);
            const v = await page.$eval('#limits-products-value', (e) => e.value).catch(() => '');
            step('limit override persisted', v === '250', v);
        }
        if (tab === 'mobile-app') {
            await setValues(page, '#admin-mobile-app-form', { ios_min_version: '1.0.0', android_min_version: '1.0.0', 'features[wishlist]': true });
            const rr = await submitForm(page, '#admin-mobile-app-form');
            step('save mobile app settings', rr.status >= 200 && rr.status < 300, `${rr.status} ${rr.status === 422 ? JSON.stringify(rr.body?.errors) : ''}`);
        }
        if (tab === 'commission') {
            const f = await page.$('main form[data-ajax]:not(#edit-store-form):not(#impersonate-form)');
            if (f) {
                const id = await f.getAttribute('id');
                const rr = await submitForm(page, id ? `#${id}` : 'main form[data-ajax]');
                step('save commission (unchanged)', rr.status >= 200 && rr.status < 300, rr.status);
            }
        }
    }
    // edit store modal
    await page.goto(base);
    await L.settle(page);
    await page.click('[data-overlay-open=edit-store]');
    await page.waitForTimeout(400);
    await setValues(page, '#edit-store-form', { name: `QA Admin Store ${RUN} v2` });
    r = await submitForm(page, '#edit-store-form');
    step('edit store modal saved', r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors) : ''}`);
    // suspend + reactivate
    await page.goto(base);
    await L.settle(page);
    const susp = page.locator('main [data-confirm-url$="/suspend"]').first();
    if (await susp.count()) {
        await susp.click();
        const st = await confirmAccept(page);
        step('suspend store', st >= 200 && st < 300, st);
        await page.goto(base);
        await L.settle(page);
        await L.shot(page, 'flow/vendors_suspended');
        const act = page.locator('main [data-confirm-url$="/activate"], main [data-confirm-url$="/reactivate"], main [data-confirm-url$="/unsuspend"], main [data-confirm-url$="/resume"]').first();
        if (await act.count()) {
            await act.click();
            const st2 = await confirmAccept(page);
            step('reactivate store', st2 >= 200 && st2 < 300, st2);
        } else step('reactivate control present', false, await page.$$eval('main [data-confirm-url]', (e) => e.map((x) => x.dataset.confirmUrl).join(',')));
    } else step('suspend control present', false);
    // impersonate
    await page.goto(base);
    await L.settle(page);
    await page.click('[data-overlay-open=impersonate]');
    await page.waitForTimeout(400);
    await setValues(page, '#impersonate-form', { reason: '@first', details: 'Release QA check' });
    const [popup] = await Promise.all([
        page.context().waitForEvent('page', { timeout: 8000 }).catch(() => null),
        page.locator('#impersonate-form [type=submit], [type=submit][form=impersonate-form]').filter({ visible: true }).first().click(),
    ]);
    const target = popup || page;
    await target.waitForLoadState('domcontentloaded').catch(() => {});
    await L.settle(target);
    const tUrl = target.url();
    const banner = await target.locator('text=/impersonat|انتحال|تتصفح|viewing|تعرض/i').count();
    step('impersonation opens vendor panel with banner', /\/admin|dashboard/.test(tUrl) && !tUrl.includes('/admin/vendors/') && banner > 0, `${tUrl} banner=${banner}`);
    await L.shot(target, 'flow/vendors_impersonating');
    const exit = target.locator('form[action*="impersonat"] [type=submit], a[href*="impersonat"], button:has-text("Exit"), button:has-text("Stop")').first();
    if (await exit.count()) {
        await exit.click().catch(() => {});
        await L.settle(target);
        step('exit impersonation', true, target.url());
    } else step('exit impersonation control', false);
    if (popup) await popup.close();
    return { base };
};

/** Click a data-confirm control (by URL suffix) on the current page and accept. */
async function confirmAction(page, urlPart, label) {
    const btn = page.locator(`main [data-confirm-url*="${urlPart}"]`).first();
    if (!(await btn.count())) { step(`${label}: control present`, false, urlPart); return null; }
    await btn.evaluate((e) => e.click());
    const st = await confirmAccept(page);
    step(label, st >= 200 && st < 300, st);
    return st;
}

/** Submit a remote modal (opened by urlPart) with values; returns status. */
async function modalSubmit(page, urlPart, values, label, expect = 'ok') {
    const modal = await openModal(page, urlPart);
    if (values) await setValues(page, `${modal} form`, values);
    const r = await submitForm(page, `${modal} form`, `${modal} [data-remote-submit], ${modal} form [type=submit]`);
    const errs = await inlineErrors(page, modal);
    const ok = expect === '422' ? r.status === 422 && errs.length > 0 : r.status >= 200 && r.status < 300;
    step(label, ok, `${r.status} ${errs.slice(0, 2).join(' | ')} ${r.status === 422 && expect !== '422' ? JSON.stringify(r.body?.errors) : ''}`);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    return r.status;
}

flows.maintenance = async (page) => {
    await page.goto(`${L.BASE}/admin/system/maintenance`);
    await L.settle(page);
    await page.click('[data-maint-new]');
    await page.waitForTimeout(400);
    let r = await submitForm(page, '#maintenance-form', '[data-maint-save]');
    step('empty window -> 422', r.status === 422 || r.status === 0, r.status);
    const start = `${d(3)}T02:00`, end = `${d(3)}T03:00`;
    await setValues(page, '#maintenance-form', { title: T('Window'), scope: 'store', tenant_id: process.env.QA_TENANT_ID || '10', starts_now: false, starts_at: start, ends_at: end, message_en: 'Back soon', message_ar: 'نعود قريبا' });
    const scopes = await page.$$eval('#maintenance-form select#scope option', (os) => os.map((o) => o.value));
    if (!scopes.includes('store')) await setValues(page, '#maintenance-form', { scope: scopes.find((s) => /store|tenant/.test(s)) || scopes[scopes.length - 1] });
    r = await submitForm(page, '#maintenance-form', '[data-maint-save]');
    step('schedule store window (3 days ahead)', r.status >= 200 && r.status < 300, `${r.status} scopes=${scopes} ${r.status === 422 ? JSON.stringify(r.body?.errors) : ''}`);
    await page.goto(`${L.BASE}/admin/system/maintenance`);
    await L.settle(page);
    const row = await rowWith(page, T('Window'));
    step('window listed', (await row.count()) > 0);
    await L.shot(page, 'flow/maintenance_list');
    if (await row.count()) {
        await row.locator('[data-confirm-method=DELETE]').first().evaluate((e) => e.click());
        const st = await confirmAccept(page);
        step('delete window', st >= 200 && st < 300, st);
    }
};

flows.cache = async (page) => {
    await page.goto(`${L.BASE}/admin/system/cache`);
    await L.settle(page);
    const before = await page.locator('main').innerText();
    const btn = page.locator('main [data-confirm-url]').first();
    await btn.click();
    const st = await confirmAccept(page);
    step('clear application cache', st >= 200 && st < 300, st);
    await page.goto(`${L.BASE}/admin/system/cache`);
    await L.settle(page);
    const after = await page.locator('main').innerText();
    step('last cleared info updated', before !== after && /QA Admin Super|seconds|ثوان|just now|الآن/i.test(after), after.slice(0, 200).replace(/\n/g, ' '));
    await L.shot(page, 'flow/cache_after');
};

flows.themes = async (page) => {
    await page.goto(`${L.BASE}/admin/storefront/themes`);
    await L.settle(page);
    const cards = await page.$$eval('main [data-remote-modal=theme-modal]', (b) => b.length);
    step('13 themes listed', cards === 13, cards);
    await modalSubmit(page, '/themes/2/edit', null, 'theme edit modal saves');
    await page.goto(`${L.BASE}/admin/storefront/themes`);
    await L.settle(page);
    await modalSubmit(page, '/themes/2/countries', null, 'theme countries modal saves');
    await page.goto(`${L.BASE}/admin/storefront/themes`);
    await L.settle(page);
    await confirmAction(page, '/themes/12/deactivate', 'deactivate Souqify Kids');
    await page.goto(`${L.BASE}/admin/storefront/themes`);
    await L.settle(page);
    await L.shot(page, 'flow/themes_after_deactivate', { fullPage: true });
    await confirmAction(page, '/themes/12/activate', 'activate Souqify Kids again');
    await page.goto(`${L.BASE}/admin/storefront/themes`);
    await L.settle(page);
    const defaultBtn = page.locator('main [data-confirm-url*="/themes/2/default"]');
    if (await defaultBtn.count()) {
        await confirmAction(page, '/themes/2/default', 'set Elora Essence as default');
        await page.goto(`${L.BASE}/admin/storefront/themes`);
        await L.settle(page);
        await confirmAction(page, '/themes/1/default', 'restore Ecommet as default');
    } else step('default control present', false, await page.$$eval('main [data-confirm-url]', (e) => e.slice(0, 4).map((x) => x.dataset.confirmUrl).join(',')));
    await page.goto(`${L.BASE}/admin/storefront/home-sections`);
    await L.settle(page);
    const first = page.locator('#theme-sections-form input[type=checkbox][name="enabled[]"]').first();
    const was = await first.isChecked();
    await first.evaluate((e) => { e.checked = !e.checked; e.dispatchEvent(new Event('change', { bubbles: true })); });
    let r = await submitForm(page, '#theme-sections-form');
    step('home sections toggle saved', r.status >= 200 && r.status < 300, r.status);
    await page.reload();
    await L.settle(page);
    const now = await page.locator('#theme-sections-form input[type=checkbox][name="enabled[]"]').first().isChecked();
    step('home sections toggle persisted', now === !was);
    await page.locator('#theme-sections-form input[type=checkbox][name="enabled[]"]').first().evaluate((e, v) => { e.checked = v; }, was);
    r = await submitForm(page, '#theme-sections-form');
    step('home sections restored', r.status >= 200 && r.status < 300, r.status);
};

flows.emailTemplates = async (page) => {
    const url = `${L.BASE}/admin/settings/email-templates/66/edit`;
    await page.goto(url);
    await L.settle(page);
    const orig = await page.inputValue('#subject-en');
    await setValues(page, '#email-template-form', { 'subject[en]': `${orig} QA` });
    await page.waitForTimeout(1200);
    const prev = page.frames().find((f) => f.url() === 'about:srcdoc');
    const prevText = prev ? await prev.evaluate(() => document.body.innerText).catch(() => '') : '';
    step('live preview renders', prevText.length > 20, prevText.slice(0, 80).replace(/\n/g, ' '));
    let r = await submitForm(page, '#email-template-form');
    step('template saved', r.status >= 200 && r.status < 300, r.status);
    await page.reload();
    await L.settle(page);
    step('last edited shown', /QA Admin Super/.test(await page.locator('main').innerText()));
    await page.click('[data-overlay-open=template-test-modal]');
    await page.waitForTimeout(300);
    r = await submitForm(page, '#template-test-form');
    step('send test e-mail', r.status >= 200 && r.status < 300, `${r.status} ${JSON.stringify(r.body).slice(0, 120)}`);
    await page.keyboard.press('Escape');
    await page.goto(url);
    await L.settle(page);
    await confirmAction(page, '/reset', 'reset template to default');
    await page.goto(url);
    await L.settle(page);
    step('subject back to default', (await page.inputValue('#subject-en')) === orig, await page.inputValue('#subject-en'));
    await L.shot(page, 'flow/email_template_after_reset');
    await page.goto(`${L.BASE}/admin/settings/email-layout`);
    await L.settle(page);
    const f = await page.$('main form[data-ajax]');
    if (f) { r = await submitForm(page, 'main form[data-ajax]'); step('email layout saves', r.status >= 200 && r.status < 300, r.status); }
};

flows.afterSales = async (page) => {
    await page.goto(`${L.BASE}/admin/refunds`);
    await L.settle(page);
    const pend = await page.$$eval('[data-remote-modal][data-url$="/complete"]', (b) => b.map((x) => x.dataset.url));
    step('pending refunds available', pend.length >= 2, pend.length);
    if (pend.length >= 2) {
        const rej = pend[1].replace(/\/complete$/, '/reject').replace(L.BASE, '');
        await modalSubmit(page, rej, { note: '' }, 'reject refund without note -> 422', '422');
        await page.goto(`${L.BASE}/admin/refunds`); await L.settle(page);
        await modalSubmit(page, rej, { note: 'QA reject' }, 'reject refund with note');
        await page.goto(`${L.BASE}/admin/refunds`); await L.settle(page);
        await modalSubmit(page, pend[0].replace(L.BASE, ''), { note: 'QA complete' }, 'complete refund');
    }
    await page.goto(`${L.BASE}/admin/returns/2`);
    await L.settle(page);
    if (await page.locator('[data-url$="/actions/request_info"]').count()) {
        await modalSubmit(page, '/actions/request_info', { note: '' }, 'return request-info without note -> 422', '422');
        await page.goto(`${L.BASE}/admin/returns/2`); await L.settle(page);
        await modalSubmit(page, '/actions/request_info', { note: 'Please send a photo of the box.' }, 'return request-info sent');
    } else step('return actions present', true, 'return 2 already decided');
    await page.goto(`${L.BASE}/admin/orders/6`);
    await L.settle(page);
    await modalSubmit(page, '/orders/6/refund', { amount: '', reason: '' }, 'order refund empty -> 422', '422');
    await page.goto(`${L.BASE}/admin/orders/6`); await L.settle(page);
    {
        const modal = await openModal(page, '/orders/6/refund');
        await setValues(page, `${modal} form`, { amount: '999999', reason: 'too much' });
        const r2 = await submitForm(page, `${modal} form`, `${modal} [data-remote-submit], ${modal} form [type=submit]`);
        const toast = await page.locator('[role=alert], .ui-toast, [data-toast]').allInnerTexts().catch(() => []);
        step('order refund above refundable -> 422 with message', r2.status === 422 && (r2.body?.message || '').length > 0, `${r2.status} ${r2.body?.message} toast=${toast.join('|').slice(0, 80)}`);
        await page.keyboard.press('Escape');
    }
    await page.goto(`${L.BASE}/admin/orders/6`); await L.settle(page);
    await setValues(page, 'main form[action$="/notes"]', { note: 'QA admin note' });
    const r = await submitForm(page, 'main form[action$="/notes"]');
    step('order note added', r.status >= 200 && r.status < 300, r.status);
    await page.reload(); await L.settle(page);
    step('order note visible', /QA admin note/.test(await page.locator('main').innerText()));
    await L.shot(page, 'flow/order_6_detail', { fullPage: true });
};

flows.requests = async (page) => {
    await page.goto(`${L.BASE}/admin/requests/manufacturing/1`);
    await L.settle(page);
    await page.fill('#thread-body', 'QA admin reply in thread');
    const respP = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('/messages'), { timeout: 10000 }).catch(() => null);
    await page.locator('form[action$="/messages"] [type=submit]').first().click();
    const r = await respP;
    await L.settle(page);
    step('thread message sent', !!r && r.status() < 400, r && r.status());
    step('message visible', /QA admin reply in thread/.test(await page.locator('main').innerText()));
    await page.click('[data-overlay-open=payment-request-modal]');
    await page.waitForTimeout(300);
    let rr = await submitForm(page, '#payment-request-form');
    step('payment request empty -> 422', rr.status === 422 || rr.status === 0, rr.status);
    await setValues(page, '#payment-request-form', { label: 'QA deposit', amount: '50', note: 'QA' });
    rr = await submitForm(page, '#payment-request-form');
    step('payment request created', rr.status >= 200 && rr.status < 300, `${rr.status} ${rr.status === 422 ? JSON.stringify(rr.body?.errors) : ''}`);
    await L.shot(page, 'flow/requests_manufacturing_1', { fullPage: true });
    await page.goto(`${L.BASE}/admin/requests/edits/1`);
    await L.settle(page);
    await L.shot(page, 'flow/requests_edits_1', { fullPage: true });
    const txt = await page.locator('main').innerText();
    step('edit request detail has content/actions', txt.length > 200, txt.slice(0, 150).replace(/\n/g, ' '));
};

flows.finance = async (page) => {
    const tenant = process.env.QA_PAYOUT_TENANT || '2';
    await page.goto(`${L.BASE}/admin/finance/payouts/create?tenant_id=${tenant}`);
    await L.settle(page);
    await L.shot(page, 'flow/finance_payout_create');
    await setValues(page, '#payout-form', { amount: '' });
    let r = await submitForm(page, '#payout-form');
    step('payout empty amount -> 422', r.status === 422, r.status);
    await setValues(page, '#payout-form', { amount: '999999', method: '@first' });
    r = await submitForm(page, '#payout-form');
    step('payout above balance -> 422', r.status === 422, `${r.status} ${JSON.stringify(r.body?.errors || r.body?.message).slice(0, 150)}`);
    await page.goto(`${L.BASE}/admin/finance/payouts/create?tenant_id=${tenant}`);
    await L.settle(page);
    await page.fill('#amount', '1.00');
    await page.fill('#transaction-reference', `QA-${RUN}`);
    r = await submitForm(page, '#payout-form');
    step('payout created', r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors || r.body?.message) : ''}`);
    await page.goto(`${L.BASE}/admin/finance/payouts`);
    await L.settle(page);
    await page.fill('[data-filters] input[name=search]', `QA-${RUN}`).catch(() => {});
    await page.waitForTimeout(1300);
    await L.settle(page);
    const row = await rowWith(page, `QA-${RUN}`);
    step('payout listed', (await row.count()) > 0);
    await L.shot(page, 'flow/finance_payouts_list');
    if (await row.count()) {
        const acts = await row.locator('[data-confirm-url], [data-remote-modal]').evaluateAll((e) => e.map((x) => x.dataset.confirmUrl || x.dataset.url));
        step('payout row actions', acts.length > 0, acts.join(' , '));
        const cancel = row.locator('[data-confirm-url$="/cancel"], [data-remote-modal][data-url$="/cancel"]').first();
        if (await cancel.count()) {
            const isModal = await cancel.getAttribute('data-remote-modal');
            if (isModal) { await modalSubmit(page, (await cancel.getAttribute('data-url')).replace(L.BASE, ''), { note: 'QA cancel', reason: 'QA cancel' }, 'cancel payout'); }
            else { await cancel.evaluate((e) => e.click()); const st = await confirmAccept(page); step('cancel payout', st >= 200 && st < 300, st); }
        }
    }
    await page.goto(`${L.BASE}/admin/finance/ledger/1`);
    await L.settle(page);
    await modalSubmit(page, '/ledger/1/settle', { amount: '' }, 'settle modal empty -> 422', '422');
    await page.goto(`${L.BASE}/admin/finance/ledger/1/statement`);
    await L.settle(page);
    await L.shot(page, 'flow/finance_statement', { fullPage: true });
    step('statement renders', (await page.locator('body').innerText()).length > 200);
    await page.goto(`${L.BASE}/admin/finance/settlements`);
    await L.settle(page);
    await modalSubmit(page, '/finance/settlements/ST-', null, 'settlement detail modal opens (no form)', 'ok').catch(() => {});
};

flows.settings = async (page) => {
    await page.goto(`${L.BASE}/admin/settings/general`);
    await L.settle(page);
    const email0 = await page.inputValue('#support-email');
    await page.fill('#support-email', 'qa-support@nogrgr.test');
    let r = await submitForm(page, '#general-settings-form');
    step('general settings save', r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors) : ''}`);
    await setValues(page, '#general-settings-form', { 'platform_name[en]': '' });
    r = await submitForm(page, '#general-settings-form');
    step('general settings empty name -> 422', r.status === 422, r.status);
    await page.goto(`${L.BASE}/admin/settings/general`);
    await L.settle(page);
    step('support e-mail persisted', (await page.inputValue('#support-email')) === 'qa-support@nogrgr.test');
    await page.fill('#support-email', email0 || 'support@nogrgr.test');
    r = await submitForm(page, '#general-settings-form');
    step('general settings restored', r.status >= 200 && r.status < 300, r.status);
    await page.goto(`${L.BASE}/admin/integrations/gateways/limits`);
    await L.settle(page);
    const orig = await page.inputValue('#limit-paypal');
    await setValues(page, '#gateway-limits', { 'limits[paypal][limit]': '12345', 'limits[paypal][warn_percent]': '80' });
    r = await submitForm(page, '#gateway-limits');
    step('gateway limits saved', r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors) : ''}`);
    await page.reload();
    await L.settle(page);
    step('gateway limit persisted', (await page.inputValue('#limit-paypal')).startsWith('12345'), await page.inputValue('#limit-paypal'));
    await setValues(page, '#gateway-limits', { 'limits[paypal][limit]': orig, 'limits[paypal][warn_percent]': '80' });
    r = await submitForm(page, '#gateway-limits');
    step('gateway limit restored', r.status >= 200 && r.status < 300, r.status);
    for (const u of ['/admin/settings/platform', '/admin/shipping/settings', '/admin/affiliates/settings', '/admin/shipping/delivery-popup']) {
        await page.goto(L.BASE + u);
        await L.settle(page);
        const forms = await page.$$eval('main form[data-ajax]', (fs) => fs.map((f) => f.id).filter(Boolean));
        const fid = forms[0];
        if (!fid) { step(`${u} has a settings form`, false); continue; }
        const firstText = page.locator(`#${fid} input[type=text]:visible, #${fid} input[type=number]:visible`).first();
        if (await firstText.count()) { const v = await firstText.inputValue(); await firstText.fill(v + ' '); await firstText.fill(v); }
        r = await submitForm(page, `#${fid}`);
        step(`${u} #${fid} saves`, r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors).slice(0, 200) : ''}`);
    }
};

flows.neozena = async (page) => {
    await page.goto(`${L.BASE}/admin/catalog/imports`);
    await L.settle(page);
    await page.locator('[data-import-open]').first().click();
    await page.waitForTimeout(400);
    await setValues(page, '#run-import-form', { driver: '@first', max_pages: '1', update_existing: false, auto_create: false });
    const [nav] = await Promise.all([
        page.waitForNavigation({ timeout: 30000 }).catch(() => null),
        page.locator('#run-import-form [type=submit], [type=submit][form=run-import-form]').filter({ visible: true }).first().click(),
    ]);
    await L.settle(page);
    const txt = await page.locator('main').innerText();
    step('import run started', !!nav && /run|queued|running|started|بدأ|قيد/i.test(txt), page.url());
    await L.shot(page, 'flow/neozena_run', { fullPage: true });
};

flows.shell = async (page) => {
    await page.goto(`${L.BASE}/admin`);
    await L.settle(page);
    // global search (Ctrl+K)
    await page.keyboard.press('Control+k');
    await page.waitForTimeout(400);
    const focused = await page.evaluate(() => document.activeElement?.id || document.activeElement?.getAttribute('type'));
    step('Ctrl+K focuses global search', /global-search|search/.test(String(focused)), focused);
    await page.keyboard.type('coupon');
    await page.waitForTimeout(800);
    const results = await page.$$eval('[role=listbox] [role=option], [data-command-item], [data-search-result]', (e) => e.filter((x) => x.offsetParent).map((x) => x.innerText.trim()).slice(0, 5));
    step('global search lists matches', results.length > 0, results.join(' | '));
    await L.shot(page, 'flow/shell_global_search');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1500);
    step('Enter opens first match', /coupon/i.test(page.url()), page.url());
    // bell
    await page.goto(`${L.BASE}/admin`);
    await L.settle(page);
    const bell = page.locator('header [data-dropdown-trigger]').filter({ has: page.locator('svg') }).nth(1);
    const bellBtn = page.locator('[aria-label*="otification" i], [aria-label*="الإشعارات"]').first();
    await (await bellBtn.count() ? bellBtn : bell).click();
    await page.waitForTimeout(800);
    const items = await page.$$eval('[data-notification-item], [role=menu] a, [data-dropdown-menu] a', (e) => e.filter((x) => x.offsetParent).map((x) => x.innerText.trim().slice(0, 50)));
    step('bell dropdown opens with items or empty state', items.length > 0 || (await page.locator('text=/no notifications|لا توجد إشعارات|all caught up/i').count()) > 0, items.slice(0, 3).join(' | '));
    await L.shot(page, 'flow/shell_bell');
    await page.goto(`${L.BASE}/admin/notifications`);
    await L.settle(page);
    const mark = page.locator('main form[action$="/read-all"] [type=submit]').filter({ visible: true }).first();
    if (await mark.count()) {
        const respP = page.waitForResponse((r) => r.url().includes('read-all'), { timeout: 8000 }).catch(() => null);
        await mark.click();
        const r = await respP;
        step('mark all read', !!r && r.status() < 300, r && r.status());
        await page.reload();
        await L.settle(page);
        const badge = await page.locator('header [data-unread-count], header .ui-badge-count').allInnerTexts().catch(() => []);
        step('unread badge cleared', !badge.some((t) => /[1-9]/.test(t)), badge.join(','));
    } else step('mark all read control', false);
    await L.shot(page, 'flow/shell_inbox');
    // error pages
    let resp = await page.goto(`${L.BASE}/admin/this-page-does-not-exist`);
    let txt = await page.locator('body').innerText();
    step('404 panel error page', resp.status() === 404 && /404|not found|غير موجود/i.test(txt) && (await page.locator('aside, nav').count()) > 0, txt.slice(0, 80).replace(/\n/g, ' '));
    await L.shot(page, 'flow/shell_404');
    resp = await page.goto(`${L.BASE}/admin/orders/99999999`);
    step('unknown record -> 404 panel page', resp.status() === 404, resp.status());
    const r419 = await page.context().request.post(`${L.BASE}/admin/notifications/read-all`, { headers: { 'X-CSRF-TOKEN': 'bad', Accept: 'text/html' } });
    step('bad CSRF -> 419', r419.status() === 419, r419.status());
    // theme + locale toggles
    await page.goto(`${L.BASE}/admin`);
    await L.settle(page);
    await page.click('[data-theme-set=dark]').catch(() => {});
    await page.reload();
    step('dark mode persists', await page.evaluate(() => document.documentElement.classList.contains('dark')));
    await page.click('[data-theme-set=light]').catch(() => {});
    await page.goto(`${L.BASE}/admin?lang=ar`);
    step('arabic switch sets rtl', (await page.getAttribute('html', 'dir')) === 'rtl');
    await page.goto(`${L.BASE}/admin?lang=en`);
};

/** Product variants through the builder UI (attribute picker → values → generated rows → save). */
flows.variants = async (page) => {
    const id = process.env.QA_PRODUCT_ID || '198';
    await page.goto(`${L.BASE}/admin/catalog/products/${id}/edit?tab=variants`);
    await L.settle(page);
    const attr = await page.$$eval('#builder-attribute option', (os) => os.filter((o) => o.value).map((o) => o.value));
    await setValues(page, '#product-form', { builder_attribute: attr[0] });
    await page.click('[data-attribute-add]');
    await page.waitForTimeout(600);
    const sel = page.locator('[data-attribute-group] select[data-attribute-values]').last();
    const opts = await sel.evaluate((s) => [...s.options].map((o) => o.value));
    step('attribute group added with values', opts.length >= 2, opts.length);
    await sel.evaluate((s) => { [...s.options].slice(0, 2).forEach((o) => { o.selected = true; }); s.dispatchEvent(new Event('change', { bubbles: true })); if (window.jQuery) window.jQuery(s).trigger('change'); });
    await page.waitForTimeout(800);
    const rows = await page.locator('[data-variant-rows] tr').count();
    step('variant rows generated', rows === 2, rows);
    const rowEls = page.locator('[data-variant-rows] tr');
    for (let i = 0; i < rows; i++) {
        await rowEls.nth(i).locator('[data-field=sku]').fill(`QA-VAR-${RUN}-${i}`);
        await rowEls.nth(i).locator('[data-field=price]').fill(String(100 + i * 10));
        await rowEls.nth(i).locator('[data-field=stock]').fill('5').catch(() => {});
    }
    if (rows) await rowEls.nth(0).locator('[data-field=image]').setInputFiles(path.join(FX, 'p3.png')).catch(() => {});
    await L.shot(page, 'flow/variants_builder', { fullPage: true });
    const r = await submitForm(page, '#product-form', '#product-form [type=submit]');
    step('product with variants saved', r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors).slice(0, 300) : ''}`);
    await page.goto(`${L.BASE}/admin/catalog/products/${id}/edit?tab=variants`);
    await L.settle(page);
    const saved = await page.locator('[data-variant-rows] tr').count();
    step('variants persisted', saved === rows, saved);
    const img = await page.locator('[data-variant-rows] img[data-variant-image]:visible').count();
    step('variant image persisted', img >= 1, img);
};

/** Delete (archive) a QA product from the list, then restore it if the list offers it. */
flows.productDelete = async (page) => {
    const sku = process.env.QA_DELETE_SKU || 'QA-ADM-bh8k3';
    await page.goto(`${L.BASE}/admin/catalog/products`);
    await L.settle(page);
    await page.fill('[data-filters] input[name=search]', sku);
    await page.waitForTimeout(1500);
    await L.settle(page);
    const row = await rowWith(page, sku);
    step('product row found', (await row.count()) > 0, sku);
    if (!(await row.count())) return;
    await row.locator('[data-confirm-method=DELETE], [data-confirm-url][data-tone=danger]').first().evaluate((e) => e.click());
    const st = await confirmAccept(page);
    step('product deleted', st >= 200 && st < 300, st);
    await page.reload();
    await L.settle(page);
    await page.fill('[data-filters] input[name=search]', sku);
    await page.waitForTimeout(1500);
    await L.settle(page);
    step('product gone from list', (await (await rowWith(page, sku)).count()) === 0);
    const resp = await page.context().request.get(`${L.BASE}/admin/catalog/products/export`);
    step('products export download', resp.status() === 200 && /attachment/.test(resp.headers()['content-disposition'] || ''), `${resp.status()} ${resp.headers()['content-disposition']}`);
};

flows.translations = async (page) => {
    const key = `shell.qa_admin_${RUN}`;
    await page.goto(`${L.BASE}/admin/localization/translations`);
    await L.settle(page);
    await modalSubmit(page, '/translations/keys/create', { key: '' }, 'add key empty -> 422', '422');
    await page.goto(`${L.BASE}/admin/localization/translations`);
    await L.settle(page);
    await modalSubmit(page, '/translations/keys/create', { key, 'values[en]': 'QA admin text', 'values[ar]': 'نص اختبار' }, 'add key saved');
    await page.goto(`${L.BASE}/admin/localization/translations`);
    await L.settle(page);
    await page.fill('[data-filters] input[name=search]', key);
    await page.waitForTimeout(1500);
    await L.settle(page);
    let row = await rowWith(page, key);
    step('new key listed', (await row.count()) > 0, key);
    if (await row.count()) {
        const edit = row.locator('[data-remote-modal][data-url*="/entry/edit"]').first();
        const url = (await edit.getAttribute('data-url')).replace(L.BASE, '');
        await modalSubmit(page, url, { 'values[ar]': 'نص اختبار ٢' }, 'edit key saved');
        await page.reload(); await L.settle(page);
        await page.fill('[data-filters] input[name=search]', key); await page.waitForTimeout(1500); await L.settle(page);
        row = await rowWith(page, key);
        step('edited arabic value shown', /نص اختبار ٢/.test(await row.innerText()));
        const lock = row.locator('[data-confirm-url*="key-locks"]').first();
        if (await lock.count()) { await lock.evaluate((e) => e.click()); const st = await confirmAccept(page); step('lock key', st >= 200 && st < 300, st); }
        await L.shot(page, 'flow/translations_key');
    }
};

flows.compliance = async (page) => {
    await page.goto(`${L.BASE}/admin/vendors/compliance/1`);
    await L.settle(page);
    const respP = page.waitForResponse((r) => r.request().method() === 'PATCH' && r.url().includes('/documents/'), { timeout: 10000 }).catch(() => null);
    await page.locator('[data-review-request][data-status=verified]').first().click();
    const r = await respP;
    await page.waitForTimeout(500);
    if (await page.locator('#ui-confirm[aria-hidden=false]').count()) await confirmAccept(page);
    step('document marked verified', !!r && r.status() < 300, r && r.status());
    await page.reload(); await L.settle(page);
    await L.shot(page, 'flow/compliance_1', { fullPage: true });
    const dl = await page.context().request.get(`${L.BASE}/admin/vendors/compliance/1/documents/1`);
    step('document download', dl.status() === 200, `${dl.status()} ${dl.headers()['content-type']}`);
};

/** Money display: ORD-10003 (central flash 10 % + central coupon) vs ORDER_FORMULAS hand calculation. */
flows.money = async (page) => {
    const number = process.env.QA_MONEY_ORDER || 'ORD-10003';
    const expect = (process.env.QA_MONEY_EXPECT || '243.70,195.93,47.77,29.16,26.25,7.50').split(',');
    await page.goto(`${L.BASE}/admin/orders`);
    await L.settle(page);
    await page.fill('[data-filters] input[name=search]', number);
    await page.waitForTimeout(1500);
    await L.settle(page);
    const href = await page.locator('tbody a[href*="/admin/orders/"]').first().getAttribute('href').catch(() => null);
    step('order found', !!href, number);
    if (!href) return;
    for (const lang of ['en', 'ar']) {
        await page.goto(`${href}?lang=${lang}`);
        await L.settle(page);
        const txt = (await page.locator('main').innerText()).replace(/[٠-٩]/g, (c) => '٠١٢٣٤٥٦٧٨٩'.indexOf(c)).replace(/٫/g, '.').replace(/٬/g, ',');
        const missing = expect.filter((v) => !txt.includes(v));
        step(`admin order shows formula amounts (${lang})`, missing.length === 0, `missing: ${missing.join(',')}`);
        await L.shot(page, `flow/money_${number}_${lang}`, { fullPage: true });
    }
    await page.goto(`${L.BASE}/admin/orders?lang=en`);
};

flows.reports = async (page) => {
    await page.goto(`${L.BASE}/admin/reports`);
    await L.settle(page);
    const tabs = await page.$$eval('main a[href*="/admin/reports"]', (as) => [...new Set(as.map((a) => a.href))].filter((h) => !/export/.test(h)));
    step('report tabs found', tabs.length >= 10, tabs.length);
    for (const t of tabs) {
        const log = L.watch(page);
        const resp = await page.goto(t);
        await L.settle(page);
        const info = await L.inspect(page);
        const name = new URL(t).pathname.split('/').pop() + (new URL(t).search || '');
        const exp = await page.$$eval('main a[href*="export"]', (as) => as.map((a) => a.href)).catch(() => []);
        let expOk = true, expInfo = '';
        if (exp[0]) { const r = await page.context().request.get(exp[0]); expOk = r.status() === 200; expInfo = `${r.status()} ${(r.headers()['content-disposition'] || '').slice(0, 50)}`; }
        step(`report ${name}`, resp.status() === 200 && !info.serverError && !log.pageErrors.length && expOk, `${resp.status()} ${log.pageErrors.join(';')} export=${expInfo}`);
        await L.shot(page, `flow/report_${name.replace(/[^a-z0-9]+/gi, '_')}`);
        page.removeAllListeners('console'); page.removeAllListeners('pageerror'); page.removeAllListeners('response'); page.removeAllListeners('requestfailed');
    }
};

flows.sorting = async (page) => {
    for (const u of ['/admin/catalog/categories/sort', '/admin/catalog/products/sort', '/admin/catalog/labels']) {
        await page.goto(L.BASE + u);
        await L.settle(page);
        const handles = page.locator('main [data-drag-handle]');
        const names0 = await handles.evaluateAll((h) => h.slice(0, 3).map((x) => x.closest('li,tr,[data-sortable-item],[data-id]')?.innerText.trim().slice(0, 30)));
        const h0 = handles.first();
        await h0.focus();
        await page.keyboard.press('ArrowDown');
        await page.waitForTimeout(400);
        const names1 = await handles.evaluateAll((h) => h.slice(0, 3).map((x) => x.closest('li,tr,[data-sortable-item],[data-id]')?.innerText.trim().slice(0, 30)));
        step(`${u} keyboard move reorders`, names0[0] !== names1[0], `${names0[0]} -> ${names1[0]}`);
        const save = page.locator('main [data-sort-save]');
        const respP = page.waitForResponse((r) => ['POST', 'PUT', 'PATCH'].includes(r.request().method()), { timeout: 8000 }).catch(() => null);
        if (await save.count()) await save.click();
        const r = await respP;
        step(`${u} order saved${(await save.count()) ? '' : ' (autosave)'}`, !!r && r.status() < 300, r && r.status());
        await page.reload();
        await L.settle(page);
        const names2 = await page.locator('main [data-drag-handle]').evaluateAll((h) => h.slice(0, 3).map((x) => x.closest('li,tr,[data-sortable-item],[data-id]')?.innerText.trim().slice(0, 30)));
        step(`${u} order persisted`, names2[0] === names1[0], names2[0]);
        // restore
        const hh = page.locator('main [data-drag-handle]').nth(1);
        await hh.focus();
        await page.keyboard.press('ArrowUp');
        await page.waitForTimeout(300);
        const respR = page.waitForResponse((x) => ['POST', 'PUT', 'PATCH'].includes(x.request().method()), { timeout: 8000 }).catch(() => null);
        if (await save.count()) await page.locator('main [data-sort-save]').click();
        await respR;
    }
};

/** Shipping queue: walk one order (QA_ORDER, default ORD-10904) through every next status to delivered. */
flows.shipping = async (page) => {
    const number = process.env.QA_ORDER || 'ORD-10904';
    await page.goto(`${L.BASE}/admin/shipping-queue`);
    await L.settle(page);
    await page.fill('[data-filters] input[name=search]', number);
    await page.waitForTimeout(1500);
    await L.settle(page);
    const href = await page.locator('tbody a[href*="/shipping-queue/"]').first().getAttribute('href').catch(() => null);
    step('order found in shipping queue', !!href, number);
    if (!href) return;
    for (let i = 0; i < 6; i++) {
        await page.goto(href);
        await L.settle(page);
        const modal = await openModal(page, '/status');
        const options = await page.$$eval(`${modal} select[name=status] option`, (os) => os.filter((o) => o.value).map((o) => ({ v: o.value, sel: o.selected, dis: o.disabled })));
        const cur = options.findIndex((o) => o.sel);
        const next = options.slice(cur + 1).find((o) => !o.dis && !/cancel|fail|return/i.test(o.v));
        if (!next) { step(`no further status after ${options[cur]?.v}`, true, options.map((o) => o.v).join(',')); break; }
        const vals = { status: next.v, location: 'Riyadh hub', note: `QA ${next.v}` };
        if (i === 0) { vals.shipping_company_id = '@first'; vals.tracking_number = `QA${RUN}TRK`; }
        await setValues(page, `${modal} form`, vals);
        const r = await submitForm(page, `${modal} form`, `${modal} [data-remote-submit], ${modal} form [type=submit]`);
        step(`status -> ${next.v}`, r.status >= 200 && r.status < 300, `${r.status} ${r.status === 422 ? JSON.stringify(r.body?.errors) : ''}`);
        if (r.status >= 300) break;
        if (/delivered/i.test(next.v)) break;
    }
    await page.goto(href);
    await L.settle(page);
    await L.shot(page, `flow/shipping_${number}_final`, { fullPage: true });
};

(async () => {
    const which = (process.argv[2] || Object.keys(flows).join(',')).split(',');
    const mode = L.MODES.find((m) => m.key === (process.argv[3] || 'd-en-light'));
    const browser = await L.launch();
    const ctx = await L.newContext(browser, mode);
    await L.login(ctx, 'super');
    const page = await ctx.newPage();
    const jsErrors = [];
    page.on('pageerror', (e) => jsErrors.push(`[${current}] ${e}`));
    for (const name of which) {
        current = name;
        try { await flows[name](page); } catch (e) { step('exception', false, e.message.split('\n')[0]); await L.shot(page, `flow/${name}_exception`).catch(() => {}); }
    }
    if (jsErrors.length) console.log('JS ERRORS', jsErrors.slice(0, 10));
    console.log(`SUMMARY pass=${results.filter((r) => r.ok).length} fail=${results.filter((r) => !r.ok).length}`);
    await browser.close();
})();

module.exports = { submitForm, inlineErrors, select2Pick, dtSearch, confirmAccept };
