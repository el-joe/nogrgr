/**
 * Content page helpers: table of contents from the article's h2s with scroll-spy,
 * copy-link button, "resend in 00:59" countdown and show/hide password buttons.
 */
import { notify } from '../../core/notify.js';

const slugify = (text, index) =>
    `section-${index + 1}-${text
        .toLowerCase()
        .trim()
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 48)}`;

export function initToc(root = document) {
    root.querySelectorAll('[data-toc]').forEach((nav) => {
        const article = document.querySelector(nav.dataset.toc);
        const list = nav.querySelector('[data-toc-list]');
        const headings = [...(article?.querySelectorAll('h2') ?? [])];

        if (!list || headings.length < 2) {
            nav.closest('[data-toc-wrap]')?.setAttribute('hidden', '');
            nav.hidden = true;
            return;
        }

        const links = headings.map((heading, index) => {
            heading.id ||= slugify(heading.textContent ?? '', index);
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.textContent = heading.textContent;
            link.className = 'block py-1 hover:text-primary';
            item.append(link);
            list.append(item);
            return link;
        });

        nav.hidden = false;
        const setActive = (id) => links.forEach((link) => link.setAttribute('aria-current', String(link.hash === `#${id}`)));
        setActive(headings[0].id);

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(
                (entries) => {
                    const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
                    if (visible) {
                        setActive(visible.target.id);
                    }
                },
                { rootMargin: '-90px 0px -65% 0px' },
            );
            headings.forEach((heading) => observer.observe(heading));
        }
    });
}

export function initCopyLink(root = document) {
    root.querySelectorAll('[data-copy-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.copyLink || window.location.href;
            try {
                await navigator.clipboard.writeText(url);
            } catch {
                const input = document.createElement('input');
                input.value = url;
                document.body.append(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }
            notify.success(button.dataset.copied ?? url);
        });
    });
}

export function initResendTimer(root = document) {
    root.querySelectorAll('[data-resend]').forEach((button) => {
        const label = button.querySelector('[data-resend-label]');
        const template = button.dataset.resendTemplate ?? ':time';
        const ready = button.dataset.resendReady ?? '';
        let seconds = parseInt(button.dataset.resend, 10) || 60;
        const pad = (value) => String(value).padStart(2, '0');

        button.disabled = true;
        const tick = () => {
            if (seconds <= 0) {
                button.disabled = false;
                label.textContent = ready;
                return;
            }
            label.textContent = template.replace(':time', `${pad(Math.floor(seconds / 60))}:${pad(seconds % 60)}`);
            seconds -= 1;
            setTimeout(tick, 1000);
        };
        tick();
    });
}

export function initPasswordToggles(root = document) {
    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = button.parentElement?.querySelector('input');

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? button.dataset.labelHide : button.dataset.labelShow);
        });
    });
}
