/**
 * Owner › Subscriptions (OWN-003): a Renew / Pay now button (also inside table rows,
 * rendered after load) fills the renewal modal (store, plan, amount, form action)
 * and opens it; the form posts to the renew route which redirects to the checkout.
 */
import { openOverlay } from '../ui/index.js';

export default function initOwnerSubscriptions(root = document) {
    const modal = root.getElementById?.('renew-modal') ?? document.getElementById('renew-modal');

    if (!modal) {
        return;
    }

    const form = modal.querySelector('[data-renew-form]');
    const fill = (selector, value) => {
        const element = modal.querySelector(selector);

        if (element) {
            element.textContent = value ?? '';
        }
    };

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-renew-open]');

        if (!opener) {
            return;
        }

        event.preventDefault();

        if (form) {
            form.action = opener.dataset.renewUrl;
        }

        fill('[data-renew-store-label]', opener.dataset.renewStore);
        fill('[data-renew-plan-label]', opener.dataset.renewPlan);
        fill('[data-renew-amount-label]', opener.dataset.renewAmount);
        openOverlay(modal);
    });

    form?.addEventListener('submit', () => {
        const submit = modal.querySelector('[type="submit"]');
        submit?.setAttribute('data-loading', '');
        submit?.setAttribute('aria-busy', 'true');
    });
}
