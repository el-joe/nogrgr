/**
 * Vendor › Settings › Account (VEN-070), loaded for [data-page="vendor-account"]: clears
 * the password form after a successful change.
 */
export default function initVendorAccount(root) {
    root.querySelectorAll('form[data-reset-on-success]').forEach((form) => {
        form.addEventListener('ajax:success', () => form.reset());
    });
}
