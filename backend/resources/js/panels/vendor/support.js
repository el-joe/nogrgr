/**
 * Vendor › Support (page key "vendor-support", VEN-091 / 091a / 091b): the New ticket
 * remote modal (its ajax form redirects to the new ticket), the live conversation
 * (panels/request-thread.js: Reverb push on support.tickets.<id> + polling) and the
 * Close / Reopen confirm actions (follow the JSON redirect).
 */
import { bindRemoteModals } from '../remote-modal.js';
import { bindThread } from '../request-thread.js';

export default function initSupport(root = document) {
    bindRemoteModals(root);
    root.querySelectorAll('[data-thread]').forEach(bindThread);
}
