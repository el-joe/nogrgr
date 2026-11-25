/**
 * admin panel page modules, loaded lazily per [data-page] so each page ships only
 * what it needs.
 */
const pages = {
    'ui-kit': () => import('./ui-kit.js'),
};

export function initAdmin() {
    document.querySelectorAll('[data-page]').forEach((element) => {
        pages[element.dataset.page]?.().then((module) => module.default?.(element));
    });
}
