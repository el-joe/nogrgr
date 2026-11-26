/**
 * Owner portal › Help & support (page key "owner-support", OWN-009): New ticket remote
 * modal and, on a ticket page, the conversation (panels/request-thread.js, polling).
 */
import { bindRemoteModals } from '../remote-modal.js';
import { bindThread } from '../request-thread.js';

export default function initOwnerSupport(root = document) {
    bindRemoteModals(root);
    root.querySelectorAll('[data-thread]').forEach(bindThread);
}
