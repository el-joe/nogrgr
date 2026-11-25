/**
 * Admin › Website content (blog categories / posts, static pages, FAQs).
 *
 *  - [data-rich-editor]   toolbar + contenteditable mirrored into the hidden textarea
 *                         before every submit (server sanitises the HTML).
 *  - [data-slug-source]   the English title fills [data-slug-target] until the slug is edited.
 *  - [data-faq-sort]      drag & drop FAQ order (SortableJS), saved on drop.
 */
import { http } from '../../core/http.js';
import { notify } from '../../core/notify.js';
import { loadSortable } from '../core.js';

const COMMANDS = {
    bold: () => document.execCommand('bold'),
    italic: () => document.execCommand('italic'),
    h2: () => document.execCommand('formatBlock', false, 'h2'),
    h3: () => document.execCommand('formatBlock', false, 'h3'),
    ul: () => document.execCommand('insertUnorderedList'),
    ol: () => document.execCommand('insertOrderedList'),
    quote: () => document.execCommand('formatBlock', false, 'blockquote'),
    unlink: () => document.execCommand('unlink'),
};

function slugify(value) {
    return value
        .toString()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 120);
}

function bindEditors(root) {
    root.querySelectorAll('[data-rich-editor]').forEach((editor) => {
        const area = editor.querySelector('[data-editor-area]');
        const input = editor.querySelector('[data-editor-input]');

        if (!area || !input) {
            return;
        }

        const sync = () => {
            input.value = area.innerHTML;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        area.addEventListener('input', sync);
        area.addEventListener('blur', sync);

        editor.querySelectorAll('[data-editor-command]').forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                area.focus();
                const command = button.dataset.editorCommand;

                if (command === 'link') {
                    const url = window.prompt(area.dataset.linkPrompt || 'https://', 'https://');

                    if (url && /^(https?:\/\/|mailto:|\/)/i.test(url.trim())) {
                        document.execCommand('createLink', false, url.trim());
                    }
                } else {
                    COMMANDS[command]?.();
                }

                sync();
            });
        });
    });

    root.querySelectorAll('form[data-content-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-rich-editor]').forEach((editor) => {
                const area = editor.querySelector('[data-editor-area]');
                const input = editor.querySelector('[data-editor-input]');

                if (area && input) {
                    input.value = area.innerHTML;
                }
            });
        }, { capture: true });
    });
}

function bindSlug(root) {
    const source = root.querySelector('[data-slug-source] input, input[data-slug-source]');
    const target = root.querySelector('[data-slug-target] input, input[data-slug-target]');

    if (!source || !target) {
        return;
    }

    let touched = target.value.trim() !== '';
    target.addEventListener('input', () => {
        touched = target.value.trim() !== '';
    });
    source.addEventListener('input', () => {
        if (!touched) {
            target.value = slugify(source.value);
        }
    });
}

async function bindFaqSort(root) {
    const list = root.querySelector('[data-faq-sort]');

    if (!list) {
        return;
    }

    const Sortable = await loadSortable();

    Sortable.create(list, {
        handle: '[data-faq-handle]',
        animation: 180,
        onEnd: async () => {
            const ids = [...list.querySelectorAll('[data-id]')].map((item) => Number(item.dataset.id));

            try {
                const payload = await http.post(list.dataset.faqSort, { ids });
                notify.success(payload?.message);
            } catch {
                // http.js already shows the error toast
            }
        },
    });
}

export default function initContent(root = document) {
    bindEditors(root);
    bindSlug(root);
    bindFaqSort(root);
}
