/**
 * Storefront returns (SF-ECO-030 / 032):
 *   evidence pickers  [data-evidence]: photos (max count + size) and one video (size) are
 *                     checked before upload and listed with remove buttons; the inputs
 *                     keep only the accepted files (DataTransfer).
 *   request return    form[data-return-form]: a line's checkbox enables its hidden id +
 *                     quantity fields, so only chosen lines are sent; at least one line.
 * The forms themselves submit as multipart through data-ajax (core/ajax-forms).
 */
const formatText = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.replace(`:${key}`, value), template ?? '');

function keep(input, files) {
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
}

function bindEvidence(box) {
    const photos = box.querySelector('[data-evidence-photos]');
    const video = box.querySelector('[data-evidence-video]');
    const list = box.querySelector('[data-evidence-list]');
    const error = box.querySelector('[data-evidence-error]');
    const maxPhotos = Number(box.dataset.photoMax) || 5;
    const photoKb = Number(box.dataset.photoKb) || 5120;
    const videoKb = Number(box.dataset.videoKb) || 51200;
    let chosenPhotos = [];
    let chosenVideo = null;

    const fail = (message) => {
        error.textContent = message;
        error.hidden = !message;
    };

    const render = () => {
        const chip = (file, onRemove) => {
            const item = document.createElement('li');
            item.className = 'inline-flex max-w-full items-center gap-1.5 rounded-full border border-sf-border bg-sf-surface py-1 ps-3 pe-1 text-xs text-sf-ink';
            const name = document.createElement('span');
            name.className = 'truncate';
            name.textContent = file.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'grid size-6 shrink-0 place-items-center rounded-full text-sf-muted hover:bg-black/5 hover:text-sf-ink';
            remove.setAttribute('aria-label', formatText(box.dataset.textRemove, { name: file.name }));
            remove.textContent = '×';
            remove.addEventListener('click', onRemove);
            item.append(name, remove);
            return item;
        };

        list.replaceChildren(
            ...chosenPhotos.map((file, index) => chip(file, () => {
                chosenPhotos.splice(index, 1);
                keep(photos, chosenPhotos);
                render();
            })),
            ...(chosenVideo ? [chip(chosenVideo, () => {
                chosenVideo = null;
                video.value = '';
                render();
            })] : []),
        );
    };

    photos?.addEventListener('change', () => {
        fail('');
        const incoming = [...photos.files];
        const tooLarge = incoming.find((file) => file.size > photoKb * 1024);

        if (tooLarge) {
            fail(formatText(box.dataset.textLarge, { name: tooLarge.name, size: Math.round(photoKb / 1024) }));
        }

        chosenPhotos = [...chosenPhotos, ...incoming.filter((file) => file.size <= photoKb * 1024)];

        if (chosenPhotos.length > maxPhotos) {
            chosenPhotos = chosenPhotos.slice(0, maxPhotos);
            fail(box.dataset.textMany);
        }

        keep(photos, chosenPhotos);
        render();
    });

    video?.addEventListener('change', () => {
        fail('');
        const file = video.files[0] ?? null;

        if (file && file.size > videoKb * 1024) {
            fail(formatText(box.dataset.textLarge, { name: file.name, size: Math.round(videoKb / 1024) }));
            video.value = '';
            chosenVideo = null;
        } else {
            chosenVideo = file;
        }

        render();
    });

    box.closest('form')?.addEventListener('ajax:success', () => {
        chosenPhotos = [];
        chosenVideo = null;
        render();
    });
}

function bindReturnForm(form) {
    const sync = () => {
        form.querySelectorAll('[data-return-line]').forEach((line) => {
            const on = Boolean(line.querySelector('[data-return-line-toggle]')?.checked);
            line.querySelectorAll('[data-return-line-field]').forEach((field) => {
                field.disabled = !on;
            });
        });
    };

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-return-line-toggle]')) {
            sync();
            form.querySelector('[data-return-need-item]').hidden = true;
        }
    });

    const evidenceError = form.querySelector('[data-evidence-error]');
    const needsVideo = () => form.querySelector('[data-return-reason]')?.selectedOptions[0]?.dataset.requiresVideo === '1';

    /* Business-rule 422s (e.g. video_required) carry no field errors: show them by the pickers. */
    form.addEventListener('ajax:error', (event) => {
        const payload = event.detail?.payload ?? {};
        if (event.detail?.status === 422 && !Object.keys(payload.errors ?? {}).length && payload.message && evidenceError) {
            evidenceError.textContent = payload.message;
            evidenceError.hidden = false;
        }
    });

    /* Capture: runs before core/ajax-forms sends the request. */
    form.addEventListener('submit', (event) => {
        if (needsVideo() && !form.querySelector('[data-evidence-video]')?.files.length && evidenceError) {
            event.preventDefault();
            event.stopImmediatePropagation();
            evidenceError.textContent = form.querySelector('[data-video-required-text]')?.textContent ?? '';
            evidenceError.hidden = false;
            evidenceError.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }

        if (!form.querySelector('[data-return-line-toggle]:checked')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const message = form.querySelector('[data-return-need-item]');
            message.hidden = false;
            message.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }, true);

    sync();
}

export function initReturns(root = document) {
    root.querySelectorAll('[data-evidence]').forEach((box) => {
        if (!box.__evidence) {
            box.__evidence = true;
            bindEvidence(box);
        }
    });

    root.querySelectorAll('form[data-return-form]').forEach((form) => {
        if (!form.__returnForm) {
            form.__returnForm = true;
            bindReturnForm(form);
        }
    });
}
