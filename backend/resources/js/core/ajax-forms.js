/**
 * <form data-ajax> submits through the http client (JSON contract, 422 field errors).
 */
import { http } from './http';

export function bindAjaxForms(root = document) {
    root.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax]')) {
            return;
        }

        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        submit?.setAttribute('data-loading', '');
        submit?.setAttribute('aria-busy', 'true');

        try {
            const method = form.querySelector('input[name="_method"]')?.value ?? form.getAttribute('method') ?? 'POST';
            const payload = await http.request(method, form.action, { data: new FormData(form), form });
            form.dispatchEvent(new CustomEvent('ajax:success', { detail: payload, bubbles: true }));
        } catch (error) {
            form.dispatchEvent(new CustomEvent('ajax:error', { detail: error, bubbles: true }));
        } finally {
            submit?.removeAttribute('data-loading');
            submit?.removeAttribute('aria-busy');
        }
    });
}
