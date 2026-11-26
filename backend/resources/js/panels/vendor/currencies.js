/**
 * Vendor › Settings › Currencies (VEN-072), loaded for [data-page="vendor-currencies"]:
 * a currency that is switched off cannot be the default of its countries, so its
 * "Default" switch is turned off and disabled with it (the server enforces the same rule).
 */
function sync(row) {
    const active = row.querySelector('[data-currency-active]');
    const fallback = row.querySelector('[data-currency-default]');

    if (!active || !fallback || active.disabled) {
        return;
    }

    if (!active.checked) {
        fallback.checked = false;
    }

    fallback.disabled = !active.checked;
}

export default function initVendorCurrencies(root) {
    root.querySelectorAll('[data-currency-row]').forEach((row) => {
        sync(row);
        row.querySelector('[data-currency-active]')?.addEventListener('change', () => sync(row));
    });
}
