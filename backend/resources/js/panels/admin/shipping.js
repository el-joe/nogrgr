/**
 * Admin › Logistics › Shipping pages (ADM-031…035):
 *  - [data-fixed-calculator]  fixed-cost calculator (server computes via Commerce\Pricing);
 *  - [data-rule-form]         zones / bands repeater, zone city pickers follow the rule countries;
 *  - [data-estimator]         live delivery estimate (country + order date);
 *  - [data-stop-form]         weekday field only for weekly stop periods (also inside remote modals);
 *  - [data-auto-submit]       stop-period filter submits on change;
 *  - [data-popup-form]        delivery popup days: live bars, total, Save only at exactly 100 %;
 *  - unsaved-changes bars (unsaved-form.js).
 */
import { $ } from '../vendor-libs';
import { initSelect2 } from '../select2';
import { http } from '../../core/http';
import { bindUnsavedForms } from './unsaved-form.js';

const debounce = (fn, wait = 300) => {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
};

const valueOf = (form, name) => form.querySelector(`[name="${name}"]`)?.value ?? '';

function onSelectChange(select, handler) {
    select.addEventListener('change', handler);
    $(select).on('select2:select select2:clear select2:unselect', handler);
}

/* Fixed-cost calculator ------------------------------------------------- */
function initCalculator(form) {
    const text = form.querySelector('[data-calc-text]');
    const result = form.querySelector('[data-calc-result]');
    const error = form.querySelector('[data-calc-error]');
    let controller;

    const run = debounce(async () => {
        const params = { country_id: valueOf(form, 'country_id'), weight_grams: valueOf(form, 'weight_grams'), sale_price: valueOf(form, 'sale_price') };

        if (!params.country_id || params.weight_grams === '' || params.sale_price === '') {
            return;
        }

        controller?.abort();
        controller = new AbortController();
        result.setAttribute('aria-busy', 'true');

        try {
            const payload = await http.get(form.dataset.url, params, { silent: true, toast: false, signal: controller.signal });
            text.textContent = payload.text;
            result.classList.toggle('bg-success-bg', payload.configured);
            result.classList.toggle('text-success', payload.configured);
            result.hidden = false;
            error.hidden = true;
        } catch (exception) {
            if (exception?.name !== 'AbortError') {
                result.hidden = true;
                error.hidden = false;
            }
        } finally {
            result.removeAttribute('aria-busy');
        }
    });

    form.addEventListener('submit', (event) => event.preventDefault());
    form.querySelectorAll('input').forEach((input) => input.addEventListener('input', run));
    form.querySelectorAll('select').forEach((select) => onSelectChange(select, run));
}

/* Delivery estimator ---------------------------------------------------- */
function initEstimator(form) {
    const text = form.querySelector('[data-estimate-text]');
    const skips = form.querySelector('[data-estimate-skips]');
    const result = form.querySelector('[data-estimate-result]');
    const error = form.querySelector('[data-estimate-error]');

    const run = debounce(async () => {
        const params = { country_id: valueOf(form, 'country_id'), date: valueOf(form, 'date') };

        if (form.dataset.company) {
            params.company_id = form.dataset.company;
        }

        if (!params.country_id || !params.date) {
            return;
        }

        result.setAttribute('aria-busy', 'true');

        try {
            const payload = await http.get(form.dataset.url, params, { silent: true, toast: false });
            text.textContent = payload.text;
            skips.textContent = payload.skipped_text ?? '';
            result.classList.add('bg-success-bg', 'text-success');
            result.hidden = false;
            error.hidden = true;
        } catch {
            result.hidden = true;
            error.hidden = false;
        } finally {
            result.removeAttribute('aria-busy');
        }
    }, 150);

    form.addEventListener('submit', (event) => event.preventDefault());
    form.addEventListener('change', run);
    form.querySelectorAll('select').forEach((select) => onSelectChange(select, run));
    run();
}

/* Rule form: zones and bands -------------------------------------------- */
function initRuleForm(form) {
    const zones = form.querySelector('[data-zones]');
    const zoneTemplate = form.querySelector('template[data-zone-template]');
    const bandTemplate = form.querySelector('template[data-band-template]');
    const countries = form.querySelector('[data-rule-countries]');
    let counter = Date.now();

    const selectedCountries = () => $(countries).val() ?? [];

    const pointCities = (select) => {
        const ids = selectedCountries();

        if ($(select).hasClass('select2-hidden-accessible')) {
            $(select).select2('destroy');
        }

        if (ids.length) {
            select.dataset.ajaxUrl = `${form.dataset.citiesUrl}?country=${encodeURIComponent(ids.join(','))}`;
            select.disabled = false;
        } else {
            delete select.dataset.ajaxUrl;
            select.disabled = true;
        }

        initSelect2(select.parentElement);
    };

    const refreshEmpty = () => {
        form.querySelector('[data-zones-empty]').hidden = zones.querySelector('[data-zone]') !== null;
        zones.querySelectorAll('[data-zone]').forEach((zone) => {
            zone.querySelector('[data-bands-empty]').hidden = zone.querySelector('[data-band]') !== null;
        });
    };

    const zoneIndex = (zone) => zone.querySelector('[name^="zones["]')?.name.match(/^zones\[([^\]]+)\]/)?.[1];

    const markDirty = () => form.dispatchEvent(new Event('change', { bubbles: true }));

    form.querySelectorAll('[data-zone-cities]').forEach(pointCities);
    onSelectChange(countries, () => form.querySelectorAll('[data-zone-cities]').forEach(pointCities));

    form.addEventListener('click', (event) => {
        const addZone = event.target.closest('[data-zone-add]');
        const removeZone = event.target.closest('[data-zone-remove]');
        const addBand = event.target.closest('[data-band-add]');
        const removeBand = event.target.closest('[data-band-remove]');

        if (addZone) {
            counter += 1;
            const html = zoneTemplate.innerHTML.replaceAll('__Z__', `n${counter}`);
            zones.insertAdjacentHTML('beforeend', html);
            const zone = zones.lastElementChild;
            initSelect2(zone);
            zone.querySelectorAll('[data-zone-cities]').forEach(pointCities);
            zone.querySelector('input[type="text"]')?.focus();
        } else if (removeZone) {
            removeZone.closest('[data-zone]')?.remove();
        } else if (addBand) {
            const zone = addBand.closest('[data-zone]');
            counter += 1;
            const html = bandTemplate.innerHTML.replaceAll('__Z__', zoneIndex(zone)).replaceAll('__B__', `n${counter}`);
            zone.querySelector('[data-bands]').insertAdjacentHTML('beforeend', html);
            zone.querySelector('[data-bands] [data-band]:last-child input')?.focus();
        } else if (removeBand) {
            removeBand.closest('[data-band]')?.remove();
        } else {
            return;
        }

        refreshEmpty();
        markDirty();
    });

    refreshEmpty();
}

/* Stop-period form: weekday only for weekly ----------------------------- */
function bindStopForms(root) {
    const sync = (form) => {
        const weekly = form.querySelector('[data-stop-recurrence] input:checked, input[name="recurrence"]:checked')?.value === 'weekly';
        const weekday = form.querySelector('[data-stop-weekday]');

        if (weekday) {
            weekday.hidden = !weekly;
        }
    };

    root.addEventListener('change', (event) => {
        const form = event.target.closest?.('[data-stop-form]');

        if (form && event.target.name === 'recurrence') {
            sync(form);
        }
    });
}

/* Delivery popup days --------------------------------------------------- */
function initPopup(form) {
    const inputs = [...form.querySelectorAll('[data-popup-input]')];
    const save = document.querySelectorAll('[data-popup-save]');
    const total = form.querySelector('[data-popup-total]');
    const totalBar = form.querySelector('[data-popup-total-bar]');
    const totalRow = form.querySelector('[data-popup-total-row]');
    const ok = form.querySelector('[data-total-ok]');
    const bad = form.querySelector('[data-total-bad]');
    const badText = form.querySelector('[data-total-bad-text]');
    const country = form.querySelector('[data-popup-country]');

    const clean = (input) => {
        const value = Number.parseInt(input.value, 10);
        const valid = Number.isInteger(value) && value >= 0 && value <= 100 && String(value) === input.value.trim();
        input.closest('.ui-control')?.setAttribute('aria-invalid', valid ? 'false' : 'true');

        return valid ? value : null;
    };

    const update = () => {
        const values = inputs.map(clean);
        const valid = values.every((value) => value !== null);
        const sum = values.reduce((carry, value) => carry + (value ?? 0), 0);
        const max = Math.max(1, ...values.map((value) => value ?? 0));

        inputs.forEach((input, index) => {
            const value = values[index] ?? 0;
            const row = input.closest('[data-popup-row]');
            row.querySelector('[data-popup-bar]').style.width = `${(value / max) * 100}%`;
            row.querySelector('[data-state-shown]').hidden = value === 0;
            row.querySelector('[data-state-hidden]').hidden = value !== 0;

            const preview = document.querySelector(`[data-preview-row][data-day="${row.dataset.day}"]`);

            if (preview) {
                preview.hidden = value === 0;
                preview.querySelector('[data-preview-bar]').style.width = `${(value / max) * 100}%`;
                preview.querySelector('[data-preview-value]').textContent = `${value}%`;
            }
        });

        const ready = valid && sum === 100;
        total.textContent = `${sum}%`;
        totalBar.style.width = `${Math.min(sum, 100)}%`;
        totalBar.classList.toggle('bg-success', ready);
        totalBar.classList.toggle('bg-danger', !ready);
        totalRow.classList.toggle('bg-success-bg', ready);
        totalRow.classList.toggle('bg-danger-bg', !ready);
        ok.hidden = !ready;
        bad.hidden = ready;

        if (!ready) {
            const template = sum < 100 ? badText.dataset.templateAdd : badText.dataset.templateRemove;
            badText.textContent = template.replace(':value', String(Math.abs(100 - sum)));
        }

        save.forEach((button) => {
            button.disabled = !ready;
        });
    };

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-popup-input]')) {
            update();
        }
    });

    form.querySelector('[data-popup-even]')?.addEventListener('click', () => {
        const base = Math.floor(100 / inputs.length);
        let remainder = 100 - base * inputs.length;
        inputs.forEach((input) => {
            input.value = String(base + (remainder > 0 ? 1 : 0));
            remainder -= 1;
        });
        update();
        form.dispatchEvent(new Event('change', { bubbles: true }));
    });

    const select = country?.querySelector('select');

    if (select) {
        onSelectChange(select, () => {
            const url = new URL(country.dataset.url, window.location.origin);

            if (select.value) {
                url.searchParams.set('country', select.value);
            }

            delete form.dataset.dirty;
            window.location.assign(url.toString());
        });
    }

    update();
}

export default function initShipping(page) {
    bindUnsavedForms(page);
    bindStopForms(document);

    page.querySelectorAll('[data-fixed-calculator]').forEach(initCalculator);
    page.querySelectorAll('[data-estimator]').forEach(initEstimator);
    page.querySelectorAll('[data-rule-form]').forEach(initRuleForm);
    page.querySelectorAll('[data-popup-form]').forEach(initPopup);
    page.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.querySelectorAll('select').forEach((select) => onSelectChange(select, () => form.requestSubmit()));
    });
}
