/**
 * Vendor › Settings › Mail (VEN-081), loaded for [data-page="vendor-mail"]: "Send test
 * email" posts the values currently on the form (+ recipient) to [data-test-url] and shows
 * the delivery result inline ([data-mail-result]) — success with the time, or the SMTP error.
 */
import { http } from '../../core/http.js';
import { closeOverlay } from '../ui/index.js';

function showResult(box, delivered, message) {
    box.hidden = false;
    box.classList.toggle('bg-success-bg', delivered);
    box.classList.toggle('text-success', delivered);
    box.classList.toggle('bg-danger-bg', !delivered);
    box.classList.toggle('text-danger', !delivered);
    box.querySelector('[data-result-icon="success"]')?.toggleAttribute('hidden', !delivered);
    box.querySelector('[data-result-icon="error"]')?.toggleAttribute('hidden', delivered);
    box.querySelector('[data-mail-result-text]').textContent = message ?? '';
}

export default function initVendorMail(root) {
    const form = (root ?? document).querySelector('form[data-mail-form]');
    const send = document.querySelector('[data-mail-test-send]');
    const recipient = document.querySelector('[data-mail-test-email]');
    const box = form?.querySelector('[data-mail-result]');

    if (!form || !send || !recipient || !box || !form.dataset.testUrl) {
        return;
    }

    send.addEventListener('click', async () => {
        const data = new FormData(form);
        data.delete('_method');
        data.set('test_email', recipient.value.trim());
        send.setAttribute('data-loading', '');
        send.setAttribute('aria-busy', 'true');

        try {
            const payload = await http.post(form.dataset.testUrl, data, { form, toast: false });
            showResult(box, true, payload?.data?.message ?? payload?.message);
            closeOverlay('vendor-mail-test');
        } catch (error) {
            const fieldError = Object.values(error?.errors ?? {})[0]?.[0];
            showResult(box, false, fieldError ?? error?.payload?.message ?? error?.message);
            closeOverlay('vendor-mail-test');
        } finally {
            send.removeAttribute('data-loading');
            send.removeAttribute('aria-busy');
        }
    });
}
