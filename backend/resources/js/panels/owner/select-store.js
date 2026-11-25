/**
 * Owner › Select store (OWN-002): the submit button shows its loading state while the
 * store panel opens.
 */
export default function initOwnerSelectStore(root = document) {
    const form = root.querySelector('[data-page="owner-select-store"] form');

    form?.addEventListener('submit', () => {
        const submit = form.querySelector('[type="submit"]');
        submit?.setAttribute('data-loading', '');
        submit?.setAttribute('aria-busy', 'true');
    });
}
