/**
 * Store-owner portal page modules, lazy-loaded per [data-page].
 */
const pages = {
    'owner-add-store': () => import('./add-store.js'),
    'owner-select-store': () => import('./select-store.js'),
    'owner-subscriptions': () => import('./subscriptions.js'),
    'owner-invoices': () => import('./invoices.js'),
    'owner-domains': () => import('./domains.js'),
    'owner-profile': () => import('./profile.js'),
    'owner-support': () => import('./support.js'),
};

export function initOwner() {
    const page = document.querySelector('[data-page]')?.dataset.page ?? '';
    pages[page]?.().then((module) => module.default?.(document));
}
