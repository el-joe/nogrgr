/**
 * Store-owner portal page modules, lazy-loaded per [data-page].
 */
const pages = {
    'owner-add-store': () => import('./add-store.js'),
};

export function initOwner() {
    const page = document.querySelector('[data-page]')?.dataset.page ?? '';
    pages[page]?.().then((module) => module.default?.(document));
}
