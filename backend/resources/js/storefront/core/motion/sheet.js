/**
 * Storefront bottom sheets (address, filters, sort, mini cart, image viewer) with
 * drag-to-dismiss; a centred dialog from 640px up unless data-sheet-mode="always".
 *   <button data-sheet-open="filters">…</button>
 *   <div class="mo-sheet" id="filters" data-sheet role="dialog" aria-modal="true" aria-labelledby="…" hidden>
 *     <div class="mo-sheet-scrim" data-sheet-close></div>
 *     <div class="mo-sheet-panel"><div class="mo-sheet-grabber" data-sheet-handle></div>…<button data-sheet-close>…</div>
 *   </div>
 * Drag the grabber/header down: closes past 30% of the height or on a flick faster
 * than 0.5 px/ms, otherwise springs back. Esc and the scrim close it; focus is trapped
 * and returned to the opener.
 */
import { prefersReducedMotion, token, capture } from './utils';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
const open = [];

function focusables(sheet) {
    return [...sheet.querySelectorAll(FOCUSABLE)].filter((node) => node.offsetParent !== null);
}

export function openSheet(target) {
    const sheet = typeof target === 'string' ? document.getElementById(target) : target;

    if (!sheet || sheet.dataset.open !== undefined) {
        return;
    }

    sheet.__opener = document.activeElement;
    sheet.__opener?.hasAttribute?.('aria-expanded') && sheet.__opener.setAttribute('aria-expanded', 'true');
    sheet.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    open.push(sheet);
    requestAnimationFrame(() => {
        sheet.dataset.open = '';
        // Prefer the first field over the header close button so typing goes straight in.
        const items = focusables(sheet.querySelector('.mo-sheet-panel'));
        (sheet.querySelector('[data-autofocus]') ?? items.find((node) => !node.matches('[data-sheet-close]')) ?? items[0])?.focus({ preventScroll: true });
    });
}

export function closeSheet(target) {
    const sheet = typeof target === 'string' ? document.getElementById(target) : target;

    if (!sheet || sheet.dataset.open === undefined) {
        return;
    }

    delete sheet.dataset.open;
    sheet.__opener?.hasAttribute?.('aria-expanded') && sheet.__opener.setAttribute('aria-expanded', 'false');
    sheet.style.removeProperty('--sheet-drag');
    open.splice(open.indexOf(sheet), 1);
    const done = () => {
        sheet.hidden = true;

        if (!open.length) {
            document.documentElement.style.overflow = '';
        }
    };

    prefersReducedMotion() ? done() : setTimeout(done, token('--motion-base', sheet, 280));
    sheet.__opener?.focus?.({ preventScroll: true });
}

function bindDrag(sheet) {
    const panel = sheet.querySelector('.mo-sheet-panel');

    sheet.querySelectorAll('[data-sheet-handle]').forEach((handle) => {
        let start = null;

        handle.addEventListener('pointerdown', (event) => {
            /* Never capture a press on a control inside the header: pointer capture would
               retarget its click to the handle and the close (X) button would do nothing. */
            if (event.button !== 0 || event.target.closest('button, a, input, select, textarea, label, [data-sheet-close]')) {
                return;
            }

            start = { y: event.clientY, time: performance.now(), delta: 0 };
            panel.style.transition = 'none';
            capture(handle, event.pointerId);
        });

        handle.addEventListener('pointermove', (event) => {
            if (!start) {
                return;
            }

            start.delta = Math.max(0, event.clientY - start.y);
            sheet.style.setProperty('--sheet-drag', `${start.delta}px`);
        });

        const end = () => {
            if (!start) {
                return;
            }

            panel.style.transition = '';
            const velocity = start.delta / Math.max(1, performance.now() - start.time);

            if (start.delta > panel.offsetHeight * 0.3 || velocity > 0.5) {
                closeSheet(sheet);
            } else {
                sheet.style.setProperty('--sheet-drag', '0px');
            }

            start = null;
        };

        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
    });
}

let bound = false;

export function initSheets(root = document) {
    root.querySelectorAll('[data-sheet]:not([data-sheet-ready])').forEach((sheet) => {
        sheet.dataset.sheetReady = '';
        bindDrag(sheet);
    });

    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-sheet-open]');

        if (opener) {
            event.preventDefault();
            openSheet(opener.dataset.sheetOpen);
            return;
        }

        const closer = event.target.closest('[data-sheet-close]');

        if (closer) {
            event.preventDefault();
            closeSheet(closer.closest('[data-sheet]'));
        }
    });

    document.addEventListener('keydown', (event) => {
        const top = open[open.length - 1];

        if (!top) {
            return;
        }

        if (event.key === 'Escape') {
            // An open Select2 dropdown inside the sheet closes first (checked in the capture
            // phase, before Select2 handles the key).
            if (document.querySelector('.select2-container--open')) {
                return;
            }

            closeSheet(top);
        } else if (event.key === 'Tab') {
            const items = focusables(top.querySelector('.mo-sheet-panel'));
            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    }, true);
}
