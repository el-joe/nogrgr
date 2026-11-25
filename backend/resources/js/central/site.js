/**
 * Central marketing website behaviour ([data-page^="site-"]): sticky header, Swiper
 * rails (touch + mouse drag + keyboard), testimonials carousel, count-up stats,
 * parallax, ajax inline success states, FAQ accordion, table of contents, share/copy
 * and auth helpers. Every module is opt-in by data attribute and reduced-motion aware.
 */
import { initHeader } from './site/header.js';
import { initRails, initChips, initReviews } from './site/rails.js';
import { initCountUp, initParallax } from './site/motion.js';
import { initInlineForms } from './site/forms.js';
import { initToc, initCopyLink, initResendTimer, initPasswordToggles } from './site/content.js';

export default function initSite(root = document) {
    initHeader(root);
    initInlineForms(root);
    initCountUp(root);
    initParallax(root);
    initToc(root);
    initCopyLink(root);
    initResendTimer(root);
    initPasswordToggles(root);

    if (root.querySelector('[data-rail], [data-chips], [data-reviews]')) {
        initRails(root);
        initChips(root);
        initReviews(root);
    }
}
