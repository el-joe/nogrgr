/**
 * Central marketing website. Page modules load lazily per [data-page].
 */
import Alpine from 'alpinejs';
import { boot, initReveal } from '../core/index.js';

const pages = {
    'register-': () => import('./register.js'),
    'site-': () => import('./site.js'),
};

export function initCentral() {
    window.Alpine = Alpine;
    Alpine.start();
    boot(() => {
        initReveal(document);

        const page = document.querySelector('[data-page]')?.dataset.page ?? '';
        const loader = Object.entries(pages).find(([prefix]) => page.startsWith(prefix))?.[1];
        loader?.().then((module) => module.default?.(document));
    });
}
