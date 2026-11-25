/**
 * Tabs (x-ui::tabs, x-ui::locale-tabs).
 *   <div data-tabs data-tabs-param="tab">  (param '' = do not sync the URL)
 *     <div role="tablist"><button role="tab" aria-controls="p1" aria-selected="true">…</button></div>
 *     <div role="tabpanel" id="p1">…</div>
 * The active tab is kept in the URL (?tab=) with history.replaceState. ← → Home End
 * move between tabs (mirrored in RTL). On a 422 (`form:errors`) every tab whose panel
 * holds an invalid field gets a red dot and the first one is opened.
 */
const tabsOf = (container) => [...container.querySelectorAll(':scope [role="tablist"]')].filter((list) => list.closest('[data-tabs]') === container).flatMap((list) => [...list.querySelectorAll('[role="tab"]')]);

export function activateTab(tab, { focus = false, sync = true } = {}) {
    const container = tab.closest('[data-tabs]');

    tabsOf(container).forEach((other) => {
        const selected = other === tab;
        other.setAttribute('aria-selected', String(selected));
        other.tabIndex = selected ? 0 : -1;
        const panel = document.getElementById(other.getAttribute('aria-controls'));

        if (panel) {
            panel.hidden = !selected;
        }
    });

    if (focus) {
        tab.focus();
    }

    const param = container.dataset.tabsParam;

    if (sync && param && tab.dataset.tabKey) {
        const url = new URL(window.location.href);
        url.searchParams.set(param, tab.dataset.tabKey);
        window.history.replaceState(window.history.state, '', url);
    }

    container.dispatchEvent(new CustomEvent('tabs:change', { bubbles: true, detail: { key: tab.dataset.tabKey } }));
}

let bound = false;

export function initTabs(root = document) {
    root.querySelectorAll('[data-tabs]:not([data-tabs-ready])').forEach((container) => {
        container.dataset.tabsReady = '';
        const param = container.dataset.tabsParam;
        const wanted = param ? new URL(window.location.href).searchParams.get(param) : null;
        const tabs = tabsOf(container);
        const initial = tabs.find((tab) => wanted && tab.dataset.tabKey === wanted) ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0];

        if (initial) {
            activateTab(initial, { sync: false });
        }
    });

    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener('click', (event) => {
        const tab = event.target.closest?.('[data-tabs] [role="tab"]');

        if (tab && !tab.disabled) {
            event.preventDefault();
            activateTab(tab);
        }
    });

    document.addEventListener('keydown', (event) => {
        const tab = event.target.closest?.('[data-tabs] [role="tab"]');

        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            return;
        }

        event.preventDefault();
        const tabs = tabsOf(tab.closest('[data-tabs]')).filter((item) => !item.disabled);
        const index = tabs.indexOf(tab);
        const rtl = document.documentElement.dir === 'rtl';
        const step = (event.key === 'ArrowRight') !== rtl ? 1 : -1;
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + step + tabs.length) % tabs.length;
        activateTab(tabs[next], { focus: true });
    });

    document.addEventListener('form:errors', (event) => {
        let opened = false;

        event.target.querySelectorAll('[data-tabs]').forEach((container) => {
            tabsOf(container).forEach((tab) => {
                const panel = document.getElementById(tab.getAttribute('aria-controls'));
                const invalid = panel?.querySelector('[aria-invalid="true"]');

                if (invalid) {
                    tab.dataset.error = '';

                    if (!opened) {
                        activateTab(tab, { sync: false });
                        opened = true;
                    }
                } else {
                    delete tab.dataset.error;
                }
            });
        });
    });
}
