/**
 * Central › sign-up wizard (WEB-016 … 019b) and the social result (WEB-016a):
 *
 *  - sign-up: "Sign up with Google / Apple" asks for the terms tick first (server
 *    re-validates); the phone field is intl-tel-input (core/phone.js)
 *  - verify: resend countdown (the cooldown itself is enforced server side)
 *  - payment redirect: forwards to the gateway's hosted page after a short beat
 *  - payment processing: reloads until the gateway confirms
 *  - social success: continues to the next step
 */
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initSocialButtons(root) {
    const form = root.querySelector('[data-signup-form]');

    if (!form) {
        return;
    }

    const compliance = form.querySelector('[data-compliance]');
    const message = form.querySelector('[data-message-for="compliance"]');

    form.querySelectorAll('[data-social-submit]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (compliance && !compliance.checked) {
                event.preventDefault();

                if (message) {
                    message.textContent = button.getAttribute('title') ?? '';
                    message.hidden = false;
                }

                compliance.focus();
            }
        });
    });

    compliance?.addEventListener('change', () => {
        if (compliance.checked && message) {
            message.hidden = true;
        }
    });
}

function initResendCountdown(root) {
    const form = root.querySelector('[data-resend-form]');
    const button = form?.querySelector('[data-resend-button]');
    const label = form?.querySelector('[data-resend-label]');

    if (!form || !button || !label) {
        return;
    }

    let remaining = Number.parseInt(form.dataset.cooldown ?? '0', 10) || 0;
    const format = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;

    const paint = () => {
        if (remaining > 0) {
            button.disabled = true;
            label.textContent = (form.dataset.labelWait ?? '').replace('__TIME__', format(remaining));
        } else {
            button.disabled = false;
            label.textContent = form.dataset.labelReady ?? label.textContent;
        }
    };

    paint();

    if (remaining > 0) {
        const timer = setInterval(() => {
            remaining -= 1;
            paint();

            if (remaining <= 0) {
                clearInterval(timer);
            }
        }, 1000);
    }

    form.addEventListener('submit', (event) => {
        if (button.disabled || form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = '1';
        button.disabled = true;
    });
}

function initPaymentRedirect(root) {
    const card = root.querySelector('[data-payment-redirect]');
    const url = card?.dataset.url;

    if (url && /^https:\/\//.test(url)) {
        setTimeout(() => window.location.assign(url), reduceMotion() ? 300 : 1200);
    }
}

function initPaymentProcessing(root) {
    const card = root.querySelector('[data-payment-processing]');
    const url = card?.dataset.refreshUrl;

    if (url) {
        setTimeout(() => window.location.replace(url), 5000);
    }
}

function initSocialSuccess(root) {
    const card = root.querySelector('[data-social-success]');
    const url = card?.dataset.continueUrl;

    if (url) {
        setTimeout(() => window.location.replace(url), reduceMotion() ? 200 : 1200);
    }
}

export default function initSignup(root = document) {
    initSocialButtons(root);
    initResendCountdown(root);
    initPaymentRedirect(root);
    initPaymentProcessing(root);
    initSocialSuccess(root);
}
