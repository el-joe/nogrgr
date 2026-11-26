/**
 * Listing helpers: "See more" appends the next page in place (JSON fragment; the link
 * stays a real ?page=n+1 for crawlers / no-JS), sort / category selects apply at once,
 * home "explore your interests" chips swap the grid (home.tab fragment) with skeletons.
 */
import { http } from '../../core/http';
import { initMotion } from '../core/motion/index.js';
import { applyDataVars } from './ui';

const fragmentUrl = (href) => {
    const url = new URL(href, window.location.href);

    if (!url.pathname.includes('/home/tab/')) {
        url.searchParams.set('fragment', '1');
    }

    return url.toString();
};

function skeletons(grid, count = 5) {
    const nodes = Array.from({ length: count }, () => {
        const card = document.createElement('div');
        card.className = 'grid gap-2';
        card.dataset.skeleton = '';
        card.setAttribute('aria-hidden', 'true');
        card.innerHTML = '<span class="eco-skeleton aspect-[240/136]"></span><span class="eco-skeleton h-3 w-3/4"></span><span class="eco-skeleton h-4 w-1/2"></span>';
        return card;
    });
    grid.append(...nodes);

    return () => nodes.forEach((node) => node.remove());
}

async function loadInto(grid, href, { replace = false, button = null } = {}) {
    const clear = skeletons(grid, replace ? 10 : 5);

    if (replace) {
        [...grid.children].filter((child) => !child.hasAttribute('data-skeleton')).forEach((child) => child.remove());
    }

    grid.setAttribute('aria-busy', 'true');

    try {
        const payload = await http.get(fragmentUrl(href), null, { toast: false });
        clear();
        const template = document.createElement('template');
        template.innerHTML = payload.html ?? '';
        const added = [...template.content.children];
        grid.append(...added);
        initMotion(grid);
        applyDataVars(grid);
        added[0]?.querySelector('a, button')?.focus({ preventScroll: true });

        return payload.next_url ?? null;
    } catch (error) {
        clear();
        throw error;
    } finally {
        grid.removeAttribute('aria-busy');
    }
}

function bindLoadMore(link) {
    link.addEventListener('click', async (event) => {
        const grid = document.querySelector(link.dataset.target);

        if (!grid || link.dataset.busy) {
            return;
        }

        event.preventDefault();
        link.dataset.busy = '1';
        const label = link.querySelector('[data-load-more-label]');
        const text = label?.textContent;

        if (label) {
            label.textContent = link.dataset.loadingText;
        }

        try {
            const next = await loadInto(grid, link.href);

            if (next) {
                link.href = next;
            } else {
                link.closest('div')?.remove();
            }
        } catch {
            if (label) {
                label.textContent = link.dataset.errorText;
            }
        } finally {
            delete link.dataset.busy;

            if (label && label.textContent === link.dataset.loadingText) {
                label.textContent = text;
            }
        }
    });
}

function bindHomeTabs(section) {
    const grid = section.querySelector('#sf-interests-grid');
    const tabs = [...section.querySelectorAll('[role="tab"][data-tab-url]')];
    let more = section.querySelector('[data-load-more]');

    tabs.forEach((tab) => tab.addEventListener('click', async () => {
        if (tab.getAttribute('aria-selected') === 'true') {
            return;
        }

        tabs.forEach((other) => other.setAttribute('aria-selected', String(other === tab)));

        try {
            const next = await loadInto(grid, tab.dataset.tabUrl, { replace: true });
            more = section.querySelector('[data-load-more]');

            if (more) {
                more.closest('div').hidden = !next;

                if (next) {
                    more.href = next;
                }
            }
        } catch {
            /* http.js already showed the error toast */
        }
    }));
}

export function initListing(root = document) {
    root.querySelectorAll('[data-load-more]:not([data-ready])').forEach((link) => {
        link.dataset.ready = '';
        bindLoadMore(link);
    });

    root.querySelectorAll('[data-home-tabs]:not([data-ready])').forEach((section) => {
        section.dataset.ready = '';
        bindHomeTabs(section);
    });

    root.querySelectorAll('select[data-auto-filter]:not([data-ready])').forEach((select) => {
        select.dataset.ready = '';
        select.addEventListener('change', () => {
            const url = new URL(window.location.href);
            const name = select.dataset.autoFilter;
            url.searchParams.delete(name);
            url.searchParams.delete('page');

            if (select.value !== '') {
                url.searchParams.set(name, select.value);
            }

            window.location.assign(url.toString());
        });
    });
}
