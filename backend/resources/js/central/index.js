/**
 * Central marketing website.
 */
import Alpine from 'alpinejs';
import { boot, initReveal } from '../core/index.js';

export function initCentral() {
    window.Alpine = Alpine;
    Alpine.start();
    boot(() => initReveal(document));
}
