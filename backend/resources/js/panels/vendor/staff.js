/**
 * Vendor › Settings › Staff and Roles (VEN-076 / 077): staff invite / edit / view modals
 * loaded from the server (their phone field gets intl-tel-input once the form arrives)
 * and the role editor matrix ("Manage" implies "View"), shared with the admin editor.
 */
import { initPhoneInputs } from '../../core/phone';
import { bindRemoteModals } from '../remote-modal.js';
import initRoleMatrix from '../admin/roles.js';

export default function initStaff(root) {
    bindRemoteModals(root);
    document.querySelectorAll('[data-remote-body]').forEach((body) => {
        new MutationObserver(() => initPhoneInputs(body)).observe(body, { childList: true });
    });
    initRoleMatrix(root);
}
