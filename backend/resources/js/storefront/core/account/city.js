/**
 * City suggestions (hook: input[data-city-suggestions-url] with __COUNTRY__): debounced
 * GET /geo/countries/{id}/cities?q= for the country chosen in the same form; free text
 * stays allowed (the backend stores the city as typed). ↑ ↓ Enter Esc on the input.
 */
import { http } from '../../../core/http';

export function initCity(input) {
    if (input.__city) {
        return;
    }

    input.__city = true;
    const root = input.closest('[data-city-combobox]');
    const list = root?.querySelector('[data-city-list]');
    const form = input.form;
    const country = form?.querySelector('select[data-country-select]');

    if (!list || !country) {
        return;
    }

    let timer = null;
    let controller = null;
    let active = -1;

    const options = () => [...list.querySelectorAll('[role="option"]')];

    const close = () => {
        delete list.dataset.open;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const highlight = (index) => {
        const items = options();
        items.forEach((item) => delete item.dataset.active);
        active = items.length ? (index + items.length) % items.length : -1;
        const item = items[active];

        if (item) {
            item.dataset.active = '';
            item.scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', item.id);
        }
    };

    const render = (cities) => {
        list.replaceChildren(...cities.map((city, index) => {
            const item = document.createElement('li');
            item.id = `${list.id}-${index}`;
            item.className = 'sf-option cursor-pointer';
            item.setAttribute('role', 'option');
            item.textContent = city.name;

            return item;
        }));

        if (cities.length && document.activeElement === input) {
            const rect = input.getBoundingClientRect();
            list.dataset.placement = window.innerHeight - rect.bottom < 260 && rect.top > 260 ? 'top' : 'bottom';
            list.dataset.open = '';
            input.setAttribute('aria-expanded', 'true');
        } else {
            close();
        }
    };

    const load = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            controller?.abort();

            if (!country.value) {
                render([]);

                return;
            }

            controller = new AbortController();
            const url = input.dataset.citySuggestionsUrl.replace('__COUNTRY__', encodeURIComponent(country.value));

            try {
                const { data } = await http.get(url, { q: input.value.trim() }, { signal: controller.signal, silent: true, toast: false });
                render(Array.isArray(data) ? data : []);
            } catch {
                render([]);
            }
        }, 220);
    };

    const choose = (item) => {
        input.value = item.textContent;
        input.removeAttribute('aria-invalid');
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    };

    input.addEventListener('input', load);
    input.addEventListener('focus', load);
    input.addEventListener('blur', () => setTimeout(close, 120));
    input.addEventListener('keydown', (event) => {
        if (list.dataset.open === undefined) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(active + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            choose(options()[active]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close();
        }
    });

    list.addEventListener('pointerdown', (event) => event.preventDefault());
    list.addEventListener('click', (event) => {
        const item = event.target.closest('[role="option"]');

        if (item) {
            choose(item);
        }
    });

    country.addEventListener('change', () => {
        list.replaceChildren();
        close();
    });
}

export function initCities(root = document) {
    root.querySelectorAll('input[data-city-suggestions-url]').forEach(initCity);
}
