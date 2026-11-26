/**
 * Vendor › Subscription & balance (VEN-060 / 060a): the Change plan modal keeps its price
 * line, "Due today" and Pay button in sync with the chosen plan + billing term (prices
 * come pre-formatted from the server in a JSON island); Pay now buttons (alert and invoice
 * rows rendered later by the table) fill the invoice modal; every checkout submit shows
 * the busy state while the browser leaves for the hosted checkout.
 */
import { openOverlay } from '../ui/index.js';

function readPrices(root) {
    const island = root.querySelector('[data-plan-prices]');

    if (!island) {
        return null;
    }

    try {
        return JSON.parse(island.textContent || '{}');
    } catch {
        return null;
    }
}

function bindChangePlan(root) {
    const form = root.querySelector('[data-change-plan-form]');
    const data = readPrices(root);

    if (!form || !data) {
        return;
    }

    const submit = document.querySelector('[data-change-submit]');
    const template = submit?.dataset.labelTemplate ?? '';
    const lineTemplate = form.querySelector('[data-change-line]');
    const plans = new Map((data.plans ?? []).map((plan) => [String(plan.id), plan]));

    const update = () => {
        const planId = form.querySelector('input[name="plan_id"]:checked')?.value;
        const term = form.querySelector('input[name="term"]:checked')?.value;
        const plan = plans.get(String(planId));
        const price = plan?.prices?.[term];

        if (!plan || !price) {
            return;
        }

        const set = (selector, value) => {
            const element = form.querySelector(selector);

            if (element) {
                element.textContent = value;
            }
        };

        set('[data-change-price]', price.amount);
        set('[data-change-due]', price.amount);

        if (lineTemplate) {
            lineTemplate.textContent = `${plan.name} ${(data.terms?.[term] ?? '').toLocaleLowerCase()}`;
        }

        if (submit) {
            const label = submit.querySelector('.ui-btn-label') ?? submit;
            label.textContent = template.replace('__AMOUNT__', price.amount);
            submit.toggleAttribute('disabled', !price.payable);
        }
    };

    form.addEventListener('change', update);
    update();
}

function bindInvoicePay(root) {
    const modal = document.getElementById('invoice-pay-modal');

    if (!modal) {
        return;
    }

    const fill = (selector, value) => {
        const element = modal.querySelector(selector);

        if (element) {
            element.textContent = value ?? '';
        }
    };

    root.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-invoice-pay]');

        if (!opener) {
            return;
        }

        event.preventDefault();

        const input = modal.querySelector('[data-invoice-id-input]');

        if (input) {
            input.value = opener.dataset.invoiceId ?? '';
        }

        fill('[data-invoice-number-label]', opener.dataset.invoiceNumber);
        fill('[data-invoice-amount-label]', opener.dataset.invoiceAmount);
        openOverlay(modal);
    });
}

function bindBusySubmit() {
    document.querySelectorAll('[data-checkout-form], [data-change-plan-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const submit = document.querySelector(`[type="submit"][form="${form.id}"]`);
            submit?.setAttribute('data-loading', '');
            submit?.setAttribute('aria-busy', 'true');
        });
    });
}

export default function initVendorSubscription(root) {
    bindChangePlan(document);
    bindInvoicePay(root);
    bindBusySubmit();
}
