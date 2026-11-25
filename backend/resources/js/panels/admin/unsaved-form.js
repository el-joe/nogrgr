/**
 * form[data-unsaved-form]: marks the form dirty on edit (data-dirty), shows its
 * [data-unsaved-bar] if it has one and clears both after a successful save;
 * [data-unsaved-discard] reloads the page.
 */
export function bindUnsavedForms(root) {
    root.querySelectorAll('form[data-unsaved-form]').forEach((form) => {
        const bar = form.querySelector('[data-unsaved-bar]');
        const markDirty = (event) => {
            if (event.target?.closest?.('[data-unsaved-ignore]')) {
                return;
            }
            form.dataset.dirty = 'true';
            if (bar) {
                bar.hidden = false;
            }
        };

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('ajax:success', () => {
            delete form.dataset.dirty;
            if (bar) {
                bar.hidden = true;
            }
        });
        bar?.querySelector('[data-unsaved-discard]')?.addEventListener('click', () => window.location.reload());
    });
}
