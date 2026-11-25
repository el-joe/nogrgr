/**
 * File upload (x-ui::file-upload, Figma 28:10325) and image upload (x-ui::image-upload, 28:10357).
 *
 * File upload: dropzone (drag over = brand border + tint) + one row per file.
 *   With data-upload-url each file is POSTed (field "file") with a 4px progress bar;
 *   the JSON `data.id` is stored in a hidden input named data-upload-name; failures show
 *   an error row with Retry. Without a URL the files stay in the <input type=file> and
 *   are submitted with the form.
 * Image upload: 96px tiles with previews, the first tile is "Main", tiles can be
 *   reordered by dragging (SortableJS, lazy) and deleted. Existing images keep their id
 *   in hidden inputs (<name>_keep[]) in the displayed order.
 */
import { t } from '../../core/i18n';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function formatBytes(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unit]}`;
}

function setFiles(input, files) {
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
}

/* ---------------- File upload ---------------- */

function uploadFile(root, row, file) {
    const url = root.dataset.uploadUrl;
    const bar = row.querySelector('[data-file-progress]');
    const meta = row.querySelector('[data-file-meta]');
    row.dataset.state = 'uploading';
    meta.textContent = formatBytes(file.size);

    const body = new FormData();
    body.append('file', file);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url);
    xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) {
            bar.style.width = `${Math.round((event.loaded / event.total) * 100)}%`;
        }
    });
    xhr.addEventListener('load', () => {
        let payload = {};

        try {
            payload = JSON.parse(xhr.responseText || '{}');
        } catch {
            payload = {};
        }

        if (xhr.status >= 200 && xhr.status < 300) {
            row.dataset.state = 'done';
            meta.textContent = `${formatBytes(file.size)} · ${t('upload.uploaded')}`;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = root.dataset.uploadName;
            hidden.value = payload?.data?.id ?? payload?.data?.path ?? '';
            row.appendChild(hidden);
        } else {
            fail(row, payload?.errors?.file?.[0] ?? payload?.message);
        }
    });
    xhr.addEventListener('error', () => fail(row));
    xhr.send(body);

    function fail(target, message) {
        target.dataset.state = 'error';
        meta.textContent = message || t('upload.failed');
        target.querySelector('[data-file-retry]').onclick = () => uploadFile(root, target, file);
    }
}

function addRows(root, files) {
    const list = root.querySelector('[data-upload-list]');
    const template = root.querySelector('template[data-upload-row]');
    const input = root.querySelector('input[type="file"]');

    if (!root.dataset.uploadUrl && !input.multiple) {
        list.innerHTML = '';
    }

    files.forEach((file) => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelector('[data-file-name]').textContent = file.name;
        row.querySelector('[data-file-meta]').textContent = formatBytes(file.size);
        row.dataset.state = 'done';
        row.__file = file;
        row.querySelector('[data-file-remove]').addEventListener('click', () => {
            row.remove();

            if (!root.dataset.uploadUrl) {
                setFiles(input, [...list.children].map((item) => item.__file).filter(Boolean));
            }
        });
        list.appendChild(row);

        if (root.dataset.uploadUrl) {
            uploadFile(root, row, file);
        }
    });
}

function initFileUpload(root) {
    const input = root.querySelector('input[type="file"]');
    const zone = root.querySelector('[data-dropzone]');
    let kept = [];

    input.addEventListener('change', () => {
        const files = [...input.files];

        if (root.dataset.uploadUrl) {
            addRows(root, files);
            input.value = '';
            return;
        }

        kept = input.multiple ? [...kept, ...files] : files;
        setFiles(input, kept);
        addRows(root, files);
    });

    ['dragenter', 'dragover'].forEach((type) =>
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.dataset.dragover = '';
        }),
    );
    ['dragleave', 'dragend', 'drop'].forEach((type) => zone.addEventListener(type, () => delete zone.dataset.dragover));
    zone.addEventListener('drop', (event) => {
        event.preventDefault();

        if (input.disabled) {
            return;
        }

        const files = [...(event.dataTransfer?.files ?? [])];
        setFiles(input, input.multiple ? files : files.slice(0, 1));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

/* ---------------- Image upload ---------------- */

function refreshMain(grid) {
    grid.querySelectorAll('[data-image-tile]').forEach((tile, index) => {
        tile.querySelector('[data-main-badge]').hidden = index !== 0;
    });
}

function syncImageInput(root) {
    const input = root.querySelector('input[type="file"]');
    const tiles = [...root.querySelectorAll('[data-image-tile]')];
    setFiles(
        input,
        tiles.map((tile) => tile.__file).filter(Boolean),
    );
    refreshMain(root.querySelector('[data-image-grid]'));
    const add = root.querySelector('[data-image-add]');
    const max = Number(root.dataset.max || 0);
    add.hidden = max > 0 && tiles.length >= max;
}

function addImageTile(root, file) {
    const template = root.querySelector('template[data-image-tile-template]');
    const tile = template.content.firstElementChild.cloneNode(true);
    const image = tile.querySelector('img');
    image.src = URL.createObjectURL(file);
    image.alt = file.name;
    tile.__file = file;
    root.querySelector('[data-image-grid]').insertBefore(tile, root.querySelector('[data-image-add]'));
}

function initImageUpload(root) {
    const input = root.querySelector('input[type="file"]');
    const grid = root.querySelector('[data-image-grid]');
    let pendingFiles = [];

    input.addEventListener('change', () => {
        if (input.__syncing) {
            return;
        }

        const max = Number(root.dataset.max || 0);
        const current = grid.querySelectorAll('[data-image-tile]').length;
        const incoming = [...input.files].filter((file) => !pendingFiles.includes(file));
        const allowed = max > 0 ? incoming.slice(0, Math.max(0, max - current)) : incoming;
        allowed.forEach((file) => addImageTile(root, file));
        pendingFiles = [...pendingFiles, ...allowed];
        input.__syncing = true;
        syncImageInput(root);
        input.__syncing = false;
    });

    grid.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-image-remove]');

        if (!remove) {
            return;
        }

        const tile = remove.closest('[data-image-tile]');

        if (tile.__file) {
            pendingFiles = pendingFiles.filter((file) => file !== tile.__file);
            URL.revokeObjectURL(tile.querySelector('img')?.src ?? '');
        }

        tile.remove();
        syncImageInput(root);
    });

    import('sortablejs').then(({ default: Sortable }) => {
        Sortable.create(grid, {
            draggable: '[data-image-tile]',
            animation: 160,
            filter: '[data-image-remove]',
            preventOnFilter: false,
            onEnd: () => syncImageInput(root),
        });
    });

    refreshMain(grid);
}

export function initUploads(root = document) {
    root.querySelectorAll('[data-upload]:not([data-upload-ready])').forEach((element) => {
        element.dataset.uploadReady = '';
        initFileUpload(element);
    });

    root.querySelectorAll('[data-image-upload]:not([data-upload-ready])').forEach((element) => {
        element.dataset.uploadReady = '';
        initImageUpload(element);
    });
}
