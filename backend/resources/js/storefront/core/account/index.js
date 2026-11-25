/**
 * Storefront account + checkout address step. Loaded on demand by the storefront core
 * when a page carries one of the hooks below, so the home pages do not pay for it.
 */
import { initPhones } from './phone';
import { initComboboxes } from './combobox';
import { initCities } from './city';
import { initMap } from './map';
import { initAccountForms } from './forms';
import { initAddressBooks } from './address-book';
import { initCheckoutSteps } from './checkout';

export function initAccount(root = document) {
    initAccountForms(root);
    // Checkout steps first: they mount their modal (template) before the fields are enhanced.
    initCheckoutSteps(root);
    initAddressBooks(root);
    initPhones(document);
    initComboboxes(document);
    initCities(document);
    document.querySelectorAll('form[data-address-form]').forEach(initMap);
}
