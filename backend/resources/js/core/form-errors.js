/**
 * Renders Laravel 422 `errors` onto a form.
 * Field "items.0.qty" ↔ input name "items[0][qty]"; "images.2" (one file of
 * <input name="images[]">) falls back to that input's field.
 * Message target: element with [data-error-for="items.0.qty"] (x-ui::* fields render
 * it hidden; created if absent). Sets aria-invalid on the input, which the
 * .ui-control / Select2 / checkbox styles turn into the Figma error state.
 * Dispatches `form:errors` (detail: {keys}) so locale tabs and tab sets can flag
 * and open the tab holding the first invalid field.
 * bindClearOnInput(): once the user edits a flagged field (`input` / `change`, incl.
 * the Select2 bridge, date pickers' hidden inputs, intl-tel-input, rich-text
 * editors' hidden textareas) its message and aria-invalid go away (RQA-ADM-011).
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
        let input = form.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
        let target = form.querySelector(`[data-error-for="${CSS.escape(key)}"]`);

        /* A file of a multi-file input ("images.2" for <input name="images[]">): show it on that field. */
        const parent = key.replace(/\.\d+$/, '');

        if (!input && !target && parent !== key) {
            input = form.querySelector(`[name="${CSS.escape(fieldName(parent))}[]"]`);
            target = form.querySelector(`[data-error-for="${CSS.escape(parent)}"]`);

            if (target && !target.hidden) {
                continue;
            }
        }

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

/** "items[0][qty]" -> "items.0.qty"; "tags[]" -> "tags". */
export function errorKey(name) {
    return name.replace(/\[\]$/, '').replace(/\[([^\]]*)\]/g, '.$1');
}

/** The named control an edit belongs to (a rich editor's contenteditable maps to its hidden textarea). */
function editedField(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    if (target.getAttribute('name')) {
        return target;
    }

    return target.closest('[data-rich-editor]')?.querySelector('[data-editor-input]') ?? null;
}

export function clearFieldError(form, field) {
    const name = field.getAttribute('name');

    if (!name) {
        return;
    }

    const keys = [errorKey(name)];

    /* intl-tel-input posts <name>_e164 / <name>_country: their errors belong to the visible field. */
    if (field.matches('input[data-phone]')) {
        keys.push(`${keys[0]}_e164`, `${keys[0]}_country`);
    }

    let cleared = field.getAttribute('aria-invalid') === 'true';
    field.removeAttribute('aria-invalid');

    form.querySelectorAll('[data-error-for]').forEach((el) => {
        const key = el.dataset.errorFor;

        if (!el.hidden && keys.some((k) => key === k || key.startsWith(`${k}.`))) {
            el.textContent = '';
            el.hidden = true;
            cleared = true;
        }
    });

    keys.slice(1).forEach((key) => form.querySelector(`[name="${CSS.escape(fieldName(key))}"]`)?.removeAttribute('aria-invalid'));

    if (!cleared) {
        return;
    }

    /* Locale tabs / tab sets: drop the error dot once no invalid field is left in that pane. */
    form.querySelectorAll('[role="tab"][data-error]').forEach((tab) => {
        const panel = document.getElementById(tab.getAttribute('aria-controls'));

        if (panel && !panel.querySelector('[aria-invalid="true"]')) {
            delete tab.dataset.error;
        }
    });

    form.dispatchEvent(new CustomEvent('form:error-cleared', { bubbles: true, detail: { keys } }));
}

export function bindClearOnInput(root = document) {
    if (root.__clearOnInputBound) {
        return;
    }

    root.__clearOnInputBound = true;

    const handler = (event) => {
        const field = editedField(event.target);
        const form = field?.form ?? field?.closest('form');

        if (field && form) {
            clearFieldError(form, field);
        }
    };

    ['input', 'change', 'countrychange'].forEach((type) => root.addEventListener(type, handler));
}
