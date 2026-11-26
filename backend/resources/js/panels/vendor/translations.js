/**
 * Vendor › Settings › Store translations (VEN-075), loaded for [data-page="vendor-translations"]:
 *  - the language / content type / field pickers reload the page (a new type starts on
 *    its first field);
 *  - edit buttons in the table fill the shared edit modal (static key, or content type +
 *    id + field) and open it; "Translate" asks the server for a suggestion (custom AI
 *    provider) and puts it in the text box — nothing is saved until Save;
 *  - a successful save closes the modal (close-on-success) and refreshes the table.
 */
import { $ } from '../../core/select2-lib.js';
import { http } from '../../core/http.js';
import { openOverlay } from '../ui/overlays.js';

function bindPickers(root) {
    root.querySelectorAll('form[data-translation-locale]').forEach((form) => {
        form.querySelectorAll('select[data-auto-submit]').forEach((select) => {
            $(select).on('change', () => {
                if (select.name === 'type') {
                    form.querySelector('select[name="field"]')?.setAttribute('disabled', '');
                }

                form.requestSubmit();
            });
        });
    });
}

function bindEditor(root) {
    const modal = document.getElementById('translation-edit-modal');
    const form = modal?.querySelector('[data-translation-form]');

    if (!modal || !form) {
        return;
    }

    const value = form.querySelector('[data-edit-value]') ?? form.querySelector('textarea[name="value"]');
    const fields = Object.fromEntries([...form.querySelectorAll('[data-edit-field]')].map((input) => [input.dataset.editField, input]));
    let suggestUrl = '';

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-translation-edit]');

        if (!button) {
            return;
        }

        event.preventDefault();
        const data = button.dataset;
        form.setAttribute('action', data.url);
        suggestUrl = data.suggestUrl;
        Object.entries(fields).forEach(([name, input]) => {
            const current = data[name];
            input.value = current ?? '';
            input.disabled = current === undefined;
        });
        form.querySelector('[data-edit-label]').textContent = data.label ?? '';
        form.querySelector('[data-edit-english]').textContent = data.english || '—';
        value.value = data.value ?? '';
        value.rows = data.long === '1' ? 8 : 4;
        openOverlay(modal);
    });

    form.querySelector('[data-translation-suggest]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        event.preventDefault();
        button.setAttribute('data-loading', '');
        button.setAttribute('aria-busy', 'true');

        try {
            const payload = { locale: form.querySelector('input[name="locale"]').value };
            Object.entries(fields).forEach(([name, input]) => {
                if (!input.disabled) {
                    payload[name] = input.value;
                }
            });
            const response = await http.post(suggestUrl, payload);

            if (typeof response?.data?.text === 'string') {
                value.value = response.data.text;
                value.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } catch {
            // The http client already showed the error toast.
        } finally {
            button.removeAttribute('data-loading');
            button.removeAttribute('aria-busy');
        }
    });

    form.addEventListener('ajax:success', () => {
        document.querySelectorAll('[data-datatable-card]').forEach((card) => card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true, quiet: true } })));
    });
}

export default function initVendorTranslations(root) {
    bindPickers(root);
    bindEditor(root);
}
