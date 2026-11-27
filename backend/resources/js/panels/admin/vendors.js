/**
 * Admin › Vendors pages (list, create wizard, profile):
 *  - [data-export-for="vendors"]   export link that carries the table's current filters.
 *  - form[data-vendor-wizard]      4-step create wizard: step navigation, live sub-domain
 *                                  check (throttled server-side), owner summary, review,
 *                                  and a 422 jumps back to the step holding the error.
 *  - [data-vendor-reload]          confirm buttons that reload the profile when done.
 *  - form[data-vendor-reload-form] ajax forms that reload the page after success.
 *  - [data-vendor-post]            menu item that POSTs to data-url, then reloads.
 *  - #assign-plan-form             Billing assign form on the profile: stays on the profile.
 *  - form[data-vendor-impersonate] posts, then opens the returned vendor panel URL.
 *  - [data-commission-form]        platform share = 100 − vendor profit, live.
 *  - form[data-limits-form]        limit overrides (ADM-041h): the number field shows for
 *                                  "custom" only, the effective column follows live and
 *                                  [data-limits-reset] sets every row back to "use plan".
 *  - ?open=edit-store|impersonate  opens that modal on load (row actions of the list).
 */
import { collectFilters } from '../ui/filters';
import { openOverlay, closeOverlay } from '../ui/overlays';
import { http } from '../../core/http';
import { notify } from '../../core/notify';
import { numberLocale } from '../../core/i18n';

function bindExportLinks(root) {
    root.querySelectorAll('a[data-export-for]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const url = new URL(link.href, window.location.origin);

            Object.entries(collectFilters(link.dataset.exportFor)).forEach(([key, value]) => {
                (Array.isArray(value) ? value : [value]).forEach((item) => {
                    if (item !== '' && item !== null && item !== undefined) {
                        url.searchParams.append(Array.isArray(value) ? `${key}[]` : key, item);
                    }
                });
            });

            window.location.assign(url.toString());
        });
    });
}

/* ---------------------------------------------------------------- wizard */

function fieldText(form, name) {
    const field = form.querySelector(`[name="${name}"], [name="${name}[]"]`);

    if (!field) {
        return '';
    }

    if (field.tagName === 'SELECT') {
        return [...field.selectedOptions]
            .filter((option) => option.value !== '')
            .map((option) => option.textContent.trim())
            .join(', ');
    }

    if (field.type === 'radio') {
        const checked = form.querySelector(`[name="${name}"]:checked`);

        return checked ? (checked.closest('label')?.textContent.trim() ?? checked.value) : '';
    }

    if (name === 'owner_phone') {
        const e164 = form.querySelector('[name="owner_phone_e164"]')?.value;

        return (e164 || field.value).trim();
    }

    return field.value.trim();
}

function bindSlugCheck(form) {
    const input = form.querySelector('[data-slug-input]');
    const status = form.querySelector('[data-slug-status]');

    if (!input || !status) {
        return;
    }

    let timer = null;
    let controller = null;

    const show = (text, tone) => {
        status.hidden = text === '';
        status.textContent = text;
        status.classList.toggle('text-success', tone === 'success');
        status.classList.toggle('text-danger', tone === 'danger');
        status.classList.toggle('text-ink-secondary', tone === 'muted');
    };

    input.addEventListener('input', () => {
        const slug = input.value.toLowerCase().replace(/[^a-z0-9-]/g, '');

        if (slug !== input.value) {
            input.value = slug;
        }

        window.clearTimeout(timer);
        controller?.abort();

        if (slug === '') {
            show('', 'muted');

            return;
        }

        show(status.dataset.checking, 'muted');
        timer = window.setTimeout(async () => {
            controller = new AbortController();

            try {
                const result = await http.get(form.dataset.slugUrl, { slug }, { silent: true, toast: false, signal: controller.signal });
                show(result.message, result.available ? 'success' : 'danger');
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    show(status.dataset.failed, 'muted');
                }
            }
        }, 400);
    });
}

function bindWizard(form) {
    const steps = [...form.querySelectorAll('[data-wizard-step]')];
    const markers = [...form.querySelectorAll('[data-wizard-goto]')];
    const lines = [...form.querySelectorAll('[data-wizard-line]')];
    const prev = form.querySelector('[data-wizard-prev]');
    const next = form.querySelector('[data-wizard-next]');
    const submit = form.querySelector('[data-wizard-submit]');
    const counter = form.querySelector('[data-wizard-counter]');
    const review = form.querySelector('[data-review]');
    const owner = form.querySelector('[data-owner-summary]');
    let current = 0;

    const refreshSummaries = () => {
        owner?.querySelectorAll('[data-owner-value]').forEach((cell) => {
            cell.textContent = fieldText(form, cell.dataset.ownerValue) || owner.dataset.empty;
        });

        review?.querySelectorAll('[data-review-value]').forEach((cell) => {
            const key = cell.dataset.reviewValue;
            let text = fieldText(form, key);

            if (key === 'slug' && text) {
                text = `${text}.${form.dataset.baseDomain}`;
            }

            if (key === 'profit_percent' && text) {
                text = `${Number(text).toLocaleString(numberLocale())}%`;
            }

            cell.textContent = text || review.dataset.empty;
        });
    };

    const go = (index) => {
        current = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach((step, i) => {
            step.hidden = i !== current;
        });
        markers.forEach((marker, i) => {
            marker.dataset.state = i < current ? 'done' : i === current ? 'current' : 'upcoming';
            marker.toggleAttribute('aria-current', i === current);
            if (i === current) {
                marker.setAttribute('aria-current', 'step');
            }
        });
        lines.forEach((line, i) => {
            line.classList.toggle('bg-success', i < current);
            line.classList.toggle('bg-line', i >= current);
        });
        prev.hidden = current === 0;
        next.hidden = current === steps.length - 1;
        submit.hidden = current !== steps.length - 1;

        if (counter) {
            counter.textContent = counter.dataset.template.replace(':current', (current + 1).toLocaleString(numberLocale()));
        }

        refreshSummaries();
    };

    /** Native validity of the visible step's own inputs (server validation stays authoritative). */
    const stepValid = () => {
        const fields = [...steps[current].querySelectorAll('input, select, textarea')].filter((field) => field.willValidate && field.type !== 'hidden');
        const invalid = fields.find((field) => !field.checkValidity());

        if (invalid) {
            invalid.reportValidity();

            return false;
        }

        return true;
    };

    next.addEventListener('click', () => {
        if (stepValid()) {
            go(current + 1);
        }
    });
    prev.addEventListener('click', () => go(current - 1));
    markers.forEach((marker, index) => {
        marker.addEventListener('click', () => {
            if (index <= current || stepValid()) {
                go(index);
            }
        });
    });

    form.addEventListener('input', refreshSummaries);
    form.addEventListener('change', refreshSummaries);

    form.addEventListener('form:errors', (event) => {
        const keys = event.detail?.keys ?? [];
        const index = steps.findIndex((step) =>
            keys.some((key) => {
                const name = key.split('.')[0];

                return step.querySelector(`[name="${name}"], [name="${name}[]"]`) !== null;
            }),
        );

        if (index >= 0 && index !== current) {
            go(index);
            steps[index].querySelector('[aria-invalid="true"]')?.focus();
        }
    });

    bindSlugCheck(form);
    go(0);
}

/* ---------------------------------------------------------------- profile */

function bindReloads() {
    document.addEventListener('confirm:done', (event) => {
        if (event.target.closest?.('[data-vendor-reload]')) {
            window.location.reload();
        }
    });

    document.querySelectorAll('form[data-vendor-reload-form]').forEach((form) => {
        form.addEventListener('ajax:success', (event) => {
            if (!event.detail?.redirect) {
                window.location.reload();
            }
        });
    });

    document.querySelectorAll('[data-vendor-post]').forEach((item) => {
        item.addEventListener('click', async () => {
            try {
                await http.post(item.dataset.url, null);
                window.location.reload();
            } catch {
                // http.js already shows the error toast
            }
        });
    });
}

/**
 * The Billing assign form answers with a redirect to the subscriptions list; on the
 * profile the admin stays here, so it is submitted without following the redirect.
 */
function bindAssignPlan() {
    const form = document.getElementById('assign-plan-form');

    if (!form) {
        return;
    }

    form.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();
            const button = document.querySelector('[form="assign-plan-form"][type="submit"]');
            button?.setAttribute('data-loading', '');

            try {
                await http.post(form.action, new FormData(form), { form, followRedirect: false });
                await closeOverlay(form.closest('[data-overlay]'), { force: true });
                window.location.reload();
            } catch {
                // errors are rendered on the form by http.js
            } finally {
                button?.removeAttribute('data-loading');
            }
        },
        true,
    );
}

function bindImpersonate() {
    const form = document.querySelector('form[data-vendor-impersonate]');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.querySelector('[form="impersonate-form"][type="submit"]');
        button?.setAttribute('data-loading', '');
        const reason = form.querySelector('[name="reason"]')?.value ?? '';
        const details = form.querySelector('[name="details"]')?.value.trim() ?? '';
        // Opened synchronously inside the submit so pop-up blockers allow it; skipped when
        // the server is going to answer with a validation error anyway.
        const tab = reason !== '' && (reason !== 'other' || details !== '') ? window.open('', '_blank') : null;

        try {
            const payload = await http.post(form.action, new FormData(form), { form, followRedirect: false });

            if (!payload?.redirect) {
                tab?.close();

                return;
            }

            await closeOverlay(form.closest('[data-overlay]'), { force: true });
            form.reset();

            if (tab && !tab.closed) {
                tab.opener = null;
                tab.location.href = payload.redirect;
            } else if (tab === null) {
                window.location.assign(payload.redirect);
            } else {
                notify.warning(form.dataset.blocked);
                window.location.assign(payload.redirect);
            }
        } catch {
            tab?.close();
        } finally {
            button?.removeAttribute('data-loading');
        }
    });
}

function bindCommission(root) {
    root.querySelectorAll('[data-commission-form]').forEach((form) => {
        const profit = form.querySelector('[data-profit-input]');
        const share = form.querySelector('[data-platform-share]');

        if (!profit || !share) {
            return;
        }

        const update = () => {
            const value = Number.parseFloat(profit.value);
            share.value = Number.isFinite(value) && value >= 0 && value <= 100 ? (100 - value).toFixed(2) : '';
        };

        profit.addEventListener('input', update);
    });
}

function bindLimitOverrides(root) {
    root.querySelectorAll('form[data-limits-form]').forEach((form) => {
        const formatter = new Intl.NumberFormat(numberLocale());
        const rows = [...form.querySelectorAll('[data-limit-row]')];

        const sync = (row) => {
            const mode = row.querySelector('select[data-limit-mode]');
            const valueField = row.querySelector('[data-limit-value]');
            const input = valueField?.querySelector('input');
            const effective = row.querySelector('[data-limit-effective] bdi');

            if (!mode || !valueField || !input || !effective) {
                return;
            }

            const isCustom = mode.value === 'custom';
            valueField.hidden = !isCustom;
            input.required = isCustom;

            if (mode.value === 'unlimited') {
                effective.textContent = form.dataset.unlimited;
            } else if (isCustom) {
                const value = Number.parseInt(input.value, 10);
                effective.textContent = Number.isFinite(value) && value >= 0 ? formatter.format(value) : '—';
            } else {
                effective.textContent = row.dataset.planText;
            }
        };

        rows.forEach((row) => {
            row.querySelector('select[data-limit-mode]')?.addEventListener('change', () => sync(row));
            row.querySelector('[data-limit-value] input')?.addEventListener('input', () => sync(row));
            sync(row);
        });

        form.querySelector('[data-limits-reset]')?.addEventListener('click', () => {
            rows.forEach((row) => {
                const select = row.querySelector('select[data-limit-mode]');

                if (select) {
                    select.value = 'plan';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    });
}

function openRequestedOverlay(root) {
    const profile = root.querySelector('[data-vendor-profile]');

    if (!profile) {
        return;
    }

    const url = new URL(window.location.href);
    const target = url.searchParams.get(profile.dataset.openParam);

    if (target && ['edit-store', 'impersonate'].includes(target) && document.getElementById(target)) {
        openOverlay(target);
    }

    if (target) {
        url.searchParams.delete(profile.dataset.openParam);
        window.history.replaceState(window.history.state, '', url);
    }
}

export default function initVendors(root) {
    bindExportLinks(root);
    root.querySelectorAll('form[data-vendor-wizard]').forEach(bindWizard);
    bindReloads();
    bindAssignPlan();
    bindImpersonate();
    bindCommission(root);
    bindLimitOverrides(root);
    openRequestedOverlay(root);
}
