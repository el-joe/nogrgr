/**
 * Sticky header: [data-sticky-header] gets data-scrolled (shrink + shadow) after 8px
 * and, below 1024px, data-hidden while scrolling down / removed when scrolling up.
 * Transform only (no layout shift). Never hides while focus is inside it.
 */
import { frame } from './utils';

export function initStickyHeader(root = document) {
    const header = root.querySelector('[data-sticky-header]:not([data-sticky-ready])');

    if (!header) {
        return;
    }

    header.dataset.stickyReady = '';
    const mobile = window.matchMedia('(max-width: 1023.98px)');
    let lastY = window.scrollY;

    const update = frame(() => {
        const y = window.scrollY;
        header.toggleAttribute('data-scrolled', y > 8);
        const goingDown = y > lastY + 4;
        const goingUp = y < lastY - 4;

        if (mobile.matches && goingDown && y > header.offsetHeight * 2 && !header.contains(document.activeElement)) {
            header.setAttribute('data-hidden', '');
        } else if (goingUp || !mobile.matches) {
            header.removeAttribute('data-hidden');
        }

        lastY = y;
    });

    window.addEventListener('scroll', update, { passive: true });
    header.addEventListener('focusin', () => header.removeAttribute('data-hidden'));
    update();
}
