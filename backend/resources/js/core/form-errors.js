/**
 * Renders Laravel 422 `errors` onto a form.
 * Field "items.0.qty" ↔ input name "items[0][qty]".
 * Message target: element with [data-error-for="items.0.qty"] (x-ui::* fields render
 * it hidden; created if absent). Sets aria-invalid on the input, which the
 * .ui-control / Select2 / checkbox styles turn into the Figma error state.
 * Dispatches `form:errors` (detail: {keys}) so locale tabs and tab sets can flag
 * and open the tab holding the first invalid field.
 */
export function fieldName(key) {
    const [first, ...rest] = key.split('.');

    return first + rest.map((part) => `[${part}]`).join('');
}

export function clearErrors(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((el) => el.removeAttribute('aria-invalid'));
    form.querySelectorAll('[data-error-for]').forEach((el) => {
        el.textContent = '';
        el.hidden = true;
    });
    form.querySelectorAll('[role="tab"][data-error]').forEach((tab) => delete tab.dataset.error);
}

export function showErrors(form, errors = {}) {
    clearErrors(form);
    let first = null;

    for (const [key, messages] of Object.entries(errors)) {
        const name = fieldName(key);
        const input = form.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
        let target = form.querySelector(`[data-error-for="${CSS.escape(key)}"]`);

        if (!target && input) {
            target = document.createElement('p');
            target.dataset.errorFor = key;
            target.className = 'ui-error mt-1.5 text-xs text-danger';
            target.setAttribute('role', 'alert');
            (input.closest('[data-field]') ?? input).insertAdjacentElement('afterend', target);
        }

        if (target) {
            target.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            target.hidden = false;
        }

        if (input) {
            input.setAttribute('aria-invalid', 'true');
            first ??= input;
        }
    }

    form.dispatchEvent(new CustomEvent('form:errors', { bubbles: true, detail: { keys: Object.keys(errors), first } }));
    first?.focus({ preventScroll: false });
}
