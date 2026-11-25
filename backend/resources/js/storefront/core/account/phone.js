/**
 * Storefront phone fields (contract hooks: [data-phone-input] + data-phone-country-field).
 * intl-tel-input with a separate dial code, the saved country or the visitor country
 * (<html data-country>) pre-selected, country search, and a live validity tick / cross
 * on the wrapper ([data-valid="true|false"]).
 *
 * The request carries the number as E.164 and the ISO2 country (hidden phone_country):
 * a capture-phase submit listener rewrites the input just before the ajax handler
 * builds its FormData, and puts the typed value back on the next task so the field
 * never shows "+9665…" next to the separate dial code.
 */
import intlTelInput from 'intl-tel-input';

function countryInput(input) {
    const form = input.form ?? input.closest('form');
    const name = input.dataset.phoneCountryField || 'phone_country';

    return form?.querySelector(`input[type="hidden"][name="${CSS.escape(name)}"]`) ?? null;
}

function refresh(input) {
    const iti = input.__iti;
    const country = countryInput(input);
    const wrapper = input.closest('[data-phone-wrapper]');
    const data = iti.getSelectedCountryData();

    if (country && data?.iso2) {
        country.value = data.iso2.toUpperCase();
    }

    if (!wrapper) {
        return;
    }

    if (!input.value.trim()) {
        delete wrapper.dataset.valid;

        return;
    }

    wrapper.dataset.valid = String(Boolean(iti.isValidNumber?.()));
}

export function initPhone(input) {
    if (input.__iti) {
        return input.__iti;
    }

    const country = countryInput(input);
    const initial = (country?.value || document.documentElement.dataset.country || 'sa').toLowerCase();

    const iti = intlTelInput(input, {
        initialCountry: initial,
        separateDialCode: true,
        countrySearch: true,
        strictMode: true,
        formatAsYouType: true,
        useFullscreenPopup: false,
        loadUtils: () => import('intl-tel-input/utils'),
    });
    input.__iti = iti;

    input.addEventListener('input', () => refresh(input));
    input.addEventListener('countrychange', () => refresh(input));
    iti.promise?.then(() => refresh(input));

    return iti;
}

/**
 * Rewrites every phone input of the form to E.164 for the request being built now.
 */
export function syncPhones(form) {
    form.querySelectorAll('input[data-phone-input]').forEach((input) => {
        const iti = input.__iti;

        if (!iti) {
            return;
        }

        refresh(input);
        const typed = input.value;
        const number = typed.trim() ? iti.getNumber?.() : '';

        if (number && number !== typed) {
            input.value = number;
            setTimeout(() => {
                input.value = typed;
            });
        }
    });
}

export function setPhone(input, number, iso2) {
    const iti = initPhone(input);

    if (iso2) {
        iti.setCountry(iso2.toLowerCase());
    }

    iti.setNumber(number || '');
    refresh(input);
}

export function initPhones(root = document) {
    root.querySelectorAll('input[data-phone-input]').forEach(initPhone);

    if (!document.__sfPhoneSubmit) {
        document.__sfPhoneSubmit = true;
        // Capture phase: runs before the bubbling [data-ajax] handler reads the form.
        document.addEventListener('submit', (event) => {
            if (event.target instanceof HTMLFormElement) {
                syncPhones(event.target);
            }
        }, true);
    }
}
