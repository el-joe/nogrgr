/**
 * VEN-012b "Translate with AI" modal of the product edit page ([data-ai-translate]):
 *  - Translate / Regenerate: the chosen store languages go to the preview endpoint (custom
 *    AI provider, nothing saved); the returned texts fill the pre-rendered textareas of
 *    each language × item × field, which the vendor may still edit;
 *  - Save translations: the visible texts go to the apply endpoint, which redirects back
 *    to the Content tab.
 * No text is translated or validated here: the server decides what may be written.
 */
import { http } from '../../core/http';

const busy = (button, on) => {
    if (!button) {
        return;
    }

    button.toggleAttribute('data-loading', on);
    button.setAttribute('aria-busy', on ? 'true' : 'false');
    button.disabled = on;
};

function bind(root) {
    const modal = root.closest('[role="dialog"]') ?? document;
    const generate = modal.querySelector('[data-ai-generate]');
    const regenerate = modal.querySelector('[data-ai-regenerate]');
    const apply = modal.querySelector('[data-ai-apply]');
    const preview = root.querySelector('[data-ai-preview]');

    const reset = () => {
        preview.hidden = true;
        root.querySelectorAll('[data-ai-preview-locale], [data-ai-preview-field]').forEach((element) => {
            element.hidden = true;
        });
        generate.hidden = false;
        regenerate.hidden = true;
        apply.hidden = true;
    };

    const locales = () => [...root.querySelectorAll('[data-ai-locale]:checked')].map((input) => input.value);

    const run = async (button) => {
        busy(button, true);

        try {
            const response = await http.post(root.dataset.previewUrl, { locales: locales() });
            const translations = response?.data?.translations ?? {};
            reset();

            Object.entries(translations).forEach(([locale, items]) => {
                Object.entries(items).forEach(([key, fields]) => {
                    Object.entries(fields).forEach(([field, text]) => {
                        const wrap = root.querySelector(`[data-ai-preview-field="${CSS.escape(`${locale}|${key}|${field}`)}"]`);
                        const textarea = wrap?.querySelector('textarea');

                        if (!wrap || !textarea) {
                            return;
                        }

                        textarea.value = text;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        wrap.hidden = false;
                        wrap.closest('[data-ai-preview-locale]').hidden = false;
                    });
                });
            });

            preview.hidden = false;
            generate.hidden = true;
            regenerate.hidden = false;
            apply.hidden = false;
        } catch {
            // The http client already showed the error toast.
        } finally {
            busy(button, false);
        }
    };

    generate?.addEventListener('click', () => run(generate));
    regenerate?.addEventListener('click', () => run(regenerate));
    root.querySelectorAll('[data-ai-locale]').forEach((input) => input.addEventListener('change', reset));

    apply?.addEventListener('click', async () => {
        const translations = {};

        root.querySelectorAll('[data-ai-preview-field]:not([hidden])').forEach((wrap) => {
            const [locale, key, field] = wrap.dataset.aiPreviewField.split('|');
            translations[locale] ??= {};
            translations[locale][key] ??= {};
            translations[locale][key][field] = wrap.querySelector('textarea')?.value ?? '';
        });

        busy(apply, true);

        try {
            await http.post(root.dataset.applyUrl, { translations });
        } catch {
            // The http client already showed the error toast (validation included).
        } finally {
            busy(apply, false);
        }
    });
}

export default function initProductAiTranslate() {
    // The modal is rendered next to the edit form, so look in the whole document.
    document.querySelectorAll('[data-ai-translate]:not([data-ai-bound])').forEach((root) => {
        root.dataset.aiBound = '';
        bind(root);
    });
}
