/**
 * Modal / Bottom sheet / Drawer (x-ui::modal, x-ui::drawer, x-ui::confirm).
 *
 *   <button data-overlay-open="product-modal">…</button>
 *   <div class="ui-overlay" id="product-modal" data-overlay [data-sheet|data-drawer]>…</div>
 *   [data-overlay-close] inside closes it; Esc and scrim click close it too.
 *
 * Focus is trapped while open and restored afterwards. When the overlay holds a
 * form the user has edited, closing asks first (data-dirty-confirm). Under 640px
 * modals with data-sheet render as a bottom sheet that can be dragged down to
 * close (> 30% of its height or a flick faster than 0.5 px/ms).
 */
import { t } from '../../core/i18n';
import { confirmDialog } from './confirm';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
const stack = [];

const focusables = (overlay) =>
    [...overlay.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null || el === document.activeElement);

function lockScroll(lock) {
    document.documentElement.style.overflow = lock ? 'hidden' : '';
}

function isDirty(overlay) {
    return overlay.querySelector('form[data-dirty]') !== null;
}

export function openOverlay(target) {
    const overlay = typeof target === 'string' ? document.getElementById(target) : target;

    if (!overlay || overlay.dataset.open !== undefined) {
        return overlay;
    }

    overlay.__returnFocus = document.activeElement;
    overlay.dataset.open = '';
    overlay.removeAttribute('aria-hidden');
    overlay.removeAttribute('inert');
    stack.push(overlay);
    lockScroll(true);

    const initial = overlay.querySelector('[data-autofocus]') ?? focusables(overlay.querySelector('.ui-overlay-panel') ?? overlay)[0];
    requestAnimationFrame(() => initial?.focus({ preventScroll: true }));
    overlay.dispatchEvent(new CustomEvent('overlay:open', { bubbles: true }));

    return overlay;
}

export async function closeOverlay(target, { force = false } = {}) {
    const overlay = typeof target === 'string' ? document.getElementById(target) : target;

    if (!overlay || overlay.dataset.open === undefined) {
        return;
    }

    if (!force && isDirty(overlay)) {
        const leave = await confirmDialog({
            title: t('ui.discard_title'),
            message: t('ui.discard_message'),
            confirmLabel: t('ui.discard'),
            tone: 'danger',
        });

        if (!leave) {
            return;
        }

        overlay.querySelectorAll('form[data-dirty]').forEach((form) => delete form.dataset.dirty);
    }

    delete overlay.dataset.open;
    overlay.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('inert', '');
    overlay.style.removeProperty('--sheet-drag');
    stack.splice(stack.indexOf(overlay), 1);

    if (!stack.length) {
        lockScroll(false);
    }

    overlay.__returnFocus?.focus?.({ preventScroll: true });
    overlay.dispatchEvent(new CustomEvent('overlay:close', { bubbles: true }));
}

function trapFocus(event, overlay) {
    const items = focusables(overlay.querySelector('.ui-overlay-panel') ?? overlay);

    if (!items.length) {
        event.preventDefault();
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function bindSheetDrag(overlay) {
    const panel = overlay.querySelector('.ui-overlay-panel');
    const handle = overlay.querySelector('[data-sheet-handle]');

    if (!panel || !handle) {
        return;
    }

    let startY = 0;
    let startTime = 0;
    let delta = 0;
    let dragging = false;

    handle.addEventListener('pointerdown', (event) => {
        if (!window.matchMedia('(max-width: 639.98px)').matches) {
            return;
        }

        dragging = true;
        startY = event.clientY;
        startTime = performance.now();
        delta = 0;
        panel.style.transition = 'none';
        handle.setPointerCapture(event.pointerId);
    });

    handle.addEventListener('pointermove', (event) => {
        if (!dragging) {
            return;
        }

        delta = Math.max(0, event.clientY - startY);
        overlay.style.setProperty('--sheet-drag', `${delta}px`);
    });

    const end = () => {
        if (!dragging) {
            return;
        }

        dragging = false;
        panel.style.transition = '';
        const velocity = delta / Math.max(1, performance.now() - startTime);

        if (delta > panel.offsetHeight * 0.3 || velocity > 0.5) {
            closeOverlay(overlay, { force: true });
        } else {
            overlay.style.setProperty('--sheet-drag', '0px');
        }
    };

    handle.addEventListener('pointerup', end);
    handle.addEventListener('pointercancel', end);
}

let bound = false;

export function initOverlays(root = document) {
    root.querySelectorAll('[data-overlay]:not([data-overlay-ready])').forEach((overlay) => {
        overlay.dataset.overlayReady = '';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('inert', '');
        bindSheetDrag(overlay);
        overlay.querySelectorAll('form').forEach((form) => {
            form.addEventListener('input', () => (form.dataset.dirty = ''));
            form.addEventListener('ajax:success', () => {
                delete form.dataset.dirty;

                if (overlay.dataset.closeOnSuccess !== undefined) {
                    closeOverlay(overlay, { force: true });
                }
            });
        });
    });

    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener('click', (event) => {
        const opener = event.target.closest?.('[data-overlay-open]');

        if (opener) {
            event.preventDefault();
            openOverlay(opener.dataset.overlayOpen);
            return;
        }

        const closer = event.target.closest?.('[data-overlay-close]');

        if (closer) {
            event.preventDefault();
            closeOverlay(closer.closest('[data-overlay]'));
            return;
        }

        if (event.target.matches?.('.ui-overlay-scrim')) {
            const overlay = event.target.closest('[data-overlay]');

            if (overlay?.dataset.static === undefined) {
                closeOverlay(overlay);
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        const top = stack[stack.length - 1];

        if (!top) {
            return;
        }

        if (event.key === 'Escape' && top.dataset.static === undefined) {
            event.preventDefault();
            closeOverlay(top);
        } else if (event.key === 'Tab') {
            trapFocus(event, top);
        }
    });
}
