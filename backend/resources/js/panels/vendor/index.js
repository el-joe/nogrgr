import { initNotificationBell } from '../notification-live.js';
import { initImpersonationBanner } from './impersonation.js';

/**
 * vendor panel page modules, loaded lazily per [data-page] so each page ships only
 * what it needs. Shell-wide behaviour (bell, impersonation banner) boots everywhere.
 */
const pages = {
    'vendor-onboarding': () => import('./onboarding.js'),
    'vendor-catalog': () => import('./catalog.js'),
    'vendor-requests': () => import('./requests.js'),
    'vendor-notifications': () => import('./notifications.js'),
    'vendor-setup-wizard': () => import('./setup-wizard.js'),
    'vendor-orders': () => import('./orders.js'),
    'vendor-subscription': () => import('./subscription.js'),
};

export function initVendor() {
    initNotificationBell();
    initImpersonationBanner(document);
    document.querySelectorAll('[data-page]').forEach((element) => {
        pages[element.dataset.page]?.().then((module) => module.default?.(element));
    });
}
