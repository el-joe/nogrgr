/**
 * Verify e-mail code page ([data-verify-email]): six digit boxes with auto-advance,
 * backspace to the previous box and paste of the whole code (auto-submits when complete);
 * the hidden `code` field is filled before core/ajax-forms posts. Resend counts down.
 */
import { http } from '../../../core/http';

const pad = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;

function bindDigits(form) {
    const boxes = [...form.querySelectorAll('[data-verify-digit]')];
    const code = form.querySelector('[data-verify-code]');
    const sync = () => {
        code.value = boxes.map((box) => box.value).join('');
        return code.value.length === boxes.length;
    };
    const fill = (text, from = 0) => {
        const digits = text.replace(/\D/g, '').split('');
        digits.slice(0, boxes.length - from).forEach((digit, index) => {
            boxes[from + index].value = digit;
        });
        boxes[Math.min(from + digits.length, boxes.length - 1)].focus();
        if (sync()) {
            form.requestSubmit();
        }
    };

    boxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            const value = box.value.replace(/\D/g, '');
            if (value.length > 1) {
                fill(value, index);
                return;
            }
            box.value = value;
            box.removeAttribute('aria-invalid');
            if (value && index < boxes.length - 1) {
                boxes[index + 1].focus();
            }
            if (sync()) {
                form.requestSubmit();
            }
        });
        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !box.value && index > 0) {
                boxes[index - 1].focus();
                boxes[index - 1].value = '';
            } else if (event.key === 'ArrowLeft' && index > 0) {
                boxes[index - 1].focus();
            } else if (event.key === 'ArrowRight' && index < boxes.length - 1) {
                boxes[index + 1].focus();
            }
        });
        box.addEventListener('paste', (event) => {
            event.preventDefault();
            fill(event.clipboardData?.getData('text') ?? '', index);
        });
    });

    form.addEventListener('submit', () => sync(), true);
    form.addEventListener('ajax:error', () => {
        boxes.forEach((box) => box.setAttribute('aria-invalid', 'true'));
        boxes.forEach((box) => {
            box.value = '';
        });
        boxes[0].focus();
    });
}

function bindResend(button) {
    let timer = null;
    const tick = (seconds) => {
        window.clearInterval(timer);
        let left = seconds;
        const paint = () => {
            button.disabled = left > 0;
            button.textContent = left > 0 ? button.dataset.labelWait.replace(':time', pad(left)) : button.dataset.label;
        };
        paint();
        if (left > 0) {
            timer = window.setInterval(() => {
                left -= 1;
                paint();
                if (left <= 0) {
                    window.clearInterval(timer);
                }
            }, 1000);
        }
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const response = await http.post(button.dataset.verifyResend, {});
            tick(Number(response?.data?.resend_in ?? 45));
        } catch {
            button.disabled = false;
        }
    });

    tick(Number(button.dataset.seconds || 0));
}

export function initVerify(root = document) {
    root.querySelectorAll('form[data-verify-form]').forEach((form) => {
        if (!form.__verify) {
            form.__verify = true;
            bindDigits(form);
        }
    });
    root.querySelectorAll('[data-verify-resend]').forEach((button) => {
        if (!button.__resend) {
            button.__resend = true;
            bindResend(button);
        }
    });
}
