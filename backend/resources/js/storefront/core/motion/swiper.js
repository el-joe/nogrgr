/**
 * Swiper loader + shared defaults for every storefront rail. Imported lazily by
 * motion/index.js only when the page has a rail, so pages without one never
 * download Swiper.
 *
 * Defaults (docs/plan/07-motion-guidelines.md §2): touch swipe, desktop mouse drag
 * with grab cursor, trackpad / Shift+wheel horizontal scrolling that never takes
 * over vertical page scroll, ← → keys while focus is inside the rail (several rails
 * on screen never move together), RTL from the dir
 * attribute, clicks suppressed after a drag, resistance at the ends.
 */
import Swiper from 'swiper';
import { A11y, Autoplay, EffectFade, FreeMode, Keyboard, Mousewheel, Navigation, Pagination, Thumbs, Zoom } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/free-mode';
import 'swiper/css/effect-fade';
import 'swiper/css/zoom';
import 'swiper/css/thumbs';
import { prefersReducedMotion, token } from './utils';
import { t } from '../../../core/i18n';

export { Swiper };

export const modules = { A11y, Autoplay, EffectFade, FreeMode, Keyboard, Mousewheel, Navigation, Pagination, Thumbs, Zoom };

export function baseOptions(element, extra = {}) {
    const scope = element.closest('[data-motion-root]') ?? document.documentElement;
    const nav = (selector) => element.querySelector(selector) ?? element.parentElement?.querySelector(selector) ?? null;

    return {
        modules: [A11y, Keyboard, Mousewheel, Navigation, Pagination],
        speed: prefersReducedMotion() ? 0 : token('--motion-slow', scope, 600),
        grabCursor: true,
        simulateTouch: true,
        threshold: 6,
        touchAngle: 35,
        preventClicks: true,
        preventClicksPropagation: true,
        resistanceRatio: 0.6,
        watchOverflow: true,
        mousewheel: { forceToAxis: true, sensitivity: 0.8, releaseOnEdges: true, thresholdDelta: 8 },
        keyboard: { enabled: false },
        /* Screen-reader messages from the #app-i18n island (motion.* keys, en/ar). */
        a11y: {
            enabled: true,
            prevSlideMessage: t('motion.prev_slide'),
            nextSlideMessage: t('motion.next_slide'),
            firstSlideMessage: t('motion.first_slide'),
            lastSlideMessage: t('motion.last_slide'),
            paginationBulletMessage: t('motion.go_to_slide'),
            slideLabelMessage: t('motion.slide_label'),
            containerRoleDescriptionMessage: t('motion.carousel'),
            itemRoleDescriptionMessage: t('motion.slide'),
        },
        navigation: { prevEl: nav('[data-motion-prev]'), nextEl: nav('[data-motion-next]') },
        pagination: nav('[data-motion-pagination]') ? { el: nav('[data-motion-pagination]'), clickable: true } : false,
        ...extra,
    };
}

/** Lazy-load slide images that sit inside the viewport of a rail (no Swiper lazy module needed). */
export function eagerVisible(swiper) {
    swiper.slides.forEach((slide) => {
        if (slide.classList.contains('swiper-slide-visible') || slide.classList.contains('swiper-slide-active')) {
            slide.querySelectorAll('img[loading="lazy"]').forEach((image) => (image.loading = 'eager'));
        }
    });
}

/**
 * ← → / Home / End while focus is inside this swiper (mirrored in RTL by Swiper's own
 * slidePrev/slideNext). Swiper's Keyboard module listens on the document, which would
 * move every rail in view at once.
 */
export function bindKeys(swiper) {
    swiper.el.addEventListener('keydown', (event) => {
        if (event.target.closest('input, textarea, select, [contenteditable], [role="tablist"]') || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const rtl = swiper.rtlTranslate;
        const action = {
            ArrowRight: rtl ? 'slidePrev' : 'slideNext',
            ArrowLeft: rtl ? 'slideNext' : 'slidePrev',
            Home: 'first',
            End: 'last',
        }[event.key];

        if (!action) {
            return;
        }

        event.preventDefault();

        if (action === 'first') {
            swiper.params.loop ? swiper.slideToLoop(0) : swiper.slideTo(0);
        } else if (action === 'last') {
            swiper.slideTo(swiper.slides.length - 1);
        } else {
            swiper[action]();
        }
    });

    return swiper;
}
