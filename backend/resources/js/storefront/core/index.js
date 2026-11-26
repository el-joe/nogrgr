/**
 * Shared storefront bootstrap (all 3 families).
 */
import Alpine from 'alpinejs';
import { boot } from '../../core/index.js';
import { initSliders } from './sliders';
import { initMotion } from './motion/index.js';

export { initSliders } from './sliders';
export { initMotion, openSheet, closeSheet } from './motion/index.js';

/** Pages with these hooks load the account / checkout-address bundle on demand. */
const ACCOUNT_HOOKS = '[data-phone-input], [data-sf-combobox], [data-address-book], [data-checkout-address], [data-password-toggle], [data-profile-card], form[data-ajax], [data-sf-account], [data-verify-email]';

function initLazyAccount() {
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-sheet-open]');
        const panel = opener ? document.getElementById(opener.dataset.sheetOpen) : null;

        if (panel?.matches('[data-lazy-account]') && !panel.dataset.accountReady) {
            panel.dataset.accountReady = '';
            import('./account/index.js').then(({ initAccount }) => initAccount(panel));
        }
    });
}

export function bootStorefront(callback) {
    window.Alpine = Alpine;
    Alpine.start();

    boot(() => {
        initMotion(document);
        initSliders(document);

        /* Hooks inside [data-lazy-account] (e.g. the header preferences panel) load the
           bundle only when that panel opens (initLazyAccount). */
        if ([...document.querySelectorAll(ACCOUNT_HOOKS)].some((element) => !element.closest('[data-lazy-account]'))) {
            import('./account/index.js').then(({ initAccount }) => initAccount(document));
        } else {
            initLazyAccount();
        }

        /* Store tracking pixels (VEN-083): only when the store has some on; consent first. */
        if (document.getElementById('sf-tracking')) {
            import('./tracking.js').then(({ initTracking }) => initTracking());
        }

        callback?.();
    });
}
