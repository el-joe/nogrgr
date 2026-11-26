/**
 * Header search autocomplete (Ecommet 1:1163): recent searches (localStorage, max 8) as
 * chips on focus with a clear button; while typing (debounced, aborted on change)
 * product + category suggestions from storefront.search.autocomplete; ↑ ↓ Enter Esc.
 */
import { context } from './context';

const KEY = 'sf:recent-searches';
const MAX = 8;

const read = () => {
    try {
        const list = JSON.parse(localStorage.getItem(KEY) || '[]');
        return Array.isArray(list) ? list.filter((item) => typeof item === 'string').slice(0, MAX) : [];
    } catch {
        return [];
    }
};

const write = (list) => {
    try {
        localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX)));
    } catch {
        /* storage unavailable: recent searches are a convenience only */
    }
};

export function remember(term) {
    const value = term.trim();

    if (value.length < 2) {
        return;
    }

    write([value, ...read().filter((item) => item.toLowerCase() !== value.toLowerCase())]);
}

const el = (tag, className, text) => {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
};

function bind(form) {
    const input = form.querySelector('[data-search-input]');
    const panel = form.querySelector('[data-search-panel]');

    if (!input || !panel) {
        return;
    }

    const recent = panel.querySelector('[data-search-recent]');
    const recentList = panel.querySelector('[data-search-recent-list]');
    const results = panel.querySelector('[data-search-results]');
    const empty = panel.querySelector('[data-search-empty]');
    const loading = panel.querySelector('[data-search-loading]');
    const texts = form.querySelector('[data-search-templates]')?.dataset ?? {};
    const url = context().routes.autocomplete;
    let controller = null;
    let timer = null;
    let active = -1;

    const options = () => [...panel.querySelectorAll('[data-option]')];
    const open = (state) => {
        panel.toggleAttribute('data-open', state);
        input.setAttribute('aria-expanded', String(state));
        active = -1;
    };
    const show = (part) => {
        [recent, results, empty, loading].forEach((node) => node && (node.hidden = node !== part));
    };

    const renderRecent = () => {
        const list = read();
        recentList.replaceChildren(...list.map((term) => {
            const link = el('a', 'eco-chip-suggest', term);
            link.href = `${form.action}?q=${encodeURIComponent(term)}`;
            link.dataset.option = '';
            link.setAttribute('role', 'option');
            link.prepend(el('span', 'grid size-6 place-items-center rounded-full bg-white text-xs text-eco-gray', '↺'));
            return link;
        }));
        show(list.length ? recent : null);
        open(list.length > 0);
    };

    const renderResults = (data) => {
        const nodes = [];

        if (data.products?.length) {
            nodes.push(el('p', 'mb-1 text-xs font-medium uppercase tracking-wide text-eco-gray', texts.textProducts));
            data.products.forEach((product) => {
                const link = el('a', 'flex items-center gap-3 rounded-lg p-2 hover:bg-[#f6f6f6] aria-selected:bg-[#f6f6f6]');
                link.href = product.url;
                link.dataset.option = '';
                link.setAttribute('role', 'option');
                const image = el('span', 'grid size-10 shrink-0 place-items-center overflow-hidden rounded bg-eco-img');

                if (product.image) {
                    const img = el('img', 'size-full object-contain mix-blend-multiply');
                    img.src = product.image;
                    img.alt = '';
                    img.loading = 'lazy';
                    image.append(img);
                }

                const name = el('span', 'min-w-0 flex-1 truncate text-sm text-eco-black', product.name);
                link.append(image, name, el('span', 'shrink-0 text-sm font-medium', product.price));
                nodes.push(link);
            });
        }

        if (data.categories?.length) {
            nodes.push(el('p', 'mb-1 mt-3 text-xs font-medium uppercase tracking-wide text-eco-gray', texts.textCategories));
            const wrap = el('div', 'flex flex-wrap gap-2');
            data.categories.forEach((category) => {
                const link = el('a', 'eco-chip-suggest px-3', category.name);
                link.href = category.url;
                link.dataset.option = '';
                link.setAttribute('role', 'option');
                wrap.append(link);
            });
            nodes.push(wrap);
        }

        if (!nodes.length) {
            show(empty);
            return;
        }

        const all = el('a', 'mt-3 block text-center text-sm font-medium text-eco-black underline', texts.textAll);
        all.href = data.search_url;
        all.dataset.option = '';
        nodes.push(all);
        results.replaceChildren(...nodes);
        show(results);
    };

    const search = (term) => {
        controller?.abort();

        if (!url || term.length < 2) {
            renderRecent();
            return;
        }

        controller = new AbortController();
        show(loading);
        open(true);
        fetch(`${url}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, signal: controller.signal, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((payload) => renderResults(payload.data ?? {}))
            .catch((error) => error?.name !== 'AbortError' && show(empty));
    };

    input.addEventListener('focus', () => (input.value.trim().length >= 2 ? search(input.value.trim()) : renderRecent()));
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => search(input.value.trim()), 180);
    });
    input.addEventListener('keydown', (event) => {
        const list = options();

        if (event.key === 'Escape') {
            open(false);
            return;
        }

        if (!['ArrowDown', 'ArrowUp'].includes(event.key) || !list.length) {
            return;
        }

        event.preventDefault();
        active = (active + (event.key === 'ArrowDown' ? 1 : -1) + list.length) % list.length;
        list.forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
        list[active].scrollIntoView({ block: 'nearest' });
    });
    form.addEventListener('submit', (event) => {
        const list = options();

        if (active >= 0 && list[active]) {
            event.preventDefault();
            window.location.assign(list[active].href);
            return;
        }

        if (input.value.trim() === '') {
            event.preventDefault();
            input.focus();
            return;
        }

        remember(input.value);
    });
    panel.querySelector('[data-search-clear]')?.addEventListener('click', () => {
        write([]);
        renderRecent();
        input.focus();
    });
    panel.addEventListener('click', (event) => {
        const option = event.target.closest('[data-option]');

        if (option && results.contains(option) === false && recent.contains(option)) {
            remember(option.textContent.replace('↺', ''));
        }
    });
    document.addEventListener('click', (event) => !form.contains(event.target) && open(false));
    form.addEventListener('focusout', (event) => !form.contains(event.relatedTarget) && setTimeout(() => !form.contains(document.activeElement) && open(false), 120));
}

export function initSearch(root = document) {
    root.querySelectorAll('form[data-search]:not([data-search-ready])').forEach((form) => {
        form.dataset.searchReady = '';
        bind(form);
    });

    const term = new URLSearchParams(window.location.search).get('q');

    if (term && document.querySelector('[data-sf-page="listing-search"]')) {
        remember(term);
    }
}
