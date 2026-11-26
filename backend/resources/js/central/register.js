/**
 * Central › complete registration (WEB-018 / 018a) and provisioning (WEB-020).
 *
 *  - every form: submit spinner + double-submit guard, password show/hide
 *  - payment step: the shared inline Stripe card (core/payments/stripe-card.js)
 *  - store step: sub-domain / own-domain switch, slug clean-up + suggestion from the
 *    store name, debounced live availability (GET register/availability) and the
 *    "Check DNS" lookup, and the store categories multi-select (Select2, lazy-loaded)
 *  - provisioning: polls the signed status URL and ticks the checklist
 *
 * Motion is CSS-driven (resources/css/central/register.css) and reduced-motion aware.
 */
import { http } from '../core/http.js';
import initSignup from './signup.js';

const DEBOUNCE_MS = 400;
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const readConfig = (root, selector) => {
    const node = root.querySelector(selector);

    try {
        return node ? JSON.parse(node.textContent) : null;
    } catch {
        return null;
    }
};

function bindSubmit(form) {
    form.addEventListener('submit', (event) => {
        const button = form.querySelector('[data-submit]');

        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = '1';

        if (button) {
            button.disabled = true;
            button.querySelector('[data-spinner]')?.classList.remove('hidden');
        }
    });

    // Back/forward cache: re-enable the button when the page is shown again.
    window.addEventListener('pageshow', () => {
        form.dataset.submitting = '0';
        const button = form.querySelector('[data-submit]');

        if (button) {
            button.disabled = false;
            button.querySelector('[data-spinner]')?.classList.add('hidden');
        }
    });
}

function bindPasswordToggles(root) {
    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = root.querySelector(`#${button.dataset.passwordToggle}`);

        button.addEventListener('click', (event) => {
            event.preventDefault();
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? button.dataset.labelHide : button.dataset.labelShow);
            button.classList.toggle('text-primary', show);
            input.focus();
        });
    });
}

/** Message line + border + status icon of one floating field. */
function fieldStatus(form, name) {
    const wrapper = form.querySelector(`[data-field="${name}"]`);
    const box = wrapper?.querySelector('.reg-field');
    const message = wrapper?.querySelector(`[data-message-for="${name}"]`);
    const icon = wrapper?.querySelector('[data-status-icon]');
    const input = wrapper?.querySelector('input');

    const set = (state, text = '') => {
        if (!wrapper) {
            return;
        }

        if (state === 'idle') {
            box.removeAttribute('data-state');
        } else {
            box.dataset.state = state;
        }

        input.toggleAttribute('aria-invalid', state === 'error');
        message.hidden = text === '';
        message.textContent = text;
        message.toggleAttribute('data-pending', state === 'checking');
        message.classList.toggle('text-danger', state === 'error');
        message.classList.toggle('text-success', state === 'ok');
        message.classList.toggle('text-(--reg-muted)', state === 'checking');

        if (icon) {
            icon.innerHTML = '';

            const glyph = document.createElement('span');

            if (state === 'checking') {
                glyph.className = 'block size-4 animate-spin rounded-full border-2 border-primary/25 border-t-primary';
            } else if (state === 'ok') {
                glyph.className = 'reg-pop grid size-5 place-items-center rounded-full bg-success text-white';
                glyph.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
            } else if (state === 'error') {
                glyph.className = 'reg-pop grid size-5 place-items-center rounded-full bg-danger text-white';
                glyph.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
            }

            if (glyph.className) {
                icon.append(glyph);
            }
        }
    };

    return { set, input };
}

/** Latin store name → a valid DNS label suggestion ("Megz Kids" → "megzkids"). */
const slugFromName = (name) =>
    name
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '')
        .slice(0, 63);

const cleanSlug = (value) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^-+/, '')
        .slice(0, 63);

function initStoreForm(root) {
    const form = root.querySelector('[data-store-form]');
    const config = readConfig(root, '[data-register-config]');

    if (!form || !config) {
        return;
    }

    // Store categories: Select2 (search) is loaded only on this step.
    if (form.querySelector('select[data-select]')) {
        import('../panels/select2.js').then(({ initSelect2 }) => initSelect2(form));
    }

    const segment = form.querySelector('[data-segment]');
    const panel = form.querySelector('[data-custom-panel]');
    const storeName = form.querySelector('[name="store_name"]');
    const slug = fieldStatus(form, 'slug');
    const domain = fieldStatus(form, 'custom_domain');
    const dnsButton = form.querySelector('[data-dns-check]');
    const dnsResult = form.querySelector('[data-dns-result]');
    let slugTouched = slug.input.value !== '';
    const controllers = {};
    const timers = {};

    const check = (key, params, status, onResult) => {
        clearTimeout(timers[key]);
        controllers[key]?.abort();

        timers[key] = setTimeout(async () => {
            const controller = new AbortController();
            controllers[key] = controller;
            status.set('checking', config.i18n.checking);

            try {
                const result = await http.get(config.availabilityUrl, { token: config.token, ...params }, { signal: controller.signal, toast: false, silent: true, followRedirect: false });
                status.set(result.available ? 'ok' : 'error', result.message ?? '');
                onResult?.(result);
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                status.set('error', error?.payload?.message ?? error?.errors?.slug?.[0] ?? config.i18n.checkFailed);
            }
        }, DEBOUNCE_MS);
    };

    const checkSlug = () => {
        const value = slug.input.value;

        if (value === '') {
            slug.set('idle');
            return;
        }

        check('slug', { slug: value }, slug);
    };

    const checkDomain = () => {
        const value = domain.input.value.trim();
        dnsResult.hidden = true;

        if (value === '') {
            domain.set('idle');
            return;
        }

        check('domain', { domain: value }, domain);
    };

    // Domain type switch (sliding pill + collapsing custom-domain panel).
    form.querySelectorAll('[name="domain_type"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const custom = radio.value === 'custom' && radio.checked;
            segment.dataset.value = radio.value;
            panel.toggleAttribute('data-collapsed', !custom);
            panel.toggleAttribute('inert', !custom);

            if (custom) {
                setTimeout(() => domain.input.focus({ preventScroll: true }), reduceMotion() ? 0 : 200);
            }
        });
    });

    storeName?.addEventListener('input', () => {
        if (slugTouched) {
            return;
        }

        const suggestion = slugFromName(storeName.value);

        if (suggestion !== slug.input.value) {
            slug.input.value = suggestion;
            checkSlug();
        }
    });

    slug.input.addEventListener('input', () => {
        const cleaned = cleanSlug(slug.input.value);

        if (cleaned !== slug.input.value) {
            slug.input.value = cleaned;
        }

        slugTouched = cleaned !== '';
        checkSlug();
    });

    domain.input.addEventListener('input', checkDomain);

    dnsButton?.addEventListener('click', async (event) => {
        event.preventDefault();
        const value = domain.input.value.trim();

        if (value === '') {
            domain.input.focus();
            return;
        }

        dnsButton.disabled = true;
        dnsButton.querySelector('[data-spinner]')?.classList.remove('hidden');

        try {
            const result = await http.get(
                config.availabilityUrl,
                { token: config.token, domain: value, check: 'dns', for: slug.input.value },
                { toast: false, silent: true, followRedirect: false },
            );
            domain.set(result.available ? 'ok' : 'error', result.message ?? '');

            if (result.available && Array.isArray(result.records)) {
                renderDns(result.records, result.subdomain);
            }
        } catch (error) {
            domain.set('error', error?.payload?.message ?? config.i18n.dnsError);
        } finally {
            dnsButton.disabled = false;
            dnsButton.querySelector('[data-spinner]')?.classList.add('hidden');
        }
    });

    function renderDns(records, subdomain) {
        const list = dnsResult.querySelector('[data-dns-records]');
        list.replaceChildren();

        records.forEach((record, index) => {
            const item = document.createElement('li');
            item.className = 'reg-pop flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] leading-[18px] text-ink';
            item.style.animationDelay = `${index * 60}ms`;

            const state = record.found === null ? 'later' : record.found ? 'found' : 'missing';
            const tone = { found: 'text-success', missing: 'text-danger', later: 'text-(--reg-muted)' }[state];
            const label = { found: config.i18n.dnsFound, missing: config.i18n.dnsMissing, later: config.i18n.dnsAfterCreation }[state];

            const dot = document.createElement('span');
            dot.className = `size-2 shrink-0 rounded-full ${state === 'found' ? 'bg-success' : state === 'missing' ? 'bg-danger' : 'bg-ink-muted'}`;
            dot.setAttribute('aria-hidden', 'true');

            const text = document.createElement('span');
            text.dir = 'ltr';
            text.className = 'min-w-0 flex-1 break-all';
            text.textContent = `${record.type}  ${record.host}${record.value ? `  →  ${record.value}` : ''}`;

            const status = document.createElement('span');
            status.className = `ms-auto font-medium ${tone}`;
            status.textContent = label;

            item.append(dot, text, status);
            list.append(item);
        });

        dnsResult.querySelector('[data-dns-note]').textContent = config.i18n.dnsNote.replace(':host', subdomain ?? '');
        dnsResult.hidden = false;
    }

    // Re-check pre-filled values (e.g. after a validation round-trip) without errors flashing.
    if (slug.input.value !== '' && !form.querySelector('[data-field="slug"] [data-state="error"]')) {
        checkSlug();
    }
}

function initProvisioning(root) {
    const page = root.querySelector('[data-provisioning]');
    const config = readConfig(root, '[data-provisioning-config]');

    if (!page || !config) {
        return;
    }

    const steps = [...page.querySelectorAll('[data-step]')];
    const doneLabel = config.doneLabel ?? '';
    let reached = config.phase === 'success' ? config.steps : 0;
    let phase = config.phase;
    let polls = 0;

    const paint = () => {
        steps.forEach((step) => {
            const index = Number(step.dataset.step);
            const tick = step.querySelector('.reg-tick');
            const done = index < reached;
            tick.toggleAttribute('data-done', done);
            tick.toggleAttribute('data-active', phase === 'progress' && index === reached);
            step.querySelector('[data-step-state]').textContent = done ? doneLabel : '';
        });
    };

    const show = (next) => {
        phase = next;
        page.dataset.phase = next;
        page.querySelectorAll('[data-when]').forEach((node) => {
            node.hidden = node.dataset.when !== next;
        });
        page.querySelectorAll('[data-when-not]').forEach((node) => {
            node.hidden = node.dataset.whenNot === next;
        });
        paint();
    };

    // While the job runs, the checklist advances step by step but never claims the last
    // step until the server says the store is live.
    const advance = setInterval(() => {
        if (phase !== 'progress') {
            clearInterval(advance);
            return;
        }

        reached = Math.min(reached + 1, config.steps - 1);
        paint();
    }, reduceMotion() ? 2400 : 1600);

    const finish = () => {
        const tick = () => {
            reached += 1;
            paint();

            if (reached < config.steps) {
                setTimeout(tick, reduceMotion() ? 0 : 260);
            } else {
                setTimeout(() => show('success'), reduceMotion() ? 0 : 450);
            }
        };

        tick();
    };

    const poll = async () => {
        polls += 1;

        try {
            const status = await http.get(config.statusUrl, null, { toast: false, silent: true, followRedirect: false });

            if (status.done) {
                clearInterval(advance);
                phase = 'finishing';
                finish();
                return;
            }

            if (status.failed) {
                clearInterval(advance);
                show('failed');
                return;
            }
        } catch {
            // Network hiccup or expired signature: keep trying a little slower.
        }

        setTimeout(poll, polls < 60 ? 2500 : 10000);
    };

    paint();

    if (phase === 'progress') {
        setTimeout(poll, 1200);
    }
}

export default function initRegister(root = document) {
    root.querySelectorAll('[data-register-form]').forEach(bindSubmit);

    // Payment step: inline Stripe card (decision #78 b), Stripe.js only loaded when chosen.
    if (root.querySelector('form [data-stripe-card]')) {
        import('../core/payments/stripe-card.js').then(({ bindStripeCardForms }) => bindStripeCardForms(root));
    }

    bindPasswordToggles(root);
    initStoreForm(root);
    initProvisioning(root);
    initSignup(root);
}
