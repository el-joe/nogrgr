/**
 * Storefront motion system (docs/plan/07-motion-guidelines.md). Shared by the three
 * theme families; sections opt in with data-* attributes and the per-family tokens
 * in resources/css/tokens/storefront-<family>.css tune timing and easing.
 *
 * Swiper-based modules (rail, hero, spotlight, tabs-swipe, gallery) are imported
 * only when the page has one, so pages without rails never load Swiper. Everything
 * else is small and always available. Budget: ≤ 25 KB gz without Swiper (checked by
 * the motion-budget plugin in vite.config.js).
 */
import { initStaggeredReveal } from './reveal';
import { initMarquee } from './marquee';
import { initCart } from './cart';
import { initCardHover } from './card-hover';
import { initSheets } from './sheet';
import { initSwipeRemove } from './swipe-remove';
import { initTransitions } from './transitions';
import { initCountdowns } from './countdown';
import { initStickyHeader } from './header';
import { initParallax } from './parallax';
import { initImages, initCounters } from './images';
import { prefersReducedMotion, watchVisibility } from './utils';
import { numberLocale } from '../../../core/i18n';

export { openSheet, closeSheet } from './sheet';

const SWIPERS = {
    '[data-rail]': ['initRail', () => import('./rail')],
    '[data-hero]': ['initHero', () => import('./hero')],
    '[data-spotlight]': ['initSpotlight', () => import('./spotlight')],
    '[data-tabs-swipe]': ['initTabsSwipe', () => import('./tabs-swipe')],
    '[data-gallery]': ['initGallery', () => import('./gallery')],
};

function initSwipers(root) {
    Object.entries(SWIPERS).forEach(([selector, [name, load]]) => {
        const elements = [...root.querySelectorAll(`${selector}:not([data-motion-ready])`)];

        if (!elements.length) {
            return;
        }

        elements.forEach((element) => (element.dataset.motionReady = ''));
        load().then((module) => elements.forEach((element) => module[name](element)));
    });
}

export function initMotion(root = document) {
    document.documentElement.classList.toggle('mo-reduced', prefersReducedMotion());
    initStickyHeader(root);
    initStaggeredReveal(root);
    initImages(root);
    initSwipers(root);
    root.querySelectorAll('[data-marquee]:not([data-motion-ready])').forEach((element) => {
        element.dataset.motionReady = '';
        initMarquee(element);
    });
    initCart();
    initCardHover();
    initSheets(root);
    initSwipeRemove(root);
    initCountdowns(root);
    initParallax(root);
    initCounters(root, { prefersReducedMotion, watchVisibility, numberLocale });
    initTransitions(root);
}
