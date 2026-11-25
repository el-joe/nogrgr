/**
 * Swiper rails for the central site (docs/plan/07-motion-guidelines.md §2): touch
 * swipe, desktop mouse drag (grab cursor), trackpad / Shift+wheel horizontal scroll
 * that never hijacks vertical scroll, ← → keys while focused, RTL from <html dir>,
 * clicks suppressed after a drag, rubber-band ends.
 *
 *   [data-rail]                 free-mode rail; data-rail-until="1024" = only below that
 *                               width (desktop shows the Figma grid); data-rail-centered
 *                               + data-rail-initial="1" for the centred plan/blog rails.
 *   [data-chips]                category chips rail + [data-chips-next] arrow.
 *   [data-reviews]              testimonials carousel, autoplay (paused on hover/focus/
 *                               touch/off-screen; off under reduced motion) + bar bullets.
 */
import Swiper from 'swiper';
import { A11y, Autoplay, FreeMode, Keyboard, Mousewheel, Navigation } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/free-mode';
import { t } from '../../core/i18n.js';

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function base(extra = {}) {
    return {
        modules: [A11y, Keyboard, Mousewheel, FreeMode],
        speed: reduced() ? 0 : 600,
        grabCursor: true,
        simulateTouch: true,
        threshold: 6,
        touchAngle: 35,
        preventClicks: true,
        preventClicksPropagation: true,
        resistanceRatio: 0.6,
        watchOverflow: true,
        slidesPerView: 'auto',
        mousewheel: { forceToAxis: true, sensitivity: 0.8, releaseOnEdges: true, thresholdDelta: 8 },
        keyboard: { enabled: true, onlyInViewport: true, pageUpDown: false },
        a11y: {
            enabled: true,
            prevSlideMessage: t('motion.prev_slide'),
            nextSlideMessage: t('motion.next_slide'),
            firstSlideMessage: t('motion.first_slide'),
            lastSlideMessage: t('motion.last_slide'),
            slideLabelMessage: t('motion.slide_label'),
            containerRoleDescriptionMessage: t('motion.carousel'),
            itemRoleDescriptionMessage: t('motion.slide'),
        },
        on: {
            touchStart: (swiper) => swiper.el.classList.add('is-dragging'),
            touchEnd: (swiper) => swiper.el.classList.remove('is-dragging'),
        },
        ...extra,
    };
}

export function initRails(root = document) {
    root.querySelectorAll('[data-rail]').forEach((el) => {
        const until = parseInt(el.dataset.railUntil ?? '0', 10);
        const centered = el.hasAttribute('data-rail-centered');
        const initial = parseInt(el.dataset.railInitial ?? '0', 10) || 0;
        let swiper = null;

        const create = () =>
            new Swiper(
                el,
                base({
                    spaceBetween: centered ? 16 : 12,
                    centeredSlides: centered,
                    centeredSlidesBounds: !centered,
                    initialSlide: initial,
                    slideToClickedSlide: centered,
                    freeMode: centered ? { enabled: false } : { enabled: true, sticky: true, momentumRatio: 0.6, momentumBounceRatio: 0.4 },
                }),
            );

        if (!until) {
            swiper = create();
            return;
        }

        const query = window.matchMedia(`(max-width: ${until - 0.02}px)`);
        const sync = () => {
            if (query.matches && !swiper) {
                swiper = create();
            } else if (!query.matches && swiper) {
                swiper.destroy(true, true);
                swiper = null;
            }
        };
        query.addEventListener('change', sync);
        sync();
    });
}

export function initChips(root = document) {
    root.querySelectorAll('[data-chips]').forEach((el) => {
        const next = el.parentElement?.querySelector('[data-chips-next]');
        const swiper = new Swiper(
            el,
            base({
                freeMode: { enabled: true, sticky: false, momentumRatio: 0.6 },
                spaceBetween: 0,
                keyboard: { enabled: false },
            }),
        );

        const active = el.querySelector('[aria-current="page"]')?.closest('.swiper-slide');
        if (active) {
            swiper.slideTo([...swiper.slides].indexOf(active), 0);
        }

        if (next) {
            const sync = () => {
                next.disabled = swiper.isEnd;
            };
            next.addEventListener('click', () => {
                const target = Math.min(swiper.activeIndex + 3, swiper.slides.length - 1);
                swiper.slideTo(target);
            });
            swiper.on('progress', sync);
            sync();
        }
    });
}

export function initReviews(root = document) {
    root.querySelectorAll('[data-reviews]').forEach((el) => {
        const wrap = el.parentElement;
        const pagination = wrap.querySelector('[data-reviews-pagination]');
        const autoplay = !reduced();

        const swiper = new Swiper(
            el,
            base({
                modules: [A11y, Keyboard, Mousewheel, Navigation, Autoplay],
                spaceBetween: 24,
                loop: el.querySelectorAll('.swiper-slide').length > 3,
                navigation: { prevEl: wrap.querySelector('[data-reviews-prev]'), nextEl: wrap.querySelector('[data-reviews-next]') },
                autoplay: autoplay ? { delay: 5000, disableOnInteraction: false, pauseOnMouseEnter: true } : false,
                breakpoints: { 1024: { centeredSlides: false } },
            }),
        );

        /* Figma "indecators": three 40×8 bars — one per page of slides. */
        if (pagination) {
            const pages = () => Math.max(1, Math.ceil(swiper.slides.length / Math.max(1, Math.round(swiper.width / 398))));
            const render = () => {
                const count = Math.min(pages(), 5);
                const current = Math.min(count - 1, Math.floor((swiper.realIndex / Math.max(1, swiper.slides.length)) * count));
                pagination.replaceChildren(
                    ...Array.from({ length: count }, (_, index) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.setAttribute('aria-label', t('motion.go_to_slide').replace('{{index}}', String(index + 1)));
                        button.setAttribute('aria-current', String(index === current));
                        button.addEventListener('click', () => swiper.slideToLoop(Math.round((index * swiper.slides.length) / count)));
                        return button;
                    }),
                );
            };
            swiper.on('slideChange', render);
            swiper.on('resize', render);
            render();
        }

        if (autoplay && swiper.autoplay) {
            el.addEventListener('focusin', () => swiper.autoplay.pause());
            el.addEventListener('focusout', () => swiper.autoplay.resume());
            el.addEventListener('touchstart', () => swiper.autoplay.pause(), { passive: true });
            new IntersectionObserver(([entry]) => (entry.isIntersecting ? swiper.autoplay.resume() : swiper.autoplay.pause())).observe(el);
        }
    });
}
