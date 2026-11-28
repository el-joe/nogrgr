/**
 * Elora account pages (css/storefront/elora-account.css). The swipeable tab strips
 * ([data-elo-drag]) scroll by touch natively; here they also drag with the mouse and bring
 * the current tab into view (RTL included). Everything else on these pages uses the shared
 * account modules (core/account/*) through their data-* hooks.
 */
import { prefersReducedMotion } from '../../core/motion/utils';

function bindStrip(strip) {
    const current = strip.querySelector('[aria-current]');

    if (current && strip.scrollWidth > strip.clientWidth) {
        const box = strip.getBoundingClientRect();
        const tab = current.getBoundingClientRect();
        strip.scrollBy({ left: tab.left + tab.width / 2 - (box.left + box.width / 2), behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    }

    let start = null;
    let moved = false;

    strip.addEventListener('pointerdown', (event) => {
        moved = false;

        if (event.pointerType === 'mouse' && event.button === 0) {
            start = { x: event.clientX, scroll: strip.scrollLeft };
        }
    });

    window.addEventListener('pointermove', (event) => {
        if (!start) {
            return;
        }

        const delta = event.clientX - start.x;
        moved ||= Math.abs(delta) > 4;
        strip.scrollLeft = start.scroll - delta;
    });

    window.addEventListener('pointerup', () => {
        start = null;
    });

    /* A drag must not follow the link it started on. */
    strip.addEventListener('click', (event) => {
        if (moved) {
            event.preventDefault();
            moved = false;
        }
    }, true);
    strip.addEventListener('dragstart', (event) => event.preventDefault());
}

export function initEloraAccount(root = document) {
    root.querySelectorAll('[data-elo-drag]:not([data-ready])').forEach((strip) => {
        strip.dataset.ready = '';
        bindStrip(strip);
    });
}
