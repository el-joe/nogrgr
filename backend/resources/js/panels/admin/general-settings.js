/**
 * Admin › Settings › General (ADM-101): sticky "Unsaved changes" bar.
 */
import { bindUnsavedForms } from './unsaved-form.js';

export default function initGeneralSettings(root) {
    bindUnsavedForms(root);
}
