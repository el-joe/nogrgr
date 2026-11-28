/**
 * Cart line behaviour shared by the cart page and the mini cart (x-sf::cart.line /
 * cart.mini-body): quantity selects, swipe / trash remove with undo (core/motion/
 * swipe-remove.js fires `swipe-remove:commit` after the undo window), select all /
 * selected chips, "Remove selected" (partial selections; any selection with
 * data-cart-selection="any"), and the coupon box. Every mutation goes to the
 * Store API and then asks the owner to re-render its fragment (`refresh`).
 */
import { cartApi } from './api';
import { initSwipeRemove } from '../core/motion/swipe-remove';
import { confirmAction } from '../../core/notify';

function syncSelection(scope) {
    const boxes = [...scope.querySelectorAll('[data-cart-select]')];
    const checked = boxes.filter((box) => box.checked);
    const all = scope.querySelector('[data-cart-select-all]');

    if (all) {
        all.checked = boxes.length > 0 && checked.length === boxes.length;
        all.indeterminate = checked.length > 0 && checked.length < boxes.length;
    }

    scope.querySelectorAll('[data-cart-selected-count]').forEach((node) => (node.textContent = String(checked.length)));
    const any = scope.dataset.cartSelection === 'any';
    scope.toggleAttribute('data-has-selection', checked.length > 0 && (any || checked.length < boxes.length));

    const filter = scope.querySelector('[data-cart-filter][aria-pressed="true"]')?.dataset.cartFilter ?? 'all';
    boxes.forEach((box) => {
        const row = box.closest('[data-cart-line]');

        if (row) {
            row.toggleAttribute('hidden', filter === 'selected' && !box.checked);
        }
    });
}

export function bindCartLines(root, refresh) {
    if (root.__cartLines) {
        return;
    }

    root.__cartLines = true;
    let busy = Promise.resolve();
    const queue = (task) => (busy = busy.then(task, task).then(refresh, refresh));

    root.addEventListener('change', (event) => {
        const select = event.target.closest('[data-cart-qty]');

        if (select) {
            const row = select.closest('[data-cart-line]');
            row?.setAttribute('aria-busy', 'true');
            queue(() => cartApi.updateQuantity(row.dataset.cartLine, Number(select.value)).catch(() => {}));
            return;
        }

        if (event.target.matches('[data-cart-select-all]')) {
            event.target.closest('[data-cart-selection]')?.querySelectorAll('[data-cart-select]').forEach((box) => (box.checked = event.target.checked));
        }

        const scope = event.target.closest('[data-cart-selection]');

        if (scope && event.target.matches('[data-cart-select], [data-cart-select-all]')) {
            syncSelection(scope);
        }
    });

    root.addEventListener('click', async (event) => {
        const filter = event.target.closest('[data-cart-filter]');

        if (filter) {
            const scope = filter.closest('[data-cart-selection]');
            scope.querySelectorAll('[data-cart-filter]').forEach((chip) => chip.setAttribute('aria-pressed', String(chip === filter)));
            syncSelection(scope);
            return;
        }

        const removeSelected = event.target.closest('[data-cart-remove-selected]');

        if (removeSelected) {
            const scope = removeSelected.closest('[data-cart-selection]');
            const rows = [...scope.querySelectorAll('[data-cart-select]:checked')].map((box) => box.closest('[data-cart-line]')).filter(Boolean);

            const { confirmTitle, confirmText, confirmButton } = removeSelected.dataset;
            const question = {
                title: confirmTitle || removeSelected.textContent.trim() || removeSelected.getAttribute('aria-label'),
                ...(confirmText ? { text: confirmText } : {}),
                ...(confirmButton ? { confirmText: confirmButton } : {}),
                danger: true,
            };

            if (rows.length && (await confirmAction(question))) {
                rows.forEach((row) => row.setAttribute('aria-busy', 'true'));
                /* One at a time: each removal recalculates the same cart. */
                queue(() => rows.reduce((chain, row) => chain.then(() => cartApi.remove(row.dataset.cartLine).catch(() => {})), Promise.resolve()));
            }
            return;
        }

        const apply = event.target.closest('[data-coupon-apply]');

        if (apply) {
            applyCoupon(apply.closest('[data-coupon-form]'));
            return;
        }

        if (event.target.closest('[data-coupon-remove]')) {
            queue(() => cartApi.removeCoupon().catch(() => {}));
        }
    });

    const applyCoupon = (form) => {
        const input = form.querySelector('input[name="code"]');
        const button = form.querySelector('[data-coupon-apply]');
        const error = form.closest('[data-coupon]')?.querySelector('[data-coupon-error]');
        const code = input.value.trim();

        if (!code) {
            input.focus();
            return;
        }

        button.disabled = true;
        busy = busy.then(async () => {
            try {
                await cartApi.applyCoupon(code);
                await refresh();
            } catch (failure) {
                if (error) {
                    error.textContent = failure?.payload?.errors?.code?.[0] ?? failure?.payload?.message ?? '';
                    error.hidden = !error.textContent;
                }
                button.disabled = false;
                input.setAttribute('aria-invalid', 'true');
            }
        });
    };

    /* Enter in the coupon field applies it (and never submits the checkout form). */
    root.addEventListener('keydown', (event) => {
        const form = event.target.closest('[data-coupon-form]');

        if (form && event.key === 'Enter') {
            event.preventDefault();
            applyCoupon(form);
        }
    });

    /* Swipe / trash: the row collapsed and the undo window passed → remove on the server. */
    root.addEventListener('swipe-remove:commit', (event) => {
        const row = event.detail?.row;

        if (row?.dataset.cartLine) {
            queue(() => cartApi.remove(row.dataset.cartLine).catch(() => {}));
        }
    });
}

/**
 * After a fragment swap: bind swipe rows and restore the selection counters.
 */
export function enhanceLines(container) {
    initSwipeRemove(container);
    container.querySelectorAll('[data-cart-selection]').forEach(syncSelection);
}
