/**
 * Legacy generic Swiper hook (<div class="swiper" data-swiper='{…}'>), now loaded
 * lazily: pages without one never download Swiper. New sections use the motion
 * modules (data-rail / data-hero / data-spotlight …, see ./motion/index.js).
 */
export function initSliders(root = document) {
    const elements = [...root.querySelectorAll('[data-swiper]:not(.swiper-initialized)')];

    if (!elements.length) {
        return;
    }

    import('./motion/swiper').then(({ Swiper, baseOptions }) => {
        elements.forEach((element) => {
            let settings = {};

            try {
                settings = JSON.parse(element.dataset.swiper || '{}');
            } catch {
                settings = {};
            }

            new Swiper(element, { ...baseOptions(element), spaceBetween: 16, slidesPerView: 1.2, ...settings });
        });
    });
}
