/**
 * Flash-sale countdown with flipping digits, only ticking while visible:
 *   <div data-countdown="2026-11-30T23:59:59Z" data-countdown-label="Ends in">
 *     <span data-unit="days"></span> <span data-unit="hours"></span> <span data-unit="minutes"></span> <span data-unit="seconds"></span>
 * Each changed digit flips (CSS .mo-flip); reduced motion swaps text only. Screen
 * readers get a once-a-minute aria-label instead of a per-second live region.
 */
import { prefersReducedMotion, watchVisibility } from './utils';
import { t } from '../../../core/i18n';

const pad = (value) => String(value).padStart(2, '0');

function parts(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));

    return { days: Math.floor(total / 86400), hours: Math.floor((total % 86400) / 3600), minutes: Math.floor((total % 3600) / 60), seconds: total % 60 };
}

function setDigits(unit, text, animate) {
    const current = unit.dataset.value;

    if (current === text) {
        return;
    }

    unit.dataset.value = text;

    if (!animate || current === undefined) {
        unit.textContent = text;
        return;
    }

    unit.classList.remove('mo-flip');
    void unit.offsetWidth;
    unit.textContent = text;
    unit.classList.add('mo-flip');
}

export function initCountdowns(root = document) {
    root.querySelectorAll('[data-countdown]:not([data-countdown-ready])').forEach((element) => {
        element.dataset.countdownReady = '';
        const end = Date.parse(element.dataset.countdown);
        const animate = !prefersReducedMotion();
        let timer = null;
        let lastMinute = null;

        const render = () => {
            const left = parts(end - Date.now());
            /* Designs without a days box: the hours carry the whole time left. */
            const clock = element.querySelector('[data-unit="days"]') ? left : { ...left, days: 0, hours: left.days * 24 + left.hours };
            Object.entries(clock).forEach(([unit, value]) => {
                const node = element.querySelector(`[data-unit="${unit}"]`);

                if (node) {
                    setDigits(node, pad(value), animate);
                }
            });

            if (left.minutes !== lastMinute) {
                lastMinute = left.minutes;
                element.setAttribute('aria-label', `${element.dataset.countdownLabel ?? ''} ${t('motion.countdown', { days: left.days, hours: left.hours, minutes: left.minutes })}`.trim());
            }

            if (end - Date.now() <= 0) {
                clearInterval(timer);
                element.dataset.expired = '';
            }
        };

        render();
        watchVisibility(element, (visible) => {
            clearInterval(timer);
            timer = visible ? setInterval(render, 1000) : null;
        });
    });
}
