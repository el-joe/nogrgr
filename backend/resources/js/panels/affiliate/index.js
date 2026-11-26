/**
 * Affiliate panel page modules, lazy-loaded per [data-page].
 */
const pages = {
    'affiliate-notifications': () => import('../admin/notifications.js'),
    'affiliate-profile': () => import('./profile.js'),
};

export function initAffiliate() {
    const page = document.querySelector('[data-page]')?.dataset.page ?? '';
    pages[page]?.().then((module) => module.default?.(document));
}
