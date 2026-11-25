/**
 * Product gallery: main swiper + thumbnail strip kept in sync, pinch / double-tap /
 * double-click zoom (Swiper Zoom), keyboard arrows, RTL. Pull down on a zoomed-out
 * image in the full-screen viewer closes it (see sheet.js for the viewer dialog).
 *   <div data-gallery>
 *     <div class="swiper" data-gallery-main>…<div class="swiper-zoom-container"><img></div>…</div>
 *     <div class="swiper" data-gallery-thumbs>…</div>
 */
import { Swiper, modules, baseOptions, bindKeys } from './swiper';

export function initGallery(element) {
    const mainEl = element.querySelector('[data-gallery-main]');
    const thumbsEl = element.querySelector('[data-gallery-thumbs]');
    const base = baseOptions(mainEl);

    const thumbs = thumbsEl
        ? new Swiper(thumbsEl, {
              ...baseOptions(thumbsEl),
              modules: [modules.FreeMode, modules.A11y, modules.Mousewheel],
              slidesPerView: 'auto',
              spaceBetween: 8,
              freeMode: true,
              watchSlidesProgress: true,
              slideToClickedSlide: true,
          })
        : null;

    const main = new Swiper(mainEl, {
        ...base,
        modules: [...base.modules, modules.Zoom, modules.Thumbs],
        slidesPerView: 1,
        spaceBetween: 0,
        grabCursor: true,
        zoom: { maxRatio: 3, toggle: true, containerClass: 'swiper-zoom-container' },
        thumbs: thumbs ? { swiper: thumbs, slideThumbActiveClass: 'is-active' } : undefined,
        mousewheel: false,
        keyboard: { enabled: false },
    });

    bindKeys(main);

    /* Keyboard: + / - zoom the active image too. */
    mainEl.addEventListener('keydown', (event) => {
        if (event.key === '+' || event.key === '=') {
            main.zoom.in();
        } else if (event.key === '-') {
            main.zoom.out();
        }
    });

    return main;
}
