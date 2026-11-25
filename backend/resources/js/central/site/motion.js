/**
 * Count-up numbers (data-count-to) once in view and subtle parallax ([data-parallax],
 * ≤ 8% travel) — both skipped under prefers-reduced-motion.
 */
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function initCountUp(root = document) {
    const items = root.querySelectorAll('[data-count-to]');

    if (!items.length || reduced() || !('IntersectionObserver' in window)) {
        return;
    }

    const formatter = new Intl.NumberFormat(document.documentElement.lang || 'en', { numberingSystem: 'latn' });
    items.forEach((el) => {
        el.textContent = formatter.format(0);
    });

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                observer.unobserve(entry.target);
                const target = parseInt(entry.target.dataset.countTo, 10) || 0;
                const duration = 1400;
                const start = performance.now();
                const tick = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    entry.target.textContent = formatter.format(Math.round(target * eased));

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    }
                };
                requestAnimationFrame(tick);
            });
        },
        { threshold: 0.4 },
    );

    items.forEach((el) => observer.observe(el));
}

export function initParallax(root = document) {
    const items = [...root.querySelectorAll('[data-parallax]')];

    if (!items.length || reduced() || window.matchMedia('(max-width: 1023px)').matches || (navigator.hardwareConcurrency ?? 8) < 4) {
        return;
    }

    let ticking = false;
    const update = () => {
        const vh = window.innerHeight;
        items.forEach((el) => {
            const rect = el.getBoundingClientRect();

            if (rect.bottom < 0 || rect.top > vh) {
                return;
            }

            const progress = (rect.top + rect.height / 2 - vh / 2) / vh;
            el.style.translate = `0 ${(progress * -0.08 * rect.height).toFixed(1)}px`;
        });
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
    update();
}
