/**
 * Storefront account order pages (SF-ECO-024/027/028/029/033):
 *   cancel order, 2 steps   [data-cancel-order='{url, number, paid}'] opens #sf-cancel-order
 *   write a review          [data-review='{url, items}'] opens #sf-write-review (stars + body)
 *   reorder                 [data-reorder='[{store_product_id, variant_id, quantity, name}]']
 *                           → Store API cart, then the cart page
 *   details ⇄ tracking      [data-order-tabs] switches in place, URL follows (history)
 *   print invoice           [data-print-invoice]
 *   list filters            [data-filter-tabs] / [data-filter-search] over [data-filter-item]
 *   wishlist remove         [data-wishlist-remove] → storefront.favorites.toggle, card leaves
 * Every request goes through core/http (CSRF, 401 → sign in, 422 → field errors + toast).
 */
import { http } from '../../../core/http';
import { notify } from '../../../core/notify';
import { context } from '../../catalog/context';
import { prefersReducedMotion } from '../motion/utils';

const json = (value, fallback) => {
    try {
        return JSON.parse(value ?? '');
    } catch {
        return fallback;
    }
};

const setBusy = (button, busy) => {
    if (!button) {
        return;
    }
    button.toggleAttribute('data-loading', busy);
    button.setAttribute('aria-busy', String(busy));
};

/* ---------- Cancel order (2 steps) ---------- */
function bindCancel() {
    const modal = document.querySelector('[data-cancel-modal]');

    if (!modal) {
        return;
    }

    const stepOne = modal.querySelector('[data-cancel-step="1"]');
    const form = modal.querySelector('[data-cancel-form]');
    const texts = modal.querySelector('template[data-i18n-cancel]')?.content;
    const text = (key) => texts?.querySelector(`[data-text="${key}"]`)?.textContent ?? '';
    let current = null;

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-cancel-order]');

        if (!opener) {
            return;
        }

        current = json(opener.dataset.cancelOrder, null);
        form.setAttribute('action', current?.url ?? '');
        stepOne.hidden = false;
        form.hidden = true;
        modal.querySelector('[data-cancel-reason-error]').hidden = true;
        form.querySelector('[data-cancel-note]').value = '';
    });

    const noteField = () => form.querySelector('[data-cancel-note]');
    const noteError = () => form.querySelector('[data-cancel-note-required]');
    let needsNote = false;

    modal.querySelector('[data-cancel-next]')?.addEventListener('click', () => {
        const choice = stepOne.querySelector('input[name="cancel_reason"]:checked');

        if (!choice) {
            modal.querySelector('[data-cancel-reason-error]').hidden = false;
            return;
        }

        needsNote = choice.dataset.requiresNote === '1';
        form.querySelector('input[name="reason_code"]').value = choice.value;
        noteError().hidden = true;
        form.querySelector('[data-cancel-summary]').textContent = text('summary').replace(':number', current?.number ?? '');
        const refund = form.querySelector('[data-cancel-refund]');
        refund.hidden = !current?.paid;
        refund.textContent = current?.paid ? text('refund').replace(':amount', current.paid) : '';
        stepOne.hidden = true;
        form.hidden = false;
        form.querySelector('[type="submit"]')?.focus({ preventScroll: true });
    });

    /* Store API: `reason_code` of the customer list + the free note as `reason`. Capture: runs before ajax-forms. */
    form.addEventListener('submit', (event) => {
        const note = noteField().value.trim();
        form.querySelector('input[name="reason"]').value = note;

        if (needsNote && !note) {
            event.preventDefault();
            event.stopImmediatePropagation();
            noteError().hidden = false;
            noteField().focus();
        }
    }, true);

    form.addEventListener('ajax:success', () => {
        window.setTimeout(() => window.location.reload(), 600);
    });
}

/* ---------- Write a review ---------- */
function bindReview() {
    const modal = document.querySelector('[data-review-modal]');

    if (!modal) {
        return;
    }

    const form = modal.querySelector('[data-review-form]');
    const list = modal.querySelector('[data-review-items]');
    const template = modal.querySelector('template[data-review-item-template]');
    const stars = [...modal.querySelectorAll('[data-star]')];
    let current = null;

    const paintStars = (value) => stars.forEach((star) => star.toggleAttribute('data-on', Number(star.dataset.star) <= value));
    const target = () => {
        const id = list.querySelector('input[name="review_item"]:checked')?.value ?? current?.items?.[0]?.id;
        form.setAttribute('action', (current?.url ?? '').replace('__ITEM__', String(id)));
    };

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-review]');

        if (!opener) {
            return;
        }

        current = json(opener.dataset.review, { items: [] });
        form.reset();
        paintStars(0);
        list.replaceChildren(...(current.items ?? []).map((item, index) => {
            const node = template.content.firstElementChild.cloneNode(true);
            const input = node.querySelector('[data-review-item-input]');
            input.value = item.id;
            input.checked = index === 0;
            input.classList.toggle('sr-only', current.items.length === 1);
            node.querySelector('[data-review-item-name]').textContent = item.name;
            node.querySelector('[data-review-item-variant]').textContent = item.variant ?? '';
            const image = node.querySelector('[data-review-item-image]');
            if (item.image) {
                image.src = item.image;
            } else {
                image.remove();
            }
            return node;
        }));
        target();
    });

    list.addEventListener('change', target);
    stars.forEach((star) => {
        star.addEventListener('mouseenter', () => paintStars(Number(star.dataset.star)));
        star.querySelector('input').addEventListener('change', () => paintStars(Number(star.dataset.star)));
    });
    modal.querySelector('[data-review-stars]')?.addEventListener('mouseleave', () => paintStars(Number(form.querySelector('input[name="rating"]:checked')?.value ?? 0)));

    form.addEventListener('ajax:success', () => {
        window.setTimeout(() => window.location.reload(), 600);
    });

    /* "Write a review" link of the order-delivered e-mail (?review=1): open the first reviewable item. */
    if (new URLSearchParams(window.location.search).get('review') === '1') {
        document.querySelector('[data-review]')?.click();
    }
}

/* ---------- Reorder ---------- */
async function reorder(button) {
    const items = json(button.dataset.reorder, []);
    const url = context().routes.cart_items;

    if (!url || !items.length) {
        return;
    }

    setBusy(button, true);
    const failed = [];
    let added = 0;

    for (const item of items) {
        const payload = { store_product_id: Number(item.store_product_id), quantity: Number(item.quantity) || 1 };
        if (item.variant_id) {
            payload.variant_id = Number(item.variant_id);
        }

        try {
            const response = await http.post(url, payload, { toast: false, silent: true });
            added += 1;
            document.dispatchEvent(new CustomEvent('sf:cart-updated', { detail: { cart: response?.data ?? {}, product: payload, source: button } }));
        } catch {
            failed.push(item.name);
        }
    }

    setBusy(button, false);
    const texts = document.querySelector('template[data-i18n-reorder]')?.content;
    const text = (key) => texts?.querySelector(`[data-text="${key}"]`)?.textContent ?? '';

    if (!added) {
        notify.error(text('failed'));
        return;
    }

    if (failed.length) {
        notify.warning(text('partial').replace(':items', failed.join(', ')));
    }

    const cart = context().routes.cart;
    if (cart) {
        window.setTimeout(() => window.location.assign(cart), failed.length ? 1500 : 300);
    } else {
        notify.success(text('done'));
    }
}

/* ---------- Details ⇄ tracking ---------- */
function bindOrderTabs(root) {
    root.querySelectorAll('[data-order-tabs]').forEach((tabs) => {
        const panels = document.querySelector('[data-order-panels]');

        tabs.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-order-tab]');

            if (!tab || !panels || event.metaKey || event.ctrlKey) {
                return;
            }

            event.preventDefault();
            const panel = tab.dataset.orderTab;
            tabs.querySelectorAll('[data-order-tab]').forEach((other) => {
                const on = other === tab;
                other.setAttribute('aria-selected', String(on));
                other.toggleAttribute('aria-current', on);
                if (on) {
                    other.setAttribute('aria-current', 'page');
                }
            });

            const swap = () => {
                panels.dataset.panel = panel;
            };
            if (document.startViewTransition && !prefersReducedMotion()) {
                document.startViewTransition(swap);
            } else {
                swap();
            }
            history.replaceState(null, '', tab.href);

            if (panel === 'tracking') {
                document.getElementById('tracking')?.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
            }
        });
    });
}

/* ---------- In-page list filters (returns chips, wishlist search) ---------- */
function applyFilter(list, test) {
    const items = [...list.querySelectorAll('[data-filter-item]')];
    let visible = 0;

    items.forEach((item) => {
        const show = test(item);
        item.hidden = !show;
        visible += show ? 1 : 0;
    });

    const empty = list.parentElement.querySelector('[data-filter-empty]');
    if (empty) {
        empty.hidden = visible > 0 || !items.length;
    }

    return empty;
}

function bindFilters(root) {
    root.querySelectorAll('[data-filter-tabs]').forEach((tabs) => {
        const list = document.querySelector(`[data-filter-list="${tabs.dataset.filterTabs}"]`);

        tabs.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-filter]');

            if (!tab || !list) {
                return;
            }

            tabs.querySelectorAll('[data-filter]').forEach((other) => other.setAttribute('aria-selected', String(other === tab)));
            const group = tab.dataset.filter;
            applyFilter(list, (item) => group === 'all' || item.dataset.filterItem === group);
        });
    });

    root.querySelectorAll('[data-filter-search]').forEach((form) => {
        const list = document.querySelector(`[data-filter-list="${form.dataset.filterSearch}"]`);
        const input = form.querySelector('input[type="search"]');

        input?.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            const empty = applyFilter(list, (item) => !term || (item.dataset.filterText ?? '').includes(term));
            const title = empty?.querySelector('p');
            if (title && empty.dataset.filterTermTitle) {
                title.textContent = empty.dataset.filterTermTitle.replace(':term', input.value.trim());
            }
        });
        form.addEventListener('submit', (event) => event.preventDefault());
    });
}

/* ---------- Wishlist remove ---------- */
async function removeFavorite(button) {
    const url = context().routes.favorite_toggle;
    const card = button.closest('[data-wishlist-card]');

    if (!url || !card) {
        return;
    }

    setBusy(button, true);

    try {
        const response = await http.post(url, { store_product_id: Number(button.dataset.wishlistRemove) });

        if (response?.data?.favorited) {
            /* It was not saved any more and got re-added: undo that. */
            await http.post(url, { store_product_id: Number(button.dataset.wishlistRemove) }, { toast: false });
        }

        document.dispatchEvent(new CustomEvent('sf:favorites-updated', { detail: response?.data ?? {} }));
        card.setAttribute('data-removing', '');
        window.setTimeout(() => {
            const list = card.parentElement;
            card.remove();
            if (!list.querySelector('[data-filter-item]')) {
                document.querySelector('[data-filter-search="wishlist"]')?.remove();
                list.parentElement.querySelector('[data-filter-empty]')?.setAttribute('hidden', '');
                document.querySelector('[data-wishlist-empty]')?.removeAttribute('hidden');
            }
        }, prefersReducedMotion() ? 0 : 300);
    } catch {
        setBusy(button, false);
    }
}

export function initOrders(root = document) {
    if (!document.__sfOrders) {
        document.__sfOrders = true;
        bindCancel();
        bindReview();

        document.addEventListener('click', (event) => {
            const again = event.target.closest('[data-reorder]');
            const remove = event.target.closest('[data-wishlist-remove]');
            const print = event.target.closest('[data-print-invoice]');

            if (again) {
                reorder(again);
            } else if (remove) {
                removeFavorite(remove);
            } else if (print) {
                window.print();
            }
        });
    }

    bindOrderTabs(root);
    bindFilters(root);
}
