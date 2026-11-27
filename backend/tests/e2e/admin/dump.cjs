/* Dev helper: dump the form controls / buttons of an admin page. Usage: node dump.cjs /admin/path [modeKey] */
const L = require('./lib.cjs');

(async () => {
    const browser = await L.launch();
    const ctx = await L.newContext(browser, L.MODES.find((m) => m.key === (process.argv[3] || 'd-en-light')));
    await L.login(ctx, process.env.QA_WHO || 'super');
    const page = await ctx.newPage();
    for (const target of process.argv[2].split(',')) {
    const resp = await page.goto(L.BASE + target);
    await L.settle(page);
    console.log('=== ' + target + ' status', resp.status(), page.url());
    const d = await page.evaluate(() => ({
        forms: [...document.querySelectorAll('main form, .ui-overlay form')].map((f) => `${f.id}|${f.getAttribute('action')}|${f.method}|ajax=${f.hasAttribute('data-ajax')}`),
        controls: [...document.querySelectorAll('main input:not([type=hidden]),main select,main textarea')].filter((e) => !/\[(fr|hi|de|es|tr|ur|id|ru|zh|it|pt|fa|bn)\]/.test(e.name) && !e.id.startsWith('select2-')).map((e) => `${e.tagName.toLowerCase()}[${e.type}] name=${e.name} id=${e.id} req=${e.required} vis=${!!e.offsetParent}`),
        buttons: [...document.querySelectorAll('main button, main a.ui-btn, main [data-remote-modal], main [data-confirm]')].filter((b) => b.offsetParent && !b.closest('[data-tab-key]') && !b.matches('[data-tab-key]')).map((b) => `${b.tagName.toLowerCase()} "${(b.innerText || b.getAttribute('aria-label') || '').trim().slice(0, 30)}" ${[...b.attributes].filter((a) => a.name.startsWith('data-') || a.name === 'href' || a.name === 'type').map((a) => a.name + '=' + a.value.slice(0, 60)).join(' ')}`),
    }));
    console.log(d.forms.join('\n'));
    console.log(d.controls.join('\n'));
    console.log(d.buttons.join('\n'));
    }
    await browser.close();
})();
