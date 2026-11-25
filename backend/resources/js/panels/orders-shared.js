/**
 * Behaviour shared by the admin and vendor order pages (decision #71). Delegated on
 * document so it also works inside remote-modal fragments loaded later.
 */
import { http } from '../core/http';

function bindPrint(root) {
    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-print]')) {
            event.preventDefault();
            window.print();
        }
    });

    if (root.querySelector('[data-autoprint]')) {
        window.setTimeout(() => window.print(), 300);
    }
}

function bindEstimate() {
    document.addEventListener('change', async (event) => {
        const select = event.target.closest?.('[data-estimate-url]');

        if (!select || !select.value) {
            return;
        }

        const output = select.closest('form')?.querySelector('[data-estimate]');

        if (!output) {
            return;
        }

        output.hidden = false;
        output.textContent = output.dataset.loadingText ?? '…';

        try {
            const payload = await http.request('POST', select.dataset.estimateUrl, { data: { shipping_company_id: select.value }, silent: true });
            output.textContent = payload?.data?.text ?? '';
        } catch {
            output.textContent = output.dataset.errorText ?? '';
        }
    });
}

function bindBulk(root) {
    const form = root.querySelector('#shipping-bulk-form');

    if (!form) {
        return;
    }

    const button = root.querySelector('[data-bulk-submit]');
    const count = root.querySelector('[data-bulk-count]');
    const selected = () => [...document.querySelectorAll('[data-row-select]:checked')];
    const refresh = () => {
        const total = selected().length;

        if (button) {
            button.disabled = total === 0;
        }

        if (count) {
            count.textContent = String(total);
        }
    };

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-select-all]')) {
            document.querySelectorAll('[data-row-select]').forEach((box) => (box.checked = event.target.checked));
        }

        if (event.target.matches('[data-select-all], [data-row-select]')) {
            refresh();
        }
    });

    form.addEventListener('ajax:success', () => {
        root.querySelectorAll('[data-datatable-card]').forEach((card) => card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } })));
        const all = root.querySelector('[data-select-all]');

        if (all) {
            all.checked = false;
        }

        window.setTimeout(refresh, 400);
    });

    document.addEventListener('draw.dt', refresh);
    refresh();
}

let estimateBound = false;

/** Revenue split bars: width from data-width (percent), no inline styles in the markup. */
function applyWidths(root) {
    root.querySelectorAll('[data-width]').forEach((bar) => {
        const value = Math.max(0, Math.min(100, Number(bar.dataset.width) || 0));
        bar.style.width = `${value}%`;
    });
}

export function initOrderPage(root) {
    applyWidths(root);
    bindPrint(root);
    bindBulk(root);

    if (!estimateBound) {
        estimateBound = true;
        bindEstimate();
    }
}
