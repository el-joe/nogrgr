/**
 * Toasts & confirm dialogs for every surface.
 *
 * Toasts (Figma Toast 31:10312): 380 wide, 4px leading accent stripe (mirrored in
 * RTL), icon, title + optional message, close button. Success/Info auto-dismiss
 * after 5s, Warning 8s, Error stays until closed. Max 3 stacked (oldest dropped).
 *   notify.success('Product saved', 'Changes are live on 42 storefronts.');
 *
 * confirmAction() uses the panel Confirm Dialog (<x-ui::confirm>, #ui-confirm) once a
 * panel registered it with useConfirmDialog(), otherwise falls back to SweetAlert2 (lazy-loaded, central/storefront).
 */
import iconSuccess from '../../svg/icons/check-circle.svg?raw';
import iconError from '../../svg/icons/x-circle.svg?raw';
import iconWarning from '../../svg/icons/alert-triangle.svg?raw';
import iconInfo from '../../svg/icons/info.svg?raw';
import iconClose from '../../svg/icons/x.svg?raw';
import { t } from './i18n';

const TONES = {
    success: { icon: iconSuccess, accent: 'var(--color-success)', timeout: 5000 },
    error: { icon: iconError, accent: 'var(--color-danger)', timeout: 0 },
    warning: { icon: iconWarning, accent: 'var(--color-warning)', timeout: 8000 },
    info: { icon: iconInfo, accent: 'var(--color-info)', timeout: 5000 },
};

const MAX_TOASTS = 3;

function container() {
    let region = document.getElementById('ui-toasts');

    if (!region) {
        region = document.createElement('div');
        region.id = 'ui-toasts';
        region.className = 'ui-toasts';
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('aria-relevant', 'additions');
        document.body.appendChild(region);
    }

    return region;
}

function svg(markup, className) {
    const wrapper = document.createElement('span');
    wrapper.innerHTML = markup.trim();
    const element = wrapper.firstElementChild;
    element.setAttribute('class', `ui-icon ${className}`);
    element.setAttribute('width', '20');
    element.setAttribute('height', '20');
    element.setAttribute('aria-hidden', 'true');

    return element;
}

export function dismissToast(toast) {
    if (!toast || toast.dataset.leaving !== undefined) {
        return;
    }

    toast.dataset.leaving = '';
    clearTimeout(Number(toast.dataset.timer));
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
    setTimeout(() => toast.remove(), 400);
}

/**
 * @param {'success'|'error'|'warning'|'info'} tone
 * @param {string} title
 * @param {string} [message]
 * @param {{timeout?: number, action?: {label: string, onClick: Function}}} [options]
 */
export function toast(tone, title, message = '', options = {}) {
    if (!title) {
        return null;
    }

    const config = TONES[tone] ?? TONES.info;
    const region = container();
    const element = document.createElement('div');
    element.className = 'ui-toast';
    element.dataset.tone = tone;
    element.style.setProperty('--toast-accent', config.accent);
    element.setAttribute('role', tone === 'error' ? 'alert' : 'status');

    const icon = svg(config.icon, 'ui-toast-icon mt-px');
    icon.style.color = config.accent;

    const body = document.createElement('div');
    body.className = 'min-w-0 flex-1';
    const heading = document.createElement('p');
    heading.className = 'ui-toast-title text-sm font-medium text-ink';
    heading.textContent = title;
    body.appendChild(heading);

    if (message) {
        const text = document.createElement('p');
        text.className = 'ui-toast-text mt-0.5 text-[13px] leading-[18px] text-ink-secondary';
        text.textContent = message;
        body.appendChild(text);
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'ui-toast-close grid size-6 shrink-0 place-items-center rounded-chip text-ink-muted hover:bg-subtle hover:text-ink';
    close.setAttribute('aria-label', t('ui.close'));
    close.appendChild(svg(iconClose, 'size-4'));
    close.addEventListener('click', () => dismissToast(element));

    element.append(icon, body);

    /* Optional action (e.g. "Undo"): {label, onClick}. */
    if (options.action?.label) {
        const action = document.createElement('button');
        action.type = 'button';
        action.className = 'ui-toast-action';
        action.textContent = options.action.label;
        action.addEventListener('click', () => options.action.onClick?.());
        element.append(action);
    }

    element.append(close);
    region.appendChild(element);

    while (region.children.length > MAX_TOASTS) {
        region.firstElementChild.remove();
    }

    const timeout = options.timeout ?? config.timeout;

    if (timeout > 0) {
        element.dataset.timer = String(setTimeout(() => dismissToast(element), timeout));
    }

    return element;
}

const PENDING_KEY = 'ui:pending-toasts';

/**
 * Toast to show on the next page (RQA-VEN-013): a JSON save that answers `redirect`
 * navigates right away, so the message is kept in sessionStorage and shown by
 * bindToasts() once the target page boots. Falls back to an immediate toast.
 */
export function queueToast(tone, title, message = '') {
    if (!title) {
        return;
    }

    try {
        const pending = JSON.parse(window.sessionStorage.getItem(PENDING_KEY) || '[]');
        pending.push({ tone, title, message });
        window.sessionStorage.setItem(PENDING_KEY, JSON.stringify(pending.slice(-MAX_TOASTS)));
    } catch {
        toast(tone, title, message);
    }
}

function flushQueuedToasts() {
    let pending = [];

    try {
        pending = JSON.parse(window.sessionStorage.getItem(PENDING_KEY) || '[]');
        window.sessionStorage.removeItem(PENDING_KEY);
    } catch {
        return;
    }

    if (Array.isArray(pending)) {
        pending.forEach((item) => item && typeof item.title === 'string' && toast(TONES[item.tone] ? item.tone : 'info', item.title, typeof item.message === 'string' ? item.message : ''));
    }
}

/** Server-rendered toasts (<x-ui::toasts>): close buttons and auto-dismiss timers. */
export function bindToasts(root = document) {
    if (root === document) {
        flushQueuedToasts();
    }

    root.querySelectorAll('.ui-toast[data-toast-timeout]:not([data-timer])').forEach((element) => {
        const timeout = Number(element.dataset.toastTimeout);

        if (timeout > 0) {
            element.dataset.timer = String(setTimeout(() => dismissToast(element), timeout));
        }
    });

    if (document.documentElement.dataset.toastsBound === undefined) {
        document.documentElement.dataset.toastsBound = '';
        document.addEventListener('click', (event) => {
            const close = event.target.closest?.('[data-toast-close]');

            if (close) {
                dismissToast(close.closest('.ui-toast'));
            }
        });
    }
}

export const notify = {
    success: (title, message) => toast('success', title, message),
    error: (title, message) => toast('error', title, message),
    warning: (title, message) => toast('warning', title, message),
    info: (title, message) => toast('info', title, message),
};

let confirmHandler = null;

/** Panels register their Confirm Dialog here (resources/js/panels/ui/index.js). */
export function useConfirmDialog(handler) {
    confirmHandler = handler;
}

/**
 * @returns {Promise<boolean>}
 */
export async function confirmAction({ title, text = '', confirmText = t('ui.confirm'), cancelText = t('ui.cancel'), danger = false, requireText = '' } = {}) {
    if (confirmHandler && document.getElementById('ui-confirm')) {
        return confirmHandler({ title, message: text, confirmLabel: confirmText, cancelLabel: cancelText, tone: danger ? 'danger' : 'brand', requireText });
    }

    const [{ default: Swal }] = await Promise.all([import('sweetalert2/dist/sweetalert2.esm.js'), import('sweetalert2/dist/sweetalert2.css')]);
    const result = await Swal.fire({
        title,
        text,
        icon: danger ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: document.documentElement.dir === 'rtl',
        focusCancel: danger,
    });

    return result.isConfirmed;
}
