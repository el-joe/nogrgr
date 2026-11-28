/**
 * Hero slider: <div class="swiper mo-hero" data-hero='{"delay":6000}'>.
 * Cross-fade with a Ken Burns zoom on the active image (CSS, .mo-kenburns), autoplay
 * with a progress bar ([data-hero-progress]) and a pause button ([data-motion-pause]).
 * Autoplay pauses on hover, focus, touch, when off-screen or hidden; no autoplay and
 * no zoom with reduced motion.
 */
import { Swiper, modules, baseOptions, bindKeys } from './swiper';
import { autoPause, options, prefersReducedMotion, isLowEnd } from './utils';

export function initHero(element) {
    const settings = options(element, 'hero', { delay: 6000, effect: 'fade' });
    const base = baseOptions(element);
    const progress = element.querySelector('[data-hero-progress]');
    const reduce = prefersReducedMotion();

    if (!reduce && !isLowEnd()) {
        element.classList.add('mo-hero-kenburns');
    }

    const swiper = new Swiper(element, {
        ...base,
        modules: [...base.modules, modules.Autoplay, modules.EffectFade],
        effect: settings.effect,
        fadeEffect: { crossFade: true },
        loop: true,
        slidesPerView: 1,
        autoplay: reduce ? false : { delay: settings.delay, disableOnInteraction: false, pauseOnMouseEnter: false },
        /* Per-section extras, e.g. slidesPerView "auto" for a peeking next slide. */
        ...(settings.swiper ?? {}),
        on: {
            autoplayTimeLeft(instance, time, ratio) {
                progress?.style.setProperty('--progress', String(1 - ratio));
            },
        },
    });

    if (!reduce) {
        autoPause(element, {
            play: () => {
                if (swiper.autoplay.paused) {
                    swiper.autoplay.resume();
                } else if (!swiper.autoplay.running) {
                    swiper.autoplay.start();
                }
            },
            pause: () => swiper.autoplay.running && !swiper.autoplay.paused && swiper.autoplay.pause(),
        });
    }

    return bindKeys(swiper);
}
