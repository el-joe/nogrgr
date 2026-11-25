/**
 * Component library page (/admin/ui-kit): buttons that fire sample toasts so every
 * toast tone and the stacking / auto-dismiss rules can be checked.
 */
import { notify } from '../../core/notify';
import { t } from '../../core/i18n';

export default function initUiKit() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-ui-kit-toast]');

        if (!trigger || trigger.matches('[data-confirm]:not([data-confirmed])')) {
            return;
        }

        const tone = trigger.dataset.uiKitToast || 'info';
        notify[tone]?.(t(`ui_kit.toast_${tone}_title`), t(`ui_kit.toast_${tone}_message`));
    });
}
