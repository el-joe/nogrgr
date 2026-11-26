/**
 * Admin › Localization › Translations / key locks (ADM-092 / 093): changing the file or
 * the language reloads the page with the new scope (the table columns, tabs and counts
 * depend on it). The Apply button stays as the no-JS / keyboard path. ADM-092a AI
 * translation (modal, progress, one-key suggestion): ai-translation-runs.js.
 */
import initAiTranslationRuns from './ai-translation-runs.js';

export default function initTranslations(root) {
    initAiTranslationRuns(root);

    const form = root.querySelector('form[data-translations-scope]');

    if (!form) {
        return;
    }

    form.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', () => form.requestSubmit());
    });
}
