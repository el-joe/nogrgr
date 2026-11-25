/**
 * Swipe-to-remove list rows (cart lines, saved items):
 *   <li data-swipe-remove data-remove-label="Wireless Earbuds">…<button data-swipe-remove-button>Remove</button></li>
 * Swipe toward the inline-start edge (left in LTR, right in RTL) past 40% of the row
 * (or a fast flick) to remove; the remove button does the same for mouse and
 * keyboard users. The row collapses and an undo toast appears for 6s. Undo restores
 * the row in place; otherwise `swipe-remove:commit` fires (detail: {row}) so the page
 * can call the server. Vertical scrolling is never captured.
 */
import { toast, dismissToast } from '../../../core/notify';
import { t } from '../../../core/i18n';
import { isRtl, prefersReducedMotion, token, capture } from './utils';

const UNDO_MS = 6000;

function collapse(row) {
    const height = row.offsetHeight;
    row.style.height = `${height}px`;
    row.dataset.removing = '';
    requestAnimationFrame(() => {
        row.style.height = '0px';
    });
}

function remove(row) {
    if (row.dataset.removing !== undefined) {
        return;
    }

    const label = row.dataset.removeLabel || '';
    collapse(row);

    let undone = false;
    const notice = toast('info', t('motion.removed', { name: label }), '', {
        timeout: UNDO_MS,
        action: {
            label: t('motion.undo'),
            onClick: () => {
                undone = true;
                dismissToast(notice);
                row.style.height = `${row.scrollHeight}px`;
                delete row.dataset.removing;
                row.style.setProperty('--swipe-x', '0px');
                setTimeout(() => row.style.removeProperty('height'), token('--motion-base', row, 280));
                row.querySelector('[data-swipe-remove-button]')?.focus();
            },
        },
    });

    setTimeout(() => {
        if (!undone) {
            row.dispatchEvent(new CustomEvent('swipe-remove:commit', { bubbles: true, detail: { row } }));
            row.hidden = true;
        }
    }, UNDO_MS + 50);
}

function bind(row) {
    let start = null;
    const content = row.querySelector('[data-swipe-content]') ?? row;

    row.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || event.target.closest('button, a, input, select')) {
            return;
        }

        start = { x: event.clientX, y: event.clientY, time: performance.now(), axis: null, dx: 0, id: event.pointerId };
    });

    row.addEventListener('pointermove', (event) => {
        if (!start) {
            return;
        }

        const dx = event.clientX - start.x;
        const dy = event.clientY - start.y;
        start.axis ??= Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy) ? 'x' : Math.abs(dy) > 8 ? 'y' : null;

        if (start.axis === 'y') {
            start = null;
            return;
        }

        if (start.axis === 'x') {
            capture(row, start.id);
            const towardStart = isRtl(row) ? Math.max(0, dx) : Math.min(0, dx);
            start.dx = towardStart;
            content.style.transition = 'none';
            row.style.setProperty('--swipe-x', `${towardStart}px`);
            row.dataset.swiping = '';
        }
    });

    const end = () => {
        if (!start) {
            return;
        }

        content.style.transition = '';
        delete row.dataset.swiping;
        const distance = Math.abs(start.dx);
        const velocity = distance / Math.max(1, performance.now() - start.time);

        if (distance > row.offsetWidth * 0.4 || (velocity > 0.6 && distance > 40)) {
            row.style.setProperty('--swipe-x', `${isRtl(row) ? '' : '-'}${row.offsetWidth}px`);
            setTimeout(() => remove(row), prefersReducedMotion() ? 0 : 120);
        } else {
            row.style.setProperty('--swipe-x', '0px');
        }

        start = null;
    };

    row.addEventListener('pointerup', end);
    row.addEventListener('pointercancel', end);
    row.querySelector('[data-swipe-remove-button]')?.addEventListener('click', (event) => {
        event.preventDefault();
        remove(row);
    });
}

export function initSwipeRemove(root = document) {
    root.querySelectorAll('[data-swipe-remove]:not([data-swipe-ready])').forEach((row) => {
        row.dataset.swipeReady = '';
        bind(row);
    });
}
