/**
 * Vendor › Your store › Page builder (VEN-032), loaded for [data-page="vendor-page-builder"]:
 *   [data-page-builder]        root: data-draft-url (PUT), data-preview-url (GET → signed url)
 *   [data-builder-form]        sections form; any change autosaves the draft (debounced),
 *                              then reloads the preview iframe of the draft
 *   [data-section-list]        SortableJS (handle [data-drag-handle]), touch + mouse
 *   [data-section-expand]      toggles the section's settings panel
 *   [data-source-select]       product source → shows [data-source-for=…]
 *   [data-section-add=type]    clones <template data-section-template=type> (store pages)
 *   [data-faq-field]           FAQ rows add / remove
 *   [data-image-field]         image upload (POST data-upload-url) → hidden path
 *   [data-preview-device]      desktop / mobile width of the preview
 */
import { $ } from '../vendor-libs';
import { http } from '../../core/http.js';
import { confirmAction } from '../../core/notify.js';
import { loadSortable, initSelect2 } from '../core.js';
import initContent from '../admin/content.js';

const SAVE_DELAY = 700;
const MOBILE_WIDTH = 390;
/** Desktop preview renders the store at this width, scaled down to fit the card. */
const DESKTOP_WIDTH = 1280;
const randomKey = () => Math.random().toString(36).slice(2, 10).padEnd(8, '0');

/** Badge tone classes (x-ui::badge soft variant): [badge, dot]. */
const TONES = {
    neutral: ['bg-subtle text-ink-secondary', 'bg-ink-secondary'],
    warning: ['bg-warning-bg text-warning', 'bg-warning'],
    success: ['bg-success-bg text-success', 'bg-success'],
    danger: ['bg-danger-bg text-danger', 'bg-danger'],
};
const STATE_TONES = { default: 'neutral', draft: 'warning', published: 'success', saving: 'neutral', error: 'danger' };

function setState(root, state) {
    const badge = root.querySelector('[data-builder-state]');

    if (!badge) {
        return;
    }

    const labels = JSON.parse(badge.dataset.labels || '{}');
    const tone = STATE_TONES[state] ?? 'neutral';
    const dot = [...badge.children].find((child) => !child.hasAttribute('data-state-label'));
    const all = Object.values(TONES);
    badge.classList.remove(...all.flatMap(([soft]) => soft.split(' ')));
    badge.classList.add(...TONES[tone][0].split(' '));
    dot?.classList.remove(...all.map(([, color]) => color));
    dot?.classList.add(TONES[tone][1]);
    badge.dataset.tone = tone;
    badge.querySelector('[data-state-label]').textContent = labels[state] ?? state;
    root.dataset.state = state;
}

function bindPreview(root) {
    const frame = root.querySelector('[data-preview-frame]');
    const wrap = root.querySelector('[data-preview-frame-wrap]');
    const loading = root.querySelector('[data-preview-loading]');
    const failed = root.querySelector('[data-preview-error]');
    const open = root.querySelector('[data-preview-open]');
    let url = null;
    let timer = null;

    const show = (element, visible) => {
        element?.classList.toggle('hidden', !visible);
        element?.classList.toggle('grid', visible);
    };

    const fail = () => {
        show(loading, false);
        show(failed, true);
    };

    const load = async () => {
        show(failed, false);
        show(loading, true);

        try {
            if (!url) {
                const payload = await http.get(root.dataset.previewUrl, null, { toast: false, silent: true });
                url = payload?.data?.url ?? null;
            }
        } catch {
            url = null;
        }

        if (!url) {
            fail();
            return;
        }

        if (open) {
            open.href = url;
        }

        let scroll = 0;

        try {
            scroll = frame.contentWindow?.scrollY ?? 0;
        } catch {
            scroll = 0;
        }

        clearTimeout(timer);
        timer = setTimeout(fail, 25000);
        frame.onload = () => {
            clearTimeout(timer);
            show(loading, false);

            try {
                frame.contentWindow?.scrollTo({ top: scroll, behavior: 'instant' });
            } catch {
                /* cross-origin preview: keep the top */
            }
        };
        frame.src = url;
    };

    let device = 'desktop';
    const fit = () => {
        const base = device === 'mobile' ? MOBILE_WIDTH : DESKTOP_WIDTH;
        wrap.style.width = device === 'mobile' ? `${Math.min(MOBILE_WIDTH, wrap.parentElement.clientWidth - 24)}px` : '';
        const scale = Math.min(1, wrap.clientWidth / base);
        frame.style.width = `${base}px`;
        frame.style.height = `${wrap.clientHeight / scale}px`;
        frame.style.transform = `scale(${scale})`;
    };

    root.querySelectorAll('[data-preview-device]').forEach((button) => {
        button.addEventListener('click', () => {
            root.querySelectorAll('[data-preview-device]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            device = button.dataset.previewDevice;
            fit();
        });
    });

    new ResizeObserver(fit).observe(wrap.parentElement);

    // Phones start on the mobile preview.
    if (window.matchMedia('(max-width: 767px)').matches) {
        root.querySelector('[data-preview-device="mobile"]')?.click();
    } else {
        fit();
    }

    root.querySelector('[data-preview-reload]')?.addEventListener('click', load);
    root.querySelector('[data-preview-retry]')?.addEventListener('click', () => {
        url = null;
        load();
    });

    load();

    return load;
}

function bindSourceGroups(scope) {
    scope.querySelectorAll('[data-source-group]').forEach((group) => {
        const select = group.querySelector('[data-source-select]');
        const sync = () => {
            group.querySelectorAll('[data-source-for]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.sourceFor !== select.value));
        };

        select?.addEventListener('change', sync);
    });
}

function refresh(root) {
    const list = root.querySelector('[data-section-list]');

    list.querySelectorAll(':scope > [data-section-item]').forEach((item, index) => {
        const position = item.querySelector('[data-sort-position]');

        if (position) {
            position.textContent = String(index + 1);
        }
    });

    root.querySelector('[data-sections-placeholder]')?.classList.toggle('hidden', list.children.length > 0);
}

function initFragment(element) {
    initSelect2(element);
    initContent(element);
    bindSourceGroups(element);
}

export default function initPageBuilder(page) {
    const root = page.querySelector('[data-page-builder]') ?? page;
    const target = page.querySelector('[data-builder-target] select, select[data-builder-target]');

    if (target) {
        $(target).on('change', () => target.value && window.location.assign(target.value));
    }

    const form = root.querySelector('[data-builder-form]');

    if (!form) {
        return;
    }

    initContent(form);
    bindSourceGroups(form);
    const reloadPreview = bindPreview(root);
    const readonly = root.hasAttribute('data-readonly');
    const list = form.querySelector('[data-section-list]');
    let saveTimer = null;
    let saving = Promise.resolve();

    const save = () => {
        if (readonly) {
            return;
        }

        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            setState(root, 'saving');
            saving = saving.then(async () => {
                try {
                    const payload = await http.put(root.dataset.draftUrl, new FormData(form), { form, toast: false });
                    setState(root, payload?.data?.state ?? 'draft');
                    reloadPreview();
                } catch {
                    setState(root, 'error');
                }
            });
        }, SAVE_DELAY);
    };

    form.addEventListener('submit', (event) => event.preventDefault());
    form.addEventListener('change', (event) => {
        if (!event.target.matches('[data-image-input]')) {
            save();
        }
    });
    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="text"], input[type="url"], input[type="number"], textarea')) {
            save();
        }
    });

    form.addEventListener('click', async (event) => {
        const expand = event.target.closest('[data-section-expand]');

        if (expand) {
            const panel = document.getElementById(expand.getAttribute('aria-controls'));
            const open = expand.getAttribute('aria-expanded') !== 'true';
            expand.setAttribute('aria-expanded', String(open));
            expand.querySelector('svg')?.classList.toggle('rotate-180', open);
            panel?.classList.toggle('hidden', !open);
            return;
        }

        if (readonly) {
            return;
        }

        const add = event.target.closest('[data-section-add]');

        if (add) {
            const template = form.querySelector(`template[data-section-template="${add.dataset.sectionAdd}"]`);
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.replaceAll('__KEY__', randomKey());
            const item = holder.firstElementChild;
            list.append(item);
            initFragment(item);
            item.querySelector('[data-section-expand]')?.click();
            item.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
            refresh(root);
            save();
            return;
        }

        const remove = event.target.closest('[data-section-remove]');

        if (remove) {
            const item = remove.closest('[data-section-item]');
            const label = item.querySelector('.font-medium')?.textContent ?? '';

            if (await confirmAction({ title: remove.textContent.trim(), text: label, danger: true })) {
                item.remove();
                refresh(root);
                save();
            }
            return;
        }

        const faqAdd = event.target.closest('[data-faq-add]');

        if (faqAdd) {
            const field = faqAdd.closest('[data-faq-field]');
            const rows = field.querySelector('[data-faq-rows]');

            if (rows.children.length >= Number(field.dataset.faqMax || 20)) {
                return;
            }

            const holder = document.createElement('div');
            holder.innerHTML = field.querySelector('template[data-faq-template]').innerHTML.replaceAll('__ROW__', `n${Date.now()}`);
            rows.append(holder.firstElementChild);
            field.querySelector('[data-faq-empty]')?.classList.add('hidden');
            rows.lastElementChild.querySelector('input')?.focus();
            return;
        }

        const faqRemove = event.target.closest('[data-faq-remove]');

        if (faqRemove) {
            const field = faqRemove.closest('[data-faq-field]');
            faqRemove.closest('[data-faq-row]').remove();
            field.querySelector('[data-faq-empty]')?.classList.toggle('hidden', field.querySelector('[data-faq-rows]').children.length > 0);
            save();
            return;
        }

        const imageRemove = event.target.closest('[data-image-remove]');

        if (imageRemove) {
            const field = imageRemove.closest('[data-image-field]');
            field.querySelector('[data-image-path]').value = '';
            field.querySelector('[data-image-preview]').classList.add('hidden');
            field.querySelector('[data-image-empty]')?.classList.remove('hidden');
            imageRemove.classList.add('hidden');
            save();
        }
    });

    form.addEventListener('change', async (event) => {
        const input = event.target.closest('[data-image-input]');

        if (!input?.files?.length) {
            return;
        }

        const field = input.closest('[data-image-field]');
        const data = new FormData();
        data.append('image', input.files[0]);

        try {
            const payload = await http.post(input.dataset.uploadUrl, data, { toast: false });
            field.querySelector('[data-image-path]').value = payload.data.path;
            const preview = field.querySelector('[data-image-preview]');
            preview.src = payload.data.url;
            preview.classList.remove('hidden');
            field.querySelector('[data-image-empty]')?.classList.add('hidden');
            field.querySelector('[data-image-remove]')?.classList.remove('hidden');
            save();
        } finally {
            input.value = '';
        }
    });

    if (readonly) {
        return;
    }

    loadSortable().then((Sortable) => {
        Sortable.create(list, {
            handle: '[data-drag-handle]',
            draggable: '[data-section-item]',
            animation: 180,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            ghostClass: 'opacity-40',
            chosenClass: 'shadow-lg',
            forceFallback: false,
            onEnd: () => {
                refresh(root);
                save();
            },
        });
    });
}
