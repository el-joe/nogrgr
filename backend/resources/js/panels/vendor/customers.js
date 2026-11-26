/**
 * Vendor customers / newsletter pages (VEN-025…026): remote-modal forms get their
 * intl-tel-input phones once loaded, and the address modal (VEN-025c) reloads the
 * city options (Select2, remote) whenever the country changes.
 */
import { initPhoneInputs } from '../../core/phone';
import { $ } from '../../core/select2-lib.js';
import { bindRemoteModals } from '../remote-modal.js';
import { initSelect2 } from '../select2';

function wirePhones() {
    document.querySelectorAll('[data-remote-body]').forEach((body) => {
        new MutationObserver(() => initPhoneInputs(body)).observe(body, { childList: true });
    });
}

function citiesUrl(city, countryId) {
    const url = new URL(city.dataset.citiesUrl, window.location.href);
    url.searchParams.set('country_id', countryId);

    return url.href;
}

function onCountryChange(event) {
    const country = event.target;

    if (!(country instanceof HTMLSelectElement) || !country.matches('[data-country-select]')) {
        return;
    }

    const city = country.form?.querySelector('select[data-city-select]');

    if (!city) {
        return;
    }

    const $city = $(city);

    if ($city.hasClass('select2-hidden-accessible')) {
        $city.select2('destroy');
    }

    city.querySelectorAll('option:not([value=""])').forEach((option) => option.remove());
    city.value = '';

    if (country.value) {
        city.dataset.ajaxUrl = citiesUrl(city, country.value);
    } else {
        delete city.dataset.ajaxUrl;
    }

    initSelect2(city.parentElement);
}

export default function init(root) {
    bindRemoteModals(root ?? document);
    wirePhones();
    document.addEventListener('change', onCountryChange);
}
