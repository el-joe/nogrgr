import { initNotificationBell } from '../notification-live.js';

/**
 * admin panel page modules, loaded lazily per [data-page] so each page ships only
 * what it needs.
 */
const pages = {
    'ui-kit': () => import('./ui-kit.js'),
    billing: () => import('./billing.js'),
    'admin-account': () => import('./account.js'),
    'admin-notifications': () => import('./notifications.js'),
    'admin-admins': () => import('./admins.js'),
    'admin-roles': () => import('./roles.js'),
    'admin-audit': () => import('./audit.js'),
    'admin-vendors': () => import('./vendors.js'),
    'admin-vendor-reviews': () => import('./vendor-reviews.js'),
    'admin-integrations': () => import('./integrations.js'),
    'admin-general-settings': () => import('./general-settings.js'),
    'admin-email-templates': () => import('./email-templates.js'),
    'admin-catalog': () => import('./catalog.js'),
    'admin-products': () => import('./products.js'),
    'admin-branches': () => import('./branches.js'),
    'admin-shipping': () => import('./shipping.js'),
    'admin-translations': () => import('./translations.js'),
    'admin-requests': () => import('./requests.js'),
    'admin-catalog-import': () => import('./catalog-import.js'),
};

export function initAdmin() {
    initNotificationBell();
    document.querySelectorAll('[data-page]').forEach((element) => {
        pages[element.dataset.page]?.().then((module) => module.default?.(element));
    });
}
