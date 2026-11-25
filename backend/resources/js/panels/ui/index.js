/**
 * x-ui::* component behaviours. initUi(root) is idempotent and can be called again
 * for content injected later (modals loaded over AJAX, datatable rows …).
 */
import { initOverlays } from './overlays';
import { initConfirm, confirmDialog } from './confirm';
import { useConfirmDialog } from '../../core/notify';
import { initDropdowns } from './dropdown';
import { initTabs } from './tabs';
import { initSidebar } from './sidebar';
import { initFields } from './fields';
import { initUploads } from './upload';
import { initFilters, refreshFilterChips } from './filters';
import { initDatePickers } from './date';
import { initCharts } from './charts';
import { initCommand } from './command';

export { openOverlay, closeOverlay } from './overlays';
export { confirmDialog } from './confirm';
export { activateTab } from './tabs';
export { toggleSidebar } from './sidebar';

export function initUi(root = document) {
    useConfirmDialog(confirmDialog);
    initSidebar();
    initOverlays(root);
    initConfirm();
    initDropdowns();
    initFilters(root);
    initDatePickers(root);
    refreshFilterChips(root);
    initTabs(root);
    initFields(root);
    initUploads(root);
    initCharts(root);
    initCommand(root);
}
