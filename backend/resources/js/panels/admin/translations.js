/**
 * Admin › Localization › Translations / key locks (ADM-092 / 093): changing the file or
 * the language reloads the page with the new scope (the table columns, tabs and counts
 * depend on it). The Apply button stays as the no-JS / keyboard path.
 */
export default function initTranslations(root) {
    const form = root.querySelector('form[data-translations-scope]');

    if (!form) {
        return;
    }

    form.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', () => form.requestSubmit());
    });
}
