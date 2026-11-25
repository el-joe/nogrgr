/**
 * Shared bootstrap for admin / vendor / affiliate panels.
 */
import Alpine from 'alpinejs';
import { boot, initReveal } from '../core/index.js';
import { initThemeToggle } from '../core/theme';
import { initSelect2 } from './select2';
import { initDataTables } from './datatable';
import { initUi } from './ui/index.js';

export { initSelect2 } from './select2';
export { initDataTable, initDataTables, reloadDataTable } from './datatable';
export { initUi, openOverlay, closeOverlay, confirmDialog, activateTab } from './ui/index.js';

/** Lazy heavy libraries: only loaded on pages that use them. */
export const loadChart = () => import('chart.js/auto').then((module) => module.default);
export const loadSortable = () => import('sortablejs').then((module) => module.default);

export function bootPanel(callback) {
    window.Alpine = Alpine;
    Alpine.start();

    boot(() => {
        initThemeToggle(document);
        initUi(document);
        initSelect2(document);
        initDataTables(document);
        initReveal(document);
        callback?.();
    });
}
