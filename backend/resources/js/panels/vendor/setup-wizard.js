/**
 * Vendor setup wizard (page key "vendor-setup-wizard", VEN-007).
 *
 * The "Default country" select only offers the countries picked above (chips + the
 * "more countries" select), QA-P5-012. Picking the first country pre-selects it as the
 * default; un-picking the current default clears it (or falls back to the first pick).
 */
import { $ } from '../../core/select2-lib.js';

function pickedCountries(root) {
    const picked = new Map();

    root.querySelectorAll('[data-country-chip]').forEach((chip) => {
        if (chip.checked) {
            picked.set(chip.value, chip.dataset.label || chip.dataset.name || chip.value);
        }
    });

    const more = root.querySelector('select[data-country-more]');

    if (more) {
        Array.from(more.selectedOptions).forEach((option) => {
            picked.set(option.value, option.textContent.trim());
        });
    }

    return picked;
}

export default function initSetupWizard(root) {
    const select = root.querySelector('select[data-default-country]');

    if (!select) {
        return;
    }

    const placeholder = select.querySelector('option[value=""]');

    const sync = () => {
        const picked = pickedCountries(root);
        const current = select.value;

        Array.from(select.options).forEach((option) => {
            if (option.value !== '') {
                option.remove();
            }
        });

        picked.forEach((label, value) => {
            const option = new Option(label, value, false, false);
            select.append(option);
        });

        let next = picked.has(current) ? current : '';

        if (!next && picked.size > 0) {
            next = picked.keys().next().value;
        }

        if (placeholder && !select.contains(placeholder)) {
            select.prepend(placeholder);
        }

        $(select).val(next).trigger('change');
    };

    root.querySelectorAll('[data-country-chip]').forEach((chip) => {
        chip.addEventListener('change', sync);
    });

    const more = root.querySelector('select[data-country-more]');

    if (more) {
        // Select2 fires jQuery change events, not native ones.
        $(more).on('change', sync);
    }
}
