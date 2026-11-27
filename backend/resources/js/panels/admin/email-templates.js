/**
 * Admin › Settings › Email templates (ADM-102 / 102a).
 *  - list: preview modal (iframe of the server render, EN / AR) and "Send test" modal
 *    whose form action is taken from the clicked row.
 *  - form: per-locale rich-text editor (contenteditable + toolbar, mirrored into a
 *    hidden textarea), variable picker (server-rendered list, data-insert-variable) and a
 *    live preview: with data-preview-url the unsaved subject / body of the active locale
 *    is rendered on the server through the real MAIL-001 layout (debounced POST, JSON
 *    {subject, html} into a sandboxed iframe); desktop / mobile width.
 */
import { openOverlay } from '../ui/index.js';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
const PLACEHOLDER = /\{\{\s*([a-z0-9_.]+)\s*\}\}/gi;

function readJson(id) {
    try {
        return JSON.parse(document.getElementById(id)?.textContent ?? '{}');
    } catch {
        return {};
    }
}

function bindList(root) {
    const frame = root.querySelector('[data-template-preview-frame]');
    let previewUrl = '';
    let locale = 'en';

    const loadPreview = () => {
        if (frame && previewUrl) {
            frame.src = `${previewUrl}?locale=${locale}`;
        }
    };

    root.addEventListener('click', (event) => {
        const preview = event.target.closest?.('[data-template-preview]');

        if (preview) {
            previewUrl = preview.dataset.templatePreview;
            loadPreview();
            openOverlay('template-preview-modal');

            return;
        }

        const localeButton = event.target.closest?.('[data-preview-locale]');

        if (localeButton) {
            locale = localeButton.dataset.previewLocale;
            root.querySelectorAll('[data-preview-locale]').forEach((button) => button.setAttribute('aria-pressed', String(button === localeButton)));
            loadPreview();

            return;
        }

        const test = event.target.closest?.('[data-template-test]');
        const form = root.querySelector('#template-test-form');

        if (test && form) {
            form.action = test.dataset.templateTest;
            openOverlay('template-test-modal');
        }
    });
}

function exec(command, value = null) {
    document.execCommand(command, false, value);
}

function runCommand(command, area, i18n) {
    area.focus();

    switch (command) {
        case 'h2':
        case 'h3':
            exec('formatBlock', command);
            break;
        case 'ul':
            exec('insertUnorderedList');
            break;
        case 'ol':
            exec('insertOrderedList');
            break;
        case 'quote':
            exec('formatBlock', 'blockquote');
            break;
        case 'code':
            exec('insertHTML', `<code>${escapeHtml(window.getSelection()?.toString() || 'code')}</code>`);
            break;
        case 'link': {
            const url = window.prompt(i18n.link ?? 'URL', 'https://');
            if (url && /^(https?:\/\/|mailto:|\{\{)/i.test(url.trim())) {
                exec('createLink', url.trim());
            }
            break;
        }
        case 'image': {
            const url = window.prompt(i18n.image ?? 'Image URL', 'https://');
            if (url && /^https?:\/\//i.test(url.trim())) {
                exec('insertImage', url.trim());
            }
            break;
        }
        case 'table': {
            const cell = escapeHtml(i18n.cell ?? '');
            exec('insertHTML', `<table><tbody><tr><td>${cell}</td><td>${cell}</td></tr><tr><td>${cell}</td><td>${cell}</td></tr></tbody></table><p></p>`);
            break;
        }
        case 'variable':
            exec('insertText', '{{ }}');
            break;
        default:
            exec(command);
    }
}

function bindForm(root) {
    const form = root.querySelector('[data-email-template-form]');

    if (!form) {
        return;
    }

    const samples = readJson('email-template-samples');
    const i18n = readJson('email-template-i18n');
    const eventField = form.querySelector('[data-template-event]');
    const pane = form.querySelector('[data-preview-pane]');
    let activeLocale = 'en';
    let lastField = null;

    const variables = () => samples[eventField?.value] ?? {};
    const fill = (text, escape) => String(text ?? '').replace(PLACEHOLDER, (match, key) => {
        const value = variables()[key] ?? '';
        return escape ? escapeHtml(value) : value;
    });

    const editorFor = (locale) => form.querySelector(`[data-email-editor="${locale}"]`);

    const previewUrl = form.dataset.previewUrl ?? '';
    const frame = form.querySelector('[data-preview-frame]');
    const previewError = form.querySelector('[data-preview-error]');
    let previewTimer = null;
    let previewRequest = 0;

    const renderServerPreview = async () => {
        const editor = editorFor(activeLocale);

        if (!editor || !frame) {
            return;
        }

        const body = new FormData();
        body.append('_token', form.querySelector('input[name="_token"]')?.value ?? '');
        body.append('locale', activeLocale);
        body.append(`subject[${activeLocale}]`, editor.querySelector('[data-editor-subject]')?.value ?? '');
        body.append(`body[${activeLocale}]`, editor.querySelector('[data-editor-area]')?.innerHTML ?? '');
        const request = ++previewRequest;

        try {
            const response = await fetch(previewUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const data = await response.json();

            if (request !== previewRequest) {
                return;
            }

            frame.srcdoc = data.html ?? '';
            form.querySelector('[data-preview-subject]').textContent = data.subject ?? '';
            previewError?.classList.add('hidden');
        } catch {
            previewError?.classList.remove('hidden');
        }
    };

    const renderPreview = () => {
        if (previewUrl) {
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(renderServerPreview, 400);

            return;
        }

        const editor = editorFor(activeLocale);

        if (!editor || !pane) {
            return;
        }

        const vars = variables();
        pane.setAttribute('dir', activeLocale === 'ar' ? 'rtl' : 'ltr');
        pane.querySelector('[data-preview-subject]').textContent = fill(editor.querySelector('[data-editor-subject]')?.value, false);
        pane.querySelector('[data-preview-header]').textContent = vars['store.name'] ?? vars['platform.name'] ?? '';
        pane.querySelector('[data-preview-body]').innerHTML = fill(editor.querySelector('[data-editor-area]')?.innerHTML, true);
    };

    const renderChips = () => {
        form.querySelectorAll('[data-variable-chips]').forEach((container) => {
            if (container.children.length > 0) {
                return; // server-rendered variable picker
            }

            container.replaceChildren(...Object.keys(variables()).map((key) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.dataset.insertVariable = key;
                chip.className = 'inline-flex h-7 items-center rounded-full border border-line bg-surface px-3 text-xs text-ink hover:border-primary hover:text-ink-brand';
                chip.textContent = `{{ ${key} }}`;
                return chip;
            }));
        });
    };

    form.querySelectorAll('[data-email-editor]').forEach((editor) => {
        const area = editor.querySelector('[data-editor-area]');
        const input = editor.querySelector('[data-editor-input]');
        const sync = () => {
            input.value = area.innerHTML;
            renderPreview();
        };

        area.addEventListener('input', sync);
        area.addEventListener('focus', () => {
            lastField = area;
        });
        editor.querySelector('[data-editor-subject]')?.addEventListener('focus', (event) => {
            lastField = event.target;
        });
        editor.querySelector('[data-editor-subject]')?.addEventListener('input', renderPreview);

        editor.querySelectorAll('[data-editor-command]').forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                runCommand(button.dataset.editorCommand, area, i18n);
                sync();
            });
        });
    });

    form.addEventListener('mousedown', (event) => {
        if (event.target.closest?.('[data-insert-variable]')) {
            event.preventDefault();
        }
    });

    form.addEventListener('click', (event) => {
        const chip = event.target.closest?.('[data-insert-variable]');

        if (!chip) {
            return;
        }

        const token = `{{ ${chip.dataset.insertVariable} }}`;
        const editor = chip.closest('[data-email-editor]');
        const target = lastField && editor?.contains(lastField) ? lastField : editor?.querySelector('[data-editor-area]');

        if (target instanceof HTMLInputElement) {
            const start = target.selectionStart ?? target.value.length;
            target.setRangeText(token, start, target.selectionEnd ?? start, 'end');
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.focus();
        } else if (target) {
            target.focus();
            exec('insertText', token);
            target.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-email-editor]').forEach((editor) => {
            editor.querySelector('[data-editor-input]').value = editor.querySelector('[data-editor-area]').innerHTML;
        });
    }, true);

    form.addEventListener('tabs:change', (event) => {
        if (['en', 'ar'].includes(event.detail?.key)) {
            activeLocale = event.detail.key;
            renderPreview();
        }
    });

    eventField?.addEventListener('change', () => {
        renderChips();
        renderPreview();
    });

    form.querySelectorAll('[data-preview-width]').forEach((button) => {
        button.addEventListener('click', () => {
            form.querySelectorAll('[data-preview-width]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            pane.classList.toggle('max-w-90', button.dataset.previewWidth === 'mobile');
        });
    });

    renderChips();
    renderPreview();
}

export default function initEmailTemplates(root) {
    bindList(root);
    bindForm(root);
}
