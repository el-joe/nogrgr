/**
 * Topbar global search (Ctrl/Cmd + K) — a command palette over the pages the admin
 * can open (the visible sidebar items, sent as a JSON island). Entity search
 * (orders, products, vendors) plugs into the same list when those modules ship.
 *   <div data-command>
 *     <input data-global-search role="combobox" aria-controls="cmd-list">
 *     <div class="ui-popover" data-command-results><ul id="cmd-list" role="listbox"></ul></div>
 *     <script type="application/json" data-command-items>[{"label":"Orders","group":"Sales","url":"…"}]</script>
 */
import { t } from '../../core/i18n';

const normalize = (value) => value.toLocaleLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '');

function init(root) {
    const input = root.querySelector('[data-global-search]');
    const popover = root.querySelector('[data-command-results]');
    const list = popover.querySelector('[role="listbox"]');
    let items = [];
    let active = 0;
    let matches = [];

    try {
        items = JSON.parse(root.querySelector('script[data-command-items]')?.textContent || '[]');
    } catch {
        items = [];
    }

    const render = () => {
        const query = normalize(input.value.trim());
        matches = (query ? items.filter((item) => normalize(`${item.label} ${item.group ?? ''}`).includes(query)) : items).slice(0, 8);
        active = Math.min(active, Math.max(0, matches.length - 1));
        list.innerHTML = '';

        if (!matches.length) {
            const empty = document.createElement('li');
            empty.className = 'px-2.5 py-2 text-[13px] text-ink-secondary';
            empty.textContent = t('search.no_results');
            list.appendChild(empty);
        }

        matches.forEach((item, index) => {
            const option = document.createElement('li');
            option.id = `${list.id}-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', String(index === active));
            option.className = `ui-menu-item cursor-pointer justify-between ${index === active ? 'bg-subtle' : ''}`;
            option.dataset.url = item.url;
            const label = document.createElement('span');
            label.textContent = item.label;
            const group = document.createElement('span');
            group.className = 'text-xs text-ink-muted';
            group.textContent = item.group ?? '';
            option.append(label, group);
            list.appendChild(option);
        });

        input.setAttribute('aria-activedescendant', matches.length ? `${list.id}-${active}` : '');
    };

    const open = () => {
        popover.dataset.open = '';
        input.setAttribute('aria-expanded', 'true');
        render();
    };

    const close = () => {
        delete popover.dataset.open;
        input.setAttribute('aria-expanded', 'false');
    };

    input.addEventListener('focus', open);
    input.addEventListener('input', () => {
        active = 0;
        open();
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % Math.max(1, matches.length);
            render();
        } else if (event.key === 'Enter' && matches[active]) {
            event.preventDefault();
            window.location.assign(matches[active].url);
        } else if (event.key === 'Escape') {
            close();
            input.blur();
        }
    });
    list.addEventListener('mousedown', (event) => {
        const option = event.target.closest('[data-url]');

        if (option) {
            event.preventDefault();
            window.location.assign(option.dataset.url);
        }
    });
    input.addEventListener('blur', () => setTimeout(close, 120));
}

let bound = false;

export function initCommand(root = document) {
    if (!bound) {
        bound = true;
        document.addEventListener('click', (event) => {
            const toggle = event.target.closest?.('[data-command-toggle]');

            if (!toggle) {
                return;
            }

            const target = document.getElementById(toggle.dataset.commandToggle);

            if (target) {
                target.toggleAttribute('data-open');
                toggle.setAttribute('aria-expanded', String(target.hasAttribute('data-open')));

                if (target.hasAttribute('data-open')) {
                    target.querySelector('[data-global-search]')?.focus();
                }
            }
        });
    }

    root.querySelectorAll('[data-command]:not([data-command-ready])').forEach((element) => {
        element.dataset.commandReady = '';
        init(element);
    });
}
