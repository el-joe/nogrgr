/**
 * Core bootstrap shared by every surface.
 */
import { bindAjaxForms } from './ajax-forms';
import { initPhoneInputs } from './phone';
import { bindToasts } from './notify';
import { bindClearOnInput } from './form-errors';

export { http, HttpError } from './http';
export { notify, confirmAction } from './notify';
export { t } from './i18n';
export { showErrors, clearErrors, clearFieldError, bindClearOnInput } from './form-errors';
export { initPhoneInputs } from './phone';
export { initReveal } from './reveal';

export function boot(callback) {
    const run = () => {
        bindAjaxForms(document);
        bindClearOnInput(document);
        initPhoneInputs(document);
        bindToasts(document);
        callback?.();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
}
