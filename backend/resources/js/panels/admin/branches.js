/**
 * Admin › Logistics › Branches form (ADM-030a):
 *  - unsaved-changes bar (unsaved-form.js);
 *  - [data-branch-country] change → the city and shipping-zone selects ([data-branch-city],
 *    [data-branch-zones]) are cleared and re-pointed at the cities / zones of that country
 *    (Select2 remote options from data-cities-url?country=<id>).
 */
import { $ } from '../vendor-libs';
import { initSelect2 } from '../select2';
import { bindUnsavedForms } from './unsaved-form.js';

function repoint(select, countryId) {
    const base = select.dataset.citiesUrl;

    if (!base) {
        return;
    }

    if ($(select).hasClass('select2-hidden-accessible')) {
        $(select).select2('destroy');
    }

    select.replaceChildren();

    if (!select.multiple) {
        select.appendChild(new Option(select.dataset.placeholder ?? '', ''));
    }

    if (countryId) {
        select.dataset.ajaxUrl = `${base}?country=${encodeURIComponent(countryId)}`;
        select.disabled = false;
    } else {
        delete select.dataset.ajaxUrl;
        select.disabled = true;
    }

    initSelect2(select.parentElement);
}

export default function initBranches(page) {
    bindUnsavedForms(page);

    const form = page.querySelector('[data-branch-form]');
    const country = form?.querySelector('[data-branch-country]');

    if (!country) {
        return;
    }

    const targets = [form.querySelector('[data-branch-city]'), form.querySelector('[data-branch-zones]')].filter(Boolean);

    if (!country.value) {
        targets.forEach((select) => repoint(select, ''));
    }

    country.addEventListener('change', () => {
        targets.forEach((select) => repoint(select, country.value));
    });
}
