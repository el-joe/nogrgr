/**
 * Vendor setup wizard (page key "vendor-setup-wizard", VEN-007): picking the first
 * country chip pre-selects it as the default country when none is chosen yet.
 */
import { $ } from '../../core/select2-lib.js';

export default function initSetupWizard(root) {
    const select = root.querySelector('select[data-default-country]');

    if (!select) {
        return;
    }

    root.querySelectorAll('[data-country-chip]').forEach((chip) => {
        chip.addEventListener('change', () => {
            if (chip.checked && !select.value) {
                $(select).val(chip.value).trigger('change');
            }
        });
    });
}
