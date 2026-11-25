/**
 * Product / category rail: <div class="swiper" data-rail='{"slidesPerView":"auto"}'>.
 * Free-mode momentum with snap, rubber-band ends, prev/next ([data-motion-prev|next])
 * and an optional progress bar ([data-motion-pagination]).
 */
import { Swiper, modules, baseOptions, bindKeys, eagerVisible } from './swiper';
import { options } from './utils';

export function initRail(element) {
    const settings = options(element, 'rail');
    const base = baseOptions(element);

    return bindKeys(new Swiper(element, {
        ...base,
        modules: [...base.modules, modules.FreeMode],
        slidesPerView: 'auto',
        spaceBetween: 16,
        freeMode: { enabled: true, sticky: true, momentumRatio: 0.6, momentumBounceRatio: 0.4 },
        watchSlidesProgress: true,
        pagination: base.pagination ? { ...base.pagination, type: 'progressbar' } : false,
        on: { init: eagerVisible, slideChange: eagerVisible },
        ...settings,
    }));
}
