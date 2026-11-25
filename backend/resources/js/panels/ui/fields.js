/**
 * Small field behaviours:
 *  - textarea character counter: <textarea data-counter="#id-count" maxlength="500">
 *  - password reveal:            <button data-password-toggle="#id">
 *  - switch optimistic update:   <input type="checkbox" data-switch-url="…" data-switch-field="is_active">
 *    PATCHes {field: 0|1}, shows the toast from the JSON response, reverts on failure.
 *  - global search shortcut:     Ctrl/Cmd + K focuses [data-global-search]
 *  - copy to clipboard:          <button data-copy-text="…"> (toast on success)
 *  - proxy click:                <button data-click-target="#period"> clicks/focuses that control
 */
import { http } from '../../core/http';
import { notify } from '../../core/notify';
import { t } from '../../core/i18n';

function updateCounter(textarea) {
    const target = document.querySelector(textarea.dataset.counter);

    if (target) {
        const max = textarea.maxLength > 0 ? textarea.maxLength : null;
        target.textContent = max ? `${textarea.value.length} / ${max}` : String(textarea.value.length);
    }
}

let bound = false;

export function initFields(root = document) {
    root.querySelectorAll('textarea[data-counter]').forEach(updateCounter);
    root.querySelectorAll('input[data-indeterminate]').forEach((input) => (input.indeterminate = true));

    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener('input', (event) => {
        if (event.target.matches?.('textarea[data-counter]')) {
            updateCounter(event.target);
        }
    });

    document.addEventListener('click', async (event) => {
        const copy = event.target.closest?.('[data-copy-text]');

        if (copy) {
            try {
                await navigator.clipboard.writeText(copy.dataset.copyText);
                notify.success(t('ui.copied'));
            } catch {
                notify.error(t('ui.copy_failed'));
            }

            return;
        }

        const proxy = event.target.closest?.('[data-click-target]');

        if (proxy) {
            const target = document.querySelector(proxy.dataset.clickTarget);
            target?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            setTimeout(() => (target?.click(), target?.focus()), 0);

            return;
        }

        const toggle = event.target.closest?.('[data-password-toggle]');

        if (toggle) {
            const input = document.querySelector(toggle.dataset.passwordToggle);

            if (input) {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(show));
            }
        }
    });

    document.addEventListener('change', async (event) => {
        const input = event.target;

        if (!input.matches?.('input[data-switch-url]')) {
            return;
        }

        const checked = input.checked;
        input.disabled = true;

        try {
            await http.patch(input.dataset.switchUrl, { [input.dataset.switchField || input.name || 'value']: checked ? 1 : 0 });
        } catch {
            input.checked = !checked;
        } finally {
            input.disabled = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            const search = document.querySelector('[data-global-search]');

            if (search) {
                event.preventDefault();
                search.focus();
                search.select?.();
            }
        }
    });
}
