/**
 * Product card hover swap: on devices that can hover, the second image
 * ([data-hover-image], loading="lazy") cross-fades over the first (CSS
 * .mo-card-media). It is only requested on the first pointer approach, so cards never
 * cost an extra download until someone points at them.
 */
let bound = false;

export function initCardHover() {
    if (bound || !window.matchMedia('(hover: hover)').matches) {
        return;
    }

    bound = true;

    document.addEventListener(
        'pointerover',
        (event) => {
            const card = event.target.closest?.('[data-hover-swap]:not([data-hover-ready])');

            if (!card) {
                return;
            }

            card.dataset.hoverReady = '';
            const image = card.querySelector('img[data-hover-image]');

            if (image?.dataset.src) {
                image.src = image.dataset.src;
                image.addEventListener('load', () => card.setAttribute('data-hover-loaded', ''), { once: true });
            } else if (image) {
                card.setAttribute('data-hover-loaded', '');
            }
        },
        { passive: true },
    );
}
