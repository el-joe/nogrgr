/**
 * Subtle parallax on hero / banner imagery: [data-parallax="0.08"] (max 8% of the
 * element height). Only runs while visible; off with reduced motion, data saver and
 * low-end devices.
 */
import { frame, isLowEnd, prefersReducedMotion, watchVisibility } from './utils';

export function initParallax(root = document) {
    if (prefersReducedMotion() || isLowEnd()) {
        return;
    }

    root.querySelectorAll('[data-parallax]:not([data-parallax-ready])').forEach((element) => {
        element.dataset.parallaxReady = '';
        const strength = Math.min(0.08, Math.abs(Number(element.dataset.parallax) || 0.08));
        const host = element.parentElement;
        let active = false;

        const update = frame(() => {
            if (!active) {
                return;
            }

            const rect = host.getBoundingClientRect();
            const progress = (rect.top + rect.height / 2 - window.innerHeight / 2) / (window.innerHeight / 2 + rect.height / 2);
            element.style.transform = `translate3d(0, ${(-progress * strength * rect.height).toFixed(1)}px, 0) scale(${1 + strength * 2})`;
        });

        watchVisibility(host, (visible) => {
            active = visible;
            update();
        });
        window.addEventListener('scroll', update, { passive: true });
    });
}
