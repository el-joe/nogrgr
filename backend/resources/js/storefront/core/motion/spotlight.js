/**
 * Spotlight carousel (old-system-docs/home-pages-custom-sliders.md), used only where
 * Figma shows the custom card-fan / coverflow slider (e.g. Elora 1:30232).
 *
 *   <div class="swiper mo-spotlight" data-spotlight='{"layout":"center"}'>   layout: center | left
 *
 * Every frame (also while dragging) each card's transform is computed from its
 * continuous distance to the active position: scale, translateX (cards tuck behind
 * the spotlight), translateZ depth, opacity and z-index — transform/opacity only,
 * no layout work. Swiper still provides drag, wheel, keyboard, loop and RTL.
 */
import { Swiper, modules, baseOptions, bindKeys } from './swiper';
import { options, prefersReducedMotion } from './utils';

const DEFAULTS = {
    layout: 'center',
    loop: true,
    maxScale: 1,
    minScale: 0.62,
    step: 0.16,
    overlap: 0.22,
    depth: 120,
    fade: 0.18,
    perspective: 1200,
};

function render(swiper, config) {
    const rtl = swiper.rtlTranslate ? -1 : 1;

    swiper.slides.forEach((slide) => {
        const progress = slide.progress;
        /* Left layout: only cards after the spotlight shrink; cards before it fade out. */
        const distance = config.layout === 'left' ? Math.max(progress * -1, 0) + Math.max(progress, 0) * 3 : Math.abs(progress);
        const scale = Math.max(config.minScale, config.maxScale - distance * config.step);
        /* Swiper progress: 0 = active, negative = after it, positive = before it. Cards tuck toward the spotlight. */
        const tuck = Math.sign(progress) * rtl * Math.min(distance, 4) * config.overlap * 100;
        const opacity = config.layout === 'left' && progress > 0 ? Math.max(0, 1 - progress * 1.5) : Math.max(0.25, 1 - Math.max(distance - 1, 0) * config.fade);
        const card = slide.firstElementChild ?? slide;

        card.style.transform = `translate3d(${tuck}%, 0, ${-distance * config.depth}px) scale(${scale})`;
        card.style.opacity = String(opacity);
        slide.style.zIndex = String(100 - Math.round(distance * 10));
        slide.dataset.spotlight = distance < 0.5 ? 'active' : distance < 1.5 ? 'near' : 'far';
    });
}

export function initSpotlight(element) {
    const config = options(element, 'spotlight', DEFAULTS);
    const base = baseOptions(element);
    const reduce = prefersReducedMotion();

    element.classList.add(`mo-spotlight-${config.layout === 'left' ? 'left' : 'center'}`);
    element.style.setProperty('--spotlight-perspective', `${config.perspective}px`);

    const swiper = new Swiper(element, {
        ...base,
        modules: base.modules,
        loop: config.loop,
        centeredSlides: config.layout !== 'left',
        slidesPerView: 'auto',
        spaceBetween: 0,
        slideToClickedSlide: true,
        watchSlidesProgress: true,
        virtualTranslate: false,
        speed: reduce ? 0 : base.speed,
        on: {
            progress: (instance) => render(instance, config),
            setTranslate: (instance) => render(instance, config),
            init: (instance) => render(instance, config),
            resize: (instance) => render(instance, config),
            setTransition(instance, duration) {
                instance.slides.forEach((slide) => {
                    const card = slide.firstElementChild ?? slide;
                    card.style.transitionDuration = `${duration}ms`;
                });
            },
        },
    });

    return bindKeys(swiper);
}
