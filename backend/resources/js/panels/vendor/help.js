/**
 * Vendor › Help center (page key "vendor-help", VEN-092 / 092a): "Was this helpful?"
 * buttons POST {helpful: 1|0} to [data-help-feedback] data-url, mark the chosen button
 * (aria-pressed) and show the matching thank-you line.
 */
import { http } from '../../core/http';

export default function initHelp(root = document) {
    const footer = root.querySelector('[data-help-feedback]');

    if (!footer) {
        return;
    }

    const status = footer.querySelector('[data-help-feedback-status]');
    const buttons = [...footer.querySelectorAll('[data-help-vote]')];

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const helpful = button.dataset.helpVote === '1';
            buttons.forEach((other) => other.setAttribute('disabled', ''));

            try {
                await http.post(footer.dataset.url, { helpful: helpful ? 1 : 0 }, { toast: false });
                buttons.forEach((other) => {
                    const pressed = other === button;
                    other.setAttribute('aria-pressed', pressed ? 'true' : 'false');
                    other.classList.toggle('text-ink-brand', pressed);
                });
                status.textContent = helpful ? footer.dataset.thanksYes : footer.dataset.thanksNo;
                status.classList.remove('hidden');
            } catch {
                // http.js shows the error toast
            } finally {
                buttons.forEach((other) => other.removeAttribute('disabled'));
            }
        });
    });
}
