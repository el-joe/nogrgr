/**
 * Sticky header: shrink + deeper shadow once scrolled; on phones it hides while
 * scrolling down and comes back on scroll up (never while the menu is open).
 */
export function initHeader(root = document) {
    const header = root.querySelector('[data-site-header]');

    if (!header) {
        return;
    }

    let lastY = window.scrollY;
    let ticking = false;

    const update = () => {
        const y = window.scrollY;
        header.toggleAttribute('data-scrolled', y > 8);

        const menuOpen = header.querySelector('[aria-expanded="true"]');
        const goingDown = y > lastY && y > 120;
        header.toggleAttribute('data-hidden', goingDown && !menuOpen);

        lastY = y;
        ticking = false;
    };

    window.addEventListener(
        'scroll',
        () => {
            if (!ticking) {
                ticking = true;
                requestAnimationFrame(update);
            }
        },
        { passive: true },
    );

    header.addEventListener('focusin', () => header.removeAttribute('data-hidden'));
    update();
}
