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
const ACCOUNT_HOOKS = '[data-phone-input], [data-sf-combobox], [data-address-book], [data-checkout-address], [data-password-toggle], [data-profile-card], form[data-ajax]';

export function bootStorefront(callback) {
    window.Alpine = Alpine;
    Alpine.start();

    boot(() => {
        initMotion(document);
        initSliders(document);

        if (document.querySelector(ACCOUNT_HOOKS)) {
            import('./account/index.js').then(({ initAccount }) => initAccount(document));
        }

        callback?.();
    });
}
