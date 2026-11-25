/**
 * Tabbed product sections that can be swiped between tabs on touch screens:
 *   <section data-tabs-swipe>
 *     <div role="tablist"><button role="tab" aria-selected="true">New</button>…</div>
 *     <div class="swiper"><div class="swiper-wrapper"><div class="swiper-slide" role="tabpanel">…</div>…</div></div>
 * Tabs and panels stay in sync both ways; ← → / Home / End move between tabs
 * (mirrored in RTL); the panel height follows the active tab without jumps.
 */
import { Swiper, baseOptions } from './swiper';
import { isRtl } from './utils';

export function initTabsSwipe(section) {
    const tabs = [...section.querySelectorAll('[role="tab"]')];
    const container = section.querySelector('.swiper');
    const base = baseOptions(container);

    const select = (index, focus = false) => {
        tabs.forEach((tab, position) => {
            const active = position === index;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });

        if (focus) {
            tabs[index]?.focus();
        }
    };

    const swiper = new Swiper(container, {
        ...base,
        slidesPerView: 1,
        spaceBetween: 24,
        autoHeight: true,
        keyboard: { enabled: false },
        nested: true,
        on: { slideChange: (instance) => select(instance.activeIndex) },
    });

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => swiper.slideTo(index));
        tab.addEventListener('keydown', (event) => {
            const step = { ArrowRight: isRtl(section) ? -1 : 1, ArrowLeft: isRtl(section) ? 1 : -1 }[event.key];
            const target = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : step !== undefined ? (index + step + tabs.length) % tabs.length : null;

            if (target !== null) {
                event.preventDefault();
                swiper.slideTo(target);
                select(target, true);
            }
        });
    });

    select(swiper.activeIndex);

    return swiper;
}
