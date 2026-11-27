// Print the data-* hooks and interactive controls of a page (exploration helper).
export default async ({ page, BASE, log, shot }) => {
    const url = process.env.QA_URL || '/';
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    const info = await page.evaluate(() => {
        const attrs = new Map();
        for (const el of document.querySelectorAll('main *, body > *')) for (const a of el.getAttributeNames()) if (a.startsWith('data-')) attrs.set(a, (attrs.get(a) || 0) + 1);
        const btns = [...document.querySelectorAll('main button, main a[role=button], main input[type=radio], main input[type=checkbox], main select')].filter((b) => b.offsetParent).map((b) => `${b.tagName}${b.name ? '[' + b.name + ']' : ''} "${(b.innerText || b.value || b.getAttribute('aria-label') || '').trim().slice(0, 30)}"`);
        return { family: document.querySelector('[data-theme-family]')?.dataset.themeFamily, attrs: [...attrs.entries()].map(([k, v]) => `${k}:${v}`).join(' '), btns: [...new Set(btns)].slice(0, 60) };
    });
    log(JSON.stringify(info, null, 1));
    await shot('inspect', true);
};
