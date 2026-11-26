/**
 * Vendor › Settings › Compliance center (VEN-084), loaded for [data-page="vendor-compliance"]:
 *
 *   - stepper: [data-step-target] (stepper dots, Status card rows, Back / Next) shows the
 *     matching [data-step-panel]; the step is kept in ?step= and the hidden step input
 *   - a 422 opens the step of the first invalid field
 *   - "Submit for review" posts the same form to its data-action-url ("Save draft" posts
 *     to the form action)
 *   - account type: company-only fields / documents hidden for individuals
 *   - country → city: the city Select2 reloads its options from the cities-by-country URL
 *   - unsaved-changes bar (form[data-unsaved-form]) + leave-page guard while dirty
 */
import { $ } from '../vendor-libs';
import { initSelect2 } from '../select2.js';
import { bindUnsavedForms } from '../admin/unsaved-form.js';

function showStep(root, key, { focus = false } = {}) {
    const panel = root.querySelector(`[data-step-panel="${CSS.escape(key)}"]`);

    if (!panel) {
        return;
    }

    root.querySelectorAll('[data-step-panel]').forEach((element) => {
        element.hidden = element !== panel;
    });
    root.querySelectorAll('nav [data-step-target]').forEach((button) => {
        if (button.dataset.stepTarget === key) {
            button.setAttribute('aria-current', 'step');
        } else {
            button.removeAttribute('aria-current');
        }
    });

    root.dataset.step = key;
    const input = root.querySelector('[data-step-input]');

    if (input) {
        input.value = key;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('step', key);
    window.history.replaceState(window.history.state, '', url);

    if (focus) {
        panel.querySelector('h2')?.closest('section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function bindSteps(root) {
    root.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-step-target]');

        if (trigger && root.contains(trigger)) {
            event.preventDefault();
            showStep(root, trigger.dataset.stepTarget, { focus: !trigger.closest('nav') });
        }
    });

    root.querySelector('form')?.addEventListener('form:errors', (event) => {
        const form = event.currentTarget;
        const keys = event.detail?.keys ?? [];
        const target = keys
            .map((key) => form.querySelector(`[data-error-for="${CSS.escape(key)}"]`))
            .find((element) => element !== null);
        const panel = (event.detail?.first ?? target)?.closest('[data-step-panel]');

        if (panel) {
            showStep(root, panel.dataset.stepPanel);
        }
    });
}

function bindSubmitAction(form) {
    const defaultAction = form.getAttribute('action');

    // Runs before the document-level data-ajax handler (target phase first).
    form.addEventListener('submit', (event) => {
        form.setAttribute('action', event.submitter?.dataset.actionUrl || defaultAction);
    });
}

function bindAccountType(root) {
    const sync = () => {
        const checked = root.querySelector('input[name="account_type"]:checked');
        const company = !checked || checked.value === 'company';

        root.querySelectorAll('[data-company-only]').forEach((element) => {
            element.hidden = !company;
        });
        root.querySelectorAll('[data-individual-only]').forEach((element) => {
            element.hidden = company;
        });
    };

    root.querySelectorAll('input[name="account_type"]').forEach((input) => input.addEventListener('change', sync));
}

function bindCities(root) {
    const country = root.querySelector('select[data-country-select]');
    const city = root.querySelector('select[data-city-select]');
    const template = root.dataset.citiesUrl;

    if (!country || !city || !template) {
        return;
    }

    $(country).on('change', () => {
        const value = country.value;
        const $city = $(city);

        if ($city.hasClass('select2-hidden-accessible')) {
            $city.select2('destroy');
        }

        city.querySelectorAll('option').forEach((option) => {
            if (option.value !== '') {
                option.remove();
            }
        });
        city.value = '';

        if (value) {
            city.dataset.ajaxUrl = template.replace('__country__', encodeURIComponent(value));
            city.dataset.placeholder = city.dataset.placeholderReady || city.dataset.placeholder || '';
            city.disabled = country.disabled;
        } else {
            delete city.dataset.ajaxUrl;
            city.disabled = true;
        }

        initSelect2(city.closest('[data-field]') ?? root);
        city.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function bindLeaveGuard(form) {
    let saving = false;

    form.addEventListener('submit', () => {
        saving = true;
    });
    form.addEventListener('ajax:error', () => {
        saving = false;
    });
    window.addEventListener('beforeunload', (event) => {
        if (!saving && form.dataset.dirty !== undefined) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}

export default function initCompliance(page) {
    const root = page.querySelector('[data-compliance]') ?? page;
    const form = root.querySelector('#compliance-form');

    bindSteps(root);
    bindAccountType(root);
    bindCities(root);

    if (form) {
        bindSubmitAction(form);
        bindUnsavedForms(root);
        bindLeaveGuard(form);
    }
}
