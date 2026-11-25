/**
 * Scroll reveal with stagger:
 *   <section data-reveal="fade-up">                         fade-up | fade | zoom | slide-start
 *   <div data-reveal-stagger="60"><article data-reveal="fade-up">…</article>…</div>
 * Children of a [data-reveal-stagger] container get --reveal-delay = index × stagger
 * (default --motion-stagger), capped so long grids never wait more than 600ms. Each
 * element animates once; reduced motion shows everything immediately
 * (resources/js/core/reveal.js does the observing).
 */
import { initReveal } from '../../../core/reveal';
import { token } from './utils';

export function initStaggeredReveal(root = document) {
    root.querySelectorAll('[data-reveal-stagger]').forEach((container) => {
        const step = Number(container.dataset.revealStagger) || token('--motion-stagger', container, 60);
        [...container.querySelectorAll(':scope > [data-reveal], :scope > * > [data-reveal]')].forEach((child, index) => {
            if (!child.dataset.revealDelay) {
                child.dataset.revealDelay = String(Math.min(index * step, 600));
            }
        });
    });

    initReveal(root);
}
