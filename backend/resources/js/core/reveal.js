/**
 * Scroll reveal: <section data-reveal="fade-up" data-reveal-delay="100">.
 * Respects prefers-reduced-motion (elements are shown immediately).
 */
export function initReveal(root = document) {
    const elements = root.querySelectorAll('[data-reveal]:not(.is-revealed)');

    if (!elements.length) {
        return;
    }

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce || !('IntersectionObserver' in window)) {
        elements.forEach((el) => el.classList.add('is-revealed'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const delay = entry.target.dataset.revealDelay;
                    if (delay) {
                        entry.target.style.setProperty('--reveal-delay', `${parseInt(delay, 10)}ms`);
                    }
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
    );

    elements.forEach((el) => observer.observe(el));
}
