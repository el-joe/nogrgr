import { initNotificationBell } from '../notification-live.js';
import { initImpersonationBanner } from './impersonation.js';

/**
 * vendor panel page modules, loaded lazily per [data-page] so each page ships only
 * what it needs. Shell-wide behaviour (bell, impersonation banner) boots everywhere; the
 * AI Advertising Studio module boots wherever its modal or an ad preview is rendered.
 */
const pages = {
    'vendor-onboarding': () => import('./onboarding.js'),
    'vendor-dashboard': () => import('./dashboard.js'),
    'vendor-catalog': () => import('./catalog.js'),
    'vendor-requests': () => import('./requests.js'),
    'vendor-notifications': () => import('./notifications.js'),
    'vendor-setup-wizard': () => import('./setup-wizard.js'),
    'vendor-orders': () => import('./orders.js'),
    'vendor-subscription': () => import('./subscription.js'),
    'vendor-customers': () => import('./customers.js'),
    'vendor-banners': () => import('./banners.js'),
    'vendor-invoices': () => import('./invoices.js'),
    'vendor-compliance': () => import('./compliance.js'),
    'vendor-themes': () => import('./themes.js'),
    'vendor-page-builder': () => import('./page-builder.js'),
    'vendor-staff': () => import('./staff.js'),
    'vendor-store-pages': () => import('./store-pages.js'),
    'vendor-appearance': () => import('./appearance.js'),
    'vendor-email-templates': () => import('./email-templates.js'),
    'vendor-mail': () => import('./mail.js'),
    'vendor-support': () => import('./support.js'),
    'vendor-help': () => import('./help.js'),
    'vendor-account': () => import('./account.js'),
    'vendor-currencies': () => import('./currencies.js'),
    'vendor-domains': () => import('./domains.js'),
    'vendor-languages': () => import('./languages.js'),
    'vendor-ai-translation': () => import('./ai-translation.js'),
    'vendor-translations': () => import('./translations.js'),
    'vendor-ad-studio': () => import('./ad-studio.js'),
};

export function initVendor() {
    initNotificationBell();
    initImpersonationBanner(document);
    document.querySelectorAll('[data-page]').forEach((element) => {
        pages[element.dataset.page]?.().then((module) => module.default?.(element));
    });

    // AI Advertising Studio modal / ad previews (dashboard, product pages, studio page).
    if (document.querySelector('[data-ad-studio], [data-ad-preview], [data-ads-widget]')) {
        import('./ad-studio.js').then((module) => module.initAdStudio(document));
    }
}
