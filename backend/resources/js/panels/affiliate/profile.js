/**
 * Affiliate › Profile (AFF-013): the payout-details form shows only the fields of the
 * chosen method (PayPal / bank transfer); the password form clears after a change.
 */
export default function initAffiliateProfile(root = document) {
    const form = root.querySelector('[data-payout-details]');
    const method = form?.querySelector('[data-payout-method]');

    if (form && method) {
        const sync = () => {
            form.querySelectorAll('[data-payout-fields]').forEach((group) => {
                group.hidden = group.dataset.payoutFields !== method.value;
            });
        };

        method.addEventListener('change', sync);
        sync();
    }

    root.querySelectorAll('form[data-reset-on-success]').forEach((passwordForm) => {
        passwordForm.addEventListener('ajax:success', () => passwordForm.reset());
    });
}
