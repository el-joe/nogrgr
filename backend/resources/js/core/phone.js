/**
 * intl-tel-input on every phone field: <input type="tel" data-phone name="phone">.
 * The visitor country comes from <html data-country="sa"> (server side, no geo-IP CDN).
 * Submits `<name>` as typed plus hidden `<name>_e164` and `<name>_country`.
 */
import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/styles';

export function initPhoneInputs(root = document) {
    const country = document.documentElement.dataset.country || 'sa';

    root.querySelectorAll('input[data-phone]:not([data-phone-ready])').forEach((input) => {
        input.dataset.phoneReady = '1';
        const name = input.getAttribute('name') ?? 'phone';

        intlTelInput(input, {
            initialCountry: input.dataset.country || country,
            separateDialCode: true,
            strictMode: true,
            hiddenInputs: () => ({ phone: `${name}_e164`, country: `${name}_country` }),
            countrySearch: true,
            loadUtils: () => import('intl-tel-input/utils'),
        });
    });
}
