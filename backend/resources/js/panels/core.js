/**
 * Shared bootstrap for admin / vendor / affiliate panels.
 */
import Alpine from 'alpinejs';
import { boot, initReveal } from '../core/index.js';
import { initThemeToggle } from '../core/theme';
import { initSelect2 } from './select2';
import { initDataTables } from './datatable';
import { initUi } from './ui/index.js';

export { initSelect2 } from './select2';
export { initDataTable, initDataTables, reloadDataTable } from './datatable';
export { initUi, openOverlay, closeOverlay, confirmDialog, activateTab } from './ui/index.js';

/** Lazy heavy libraries: only loaded on pages that use them. */
export const loadChart = () => import('chart.js/auto').then((module) => module.default);
export const loadSortable = () => import('sortablejs').then((module) => module.default);

/**
 * Inline Stripe card (<x-ui::stripe-card>, decision #78 b) on any panel payment form:
 * the shared module is only fetched when such a form is on the page.
 */
export function initStripeCards(root = document) {
    if (root.querySelector('form [data-stripe-card]')) {
        import('../core/payments/stripe-card.js').then(({ bindStripeCardForms }) => bindStripeCardForms(root));
    }
}

export function bootPanel(callback) {
    window.Alpine = Alpine;
    Alpine.start();

    boot(() => {
        initThemeToggle(document);
        initUi(document);
        initSelect2(document);
        initDataTables(document);
        initReveal(document);
        initStripeCards(document);
        callback?.();
    });
}
