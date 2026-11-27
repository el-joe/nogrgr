/**
 * Admin › Localization › Translations / key locks (ADM-092 / 093): changing the file or
 * the language reloads the page with the new scope (the table columns, tabs and counts
 * depend on it). The Apply button stays as the no-JS / keyboard path. ADM-092a AI
 * translation (modal, progress, one-key suggestion): ai-translation-runs.js. The add key /
 * new file / edit key / Translate with AI buttons open remote modals (QA-FINAL-005).
 */
import initAiTranslationRuns from './ai-translation-runs.js';
import { bindRemoteModals } from '../remote-modal.js';

export default function initTranslations(root) {
    bindRemoteModals(root);
    initAiTranslationRuns(root);

    const form = root.querySelector('form[data-translations-scope]');

    if (!form) {
        return;
    }

    form.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', () => form.requestSubmit());
    });
}
