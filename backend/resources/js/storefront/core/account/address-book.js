/**
 * Address book (spec §1–§2) for both mounts:
 *   manage — Account › Addresses: [data-address-book][data-mode="manage"], data from #address-book
 *   select — checkout address step: [data-checkout-address], data from its [data-checkout-config]
 * One canonical modal / bottom sheet (root data-modal="<id>") handles add + edit; the
 * lists re-render from `data.addresses` of every store / update / default / destroy
 * response, so the page never reloads. Cards animate in / out (motion guidelines §4).
 */
import { http } from '../../../core/http';
import { clearErrors } from '../../../core/form-errors';
import { openSheet, closeSheet, initSheets } from '../motion/sheet';
import { prefersReducedMotion } from '../motion/utils';
import { setPhone } from './phone';
import { resetMapNotices } from './map';

const readJson = (node) => {
    try {
        return JSON.parse(node?.textContent || '{}');
    } catch {
        return {};
    }
};

const leave = (node) => new Promise((resolve) => {
    if (!node || prefersReducedMotion()) {
        resolve();

        return;
    }

    node.dataset.leaving = '';
    setTimeout(resolve, 220);
});

function buildCard(template, address) {
    const html = template.innerHTML.replaceAll('__ID__', String(address.id));
    const holder = document.createElement('div');
    holder.innerHTML = html.trim();
    const card = holder.firstElementChild;
    const text = (selector, value) => {
        const node = card.querySelector(selector);

        if (node) {
            node.textContent = value ?? '';
        }

        return node;
    };

    text('[data-field-full-name]', address.full_name);
    text('[data-field-phone]', address.phone);
    text('[data-field-one-line]', address.one_line);
    const label = text('[data-field-label]', address.label);
    if (label) {
        label.hidden = !address.label;
    }
    const chip = card.querySelector('[data-field-default]');
    if (chip) {
        chip.hidden = !address.is_default;
    }
    const setDefault = card.querySelector('[data-address-default]');
    if (setDefault) {
        setDefault.hidden = Boolean(address.is_default);
    }

    return card;
}

export function initAddressBook(root) {
    if (root.__addressBook) {
        return root.__addressBook;
    }

    const mode = root.dataset.mode === 'select' ? 'select' : 'manage';
    const config = mode === 'select' ? readJson(root.querySelector('[data-checkout-config]')) : readJson(document.getElementById('address-book'));
    let addresses = Array.isArray(config.addresses) ? config.addresses : [];
    const urls = config.urls ?? {};

    // The checkout step ships its modal in a <template> (a <form> may not nest inside the
    // checkout form); mount it at the end of <body>.
    const modalTemplate = root.querySelector('template[data-address-modal-template]');
    if (modalTemplate) {
        document.body.append(modalTemplate.content.cloneNode(true));
        modalTemplate.remove();
        initSheets(document.body);
    }

    const modal = document.getElementById(root.dataset.modal || 'sf-address-modal');
    const form = modal?.querySelector('form[data-address-form]');
    const deleteModal = modal ? document.getElementById(`${modal.id}-delete`) : null;
    const lists = [...root.querySelectorAll('[data-address-list]')];
    const empty = root.querySelector('[data-address-empty]');
    let purpose = 'shipping';
    let editing = null;
    const entering = new Set();

    const selection = () => Object.fromEntries(lists.map((list) => {
        const group = list.closest('[data-address-group]')?.dataset.addressGroup ?? 'manage';

        return [group, list.querySelector('input[data-address-radio]:checked')?.value ?? null];
    }));

    const render = (selected = selection()) => {
        lists.forEach((list) => {
            const group = list.closest('[data-address-group]')?.dataset.addressGroup ?? 'manage';
            const template = list.parentElement.querySelector('template[data-address-template]');

            if (!template) {
                return;
            }

            const fallback = addresses.find((address) => address.is_default) ?? addresses[0];
            const chosen = addresses.some((address) => String(address.id) === String(selected[group])) ? selected[group] : fallback?.id;

            list.replaceChildren(...addresses.map((address) => {
                const card = buildCard(template, address);
                const radio = card.querySelector('input[data-address-radio]');

                if (radio) {
                    radio.checked = String(address.id) === String(chosen);
                }

                if (entering.has(String(address.id)) && !prefersReducedMotion()) {
                    card.dataset.entering = '';
                    requestAnimationFrame(() => requestAnimationFrame(() => delete card.dataset.entering));
                }

                return card;
            }));
        });

        entering.clear();
        const none = addresses.length === 0;

        if (empty) {
            empty.hidden = !none;
        }

        root.querySelectorAll('[data-add-when-filled]').forEach((node) => {
            node.hidden = none;
        });
        root.dispatchEvent(new CustomEvent('address-book:change', { bubbles: true, detail: { addresses, selected: selection() } }));
    };

    const titleFor = (isEdit) => {
        const key = mode === 'select'
            ? `title${purpose === 'billing' ? 'Billing' : 'Shipping'}${isEdit ? 'Edit' : 'New'}`
            : `title${isEdit ? 'Edit' : 'New'}`;

        return modal.dataset[key] ?? modal.dataset[isEdit ? 'titleEdit' : 'titleNew'] ?? '';
    };

    const openForm = (address = null) => {
        if (!form) {
            return;
        }

        editing = address;
        form.reset();
        clearErrors(form);
        resetMapNotices(form);
        form.querySelector('[data-form-error]')?.setAttribute('hidden', '');

        const set = (name, value) => {
            const field = form.querySelector(`[name="${name}"]:not([type="hidden"]), input[type="hidden"][name="${name}"]`);

            if (field) {
                field.value = value ?? '';
            }
        };

        ['full_name', 'label', 'email', 'address_line_1', 'city', 'state', 'latitude', 'longitude'].forEach((name) => set(name, address?.[name] ?? ''));
        const country = form.querySelector('select[data-country-select]');
        if (country) {
            country.value = address?.country_id ? String(address.country_id) : '';
            country.closest('[data-sf-combobox]')?.__combobox?.render();
        }
        const isDefault = form.querySelector('input[type="checkbox"][name="is_default"]');
        if (isDefault) {
            isDefault.checked = Boolean(address?.is_default) || (!address && addresses.length === 0);
        }
        const phone = form.querySelector('input[data-phone-input]');
        if (phone) {
            setPhone(phone, address?.phone ?? '', address?.phone_country || config.phoneCountry);
        }

        form.action = address ? (urls.update || form.dataset.updateUrl).replace('__ID__', address.id) : (urls.store || form.dataset.storeUrl);
        form.querySelector('[data-method-field]').value = address ? 'PUT' : 'POST';
        const submit = form.querySelector('[data-submit-label]');
        const idle = submit?.querySelector('.sf-btn-idle') ?? submit;
        if (idle && submit) {
            idle.textContent = address ? submit.dataset.labelUpdate : submit.dataset.labelSave;
        }
        const title = modal.querySelector('[data-modal-title]');
        if (title) {
            title.textContent = titleFor(Boolean(address));
        }

        openSheet(modal);
    };

    const choose = (group, id) => {
        const list = root.querySelector(`[data-address-group="${group}"] [data-address-list]`);
        const radio = list?.querySelector(`input[data-address-radio][value="${CSS.escape(String(id))}"]`);

        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        const banner = form.querySelector('[data-form-error]');
        const data = new FormData(form); // phone already rewritten to E.164 (capture listener)
        const previous = selection();
        submit?.setAttribute('data-loading', '');
        submit?.setAttribute('aria-busy', 'true');
        banner?.setAttribute('hidden', '');

        try {
            const payload = await http.request('POST', form.action, { data, form });
            const saved = payload?.data?.address;
            addresses = payload?.data?.addresses ?? addresses;

            if (saved && !editing) {
                entering.add(String(saved.id));
            }

            if (saved && mode === 'select') {
                previous[purpose] = saved.id;
                if (purpose === 'shipping' && root.querySelector('[data-billing-same]')?.checked) {
                    previous.billing = saved.id;
                }
            }

            render(previous);
            closeSheet(modal);
        } catch (error) {
            if (error?.status === 422 && banner) {
                banner.removeAttribute('hidden');
            }
        } finally {
            submit?.removeAttribute('data-loading');
            submit?.removeAttribute('aria-busy');
        }
    });

    let pendingDelete = null;
    const confirm = deleteModal?.querySelector('[data-confirm-delete]');

    confirm?.addEventListener('click', async () => {
        if (!pendingDelete) {
            return;
        }

        confirm.dataset.loading = '';
        confirm.setAttribute('aria-busy', 'true');

        try {
            const payload = await http.delete(urls.destroy.replace('__ID__', pendingDelete.id));
            closeSheet(deleteModal);
            await leave(root.querySelector(`[data-address-card="${CSS.escape(String(pendingDelete.id))}"]`));
            addresses = payload?.data?.addresses ?? addresses.filter((address) => address.id !== pendingDelete.id);
            render();
        } catch {
            // the http client already toasts the failure
        } finally {
            delete confirm.dataset.loading;
            confirm.removeAttribute('aria-busy');
            pendingDelete = null;
        }
    });

    root.addEventListener('click', async (event) => {
        const add = event.target.closest('[data-address-add]');
        const edit = event.target.closest('[data-address-edit]');
        const makeDefault = event.target.closest('[data-address-default]');
        const remove = event.target.closest('[data-address-delete]');
        const find = (id) => addresses.find((address) => String(address.id) === String(id));

        if (add) {
            purpose = add.dataset.addressPurpose ?? 'shipping';
            openForm(null);
        } else if (edit) {
            event.preventDefault();
            purpose = edit.closest('[data-address-group]')?.dataset.addressGroup ?? 'shipping';
            openForm(find(edit.dataset.addressEdit) ?? null);
        } else if (makeDefault && urls.default) {
            makeDefault.disabled = true;

            try {
                const payload = await http.post(urls.default.replace('__ID__', makeDefault.dataset.addressDefault));
                addresses = payload?.data?.addresses ?? addresses;
                render();
            } catch {
                makeDefault.disabled = false;
            }
        } else if (remove && deleteModal) {
            pendingDelete = find(remove.dataset.addressDelete);

            if (!pendingDelete) {
                return;
            }

            const message = deleteModal.querySelector('[data-delete-message]');
            if (message) {
                message.textContent = message.dataset.template.replace('__ADDRESS__', pendingDelete.label || pendingDelete.one_line || pendingDelete.full_name);
            }
            openSheet(deleteModal);
        }
    });

    // Header "Add address" (outside the list root on the account page).
    if (mode === 'manage') {
        document.querySelectorAll('[data-address-add]').forEach((button) => {
            if (!root.contains(button)) {
                button.addEventListener('click', () => {
                    purpose = 'shipping';
                    openForm(null);
                });
            }
        });
    }

    root.__addressBook = {
        get addresses() {
            return addresses;
        },
        selection,
        choose,
        render,
        add: (forPurpose = 'shipping') => {
            purpose = forPurpose;
            openForm(null);
        },
    };

    return root.__addressBook;
}

export function initAddressBooks(root = document) {
    root.querySelectorAll('[data-address-book]').forEach(initAddressBook);
}
