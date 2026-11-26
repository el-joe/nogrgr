/**
 * Vendor › Settings › Email templates (VEN-080 / 080a), loaded for
 * [data-page="vendor-email-templates"]: the list's preview modal (iframe, EN / AR) and the
 * edit page's per-locale rich-text editor, variable chips and live preview — the same
 * behaviour as Admin › Email templates (panels/admin/email-templates.js).
 */
import initEmailTemplates from '../admin/email-templates.js';

export default function initVendorEmailTemplates(root) {
    initEmailTemplates(root ?? document);
}
