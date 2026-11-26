/**
 * Product AI tools of the vendor product pages (OpenAI only, plan "AI calls"):
 *  - VEN-010b AI price finder [data-ai-price]: market search for the chosen selling
 *    country → average / median / range + listings; "Use $x" only fills the price field
 *    of the own-product form (or copies the price) — the tool never saves a price;
 *  - VEN-010c AI social posts [data-ai-social]: platform + language → editable caption
 *    with hashtags, Copy post, Download image.
 * States: loading, result, empty, limit reached (429 + upgrade link), provider error.
 */
import { http } from '../../core/http';
import { notify } from '../../core/notify';

const busy = (button, on) => {
    if (!button) {
        return;
    }

    button.toggleAttribute('data-loading', on);
    button.setAttribute('aria-busy', on ? 'true' : 'false');
    button.disabled = on;
};

const show = (root, state) => {
    ['loading', 'result', 'empty', 'limit', 'error'].forEach((name) => {
        const node = root.querySelector(`[data-ai-${name}]`);

        if (node) {
            node.hidden = name !== state;
        }
    });
};

const fail = (root, error, texts) => {
    if (error?.status === 429 && error.payload?.data?.quota) {
        root.querySelector('[data-ai-limit-text]').textContent = error.payload.message ?? '';
        root.querySelector('[data-ai-quota]').textContent = error.payload.data.quota.label ?? '';
        show(root, 'limit');

        return;
    }

    root.querySelector('[data-ai-error]').textContent = error?.payload?.message || texts.textGeneric;
    show(root, 'error');
};

const copy = async (text) => {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        return false;
    }
};

function bindPrice(root) {
    const modal = root.closest('[role="dialog"]') ?? document;
    const search = root.querySelector('[data-ai-price-search]');
    const use = modal.querySelector('[data-ai-price-use]');
    const list = root.querySelector('[data-ai-hits]');
    const template = root.querySelector('[data-ai-hit]');
    const texts = root.dataset;
    let suggested = null;

    search?.addEventListener('click', async () => {
        const country = root.querySelector('select[name="ai_price_country"]')?.value;
        busy(search, true);
        use.hidden = true;
        show(root, 'loading');

        try {
            const { data } = await http.post(root.dataset.url, { country_id: country }, { silent: true, toast: false });
            const result = data.result;
            root.querySelector('[data-ai-quota]').textContent = data.quota?.label ?? '';

            if (!result.sample_size) {
                show(root, 'empty');

                return;
            }

            root.querySelector('[data-ai-stat="average"]').textContent = result.labels.average ?? '—';
            root.querySelector('[data-ai-stat="median"]').textContent = result.labels.median ?? '—';
            root.querySelector('[data-ai-stat="range"]').textContent = `${result.labels.min ?? ''}–${result.labels.max ?? ''}`;
            list.replaceChildren(...result.hits.map((hit) => {
                const node = template.content.firstElementChild.cloneNode(true);
                const link = node.querySelector('[data-hit-link]');
                link.href = hit.url;
                node.querySelector('[data-hit-title]').textContent = [hit.source, hit.title].filter(Boolean).join(' · ');
                node.querySelector('[data-hit-price]').textContent = hit.price_label;

                return node;
            }));
            const suggestion = root.querySelector('[data-ai-suggestion]');
            suggestion.textContent = suggestion.dataset.template.replace('__PRICE__', result.labels.suggested ?? '');
            suggested = result.suggested;
            use.querySelector('.ui-btn-label').textContent = texts.textUse.replace('__PRICE__', result.labels.suggested ?? '');
            use.hidden = suggested === null;
            use.dataset.label = result.labels.suggested ?? '';
            show(root, 'result');
        } catch (error) {
            fail(root, error, texts);
        } finally {
            busy(search, false);
        }
    });

    use?.addEventListener('click', async () => {
        if (suggested === null) {
            return;
        }

        const amount = (suggested / 100).toFixed(2);
        const field = document.querySelector('form input[name="price"]');

        if (field) {
            field.value = amount;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            modal.querySelector('[data-overlay-close]')?.click();
            field.focus();

            return;
        }

        await copy(amount);
        notify.success(texts.textCopied.replace('__PRICE__', use.dataset.label));
    });
}

function bindSocial(root) {
    const modal = root.closest('[role="dialog"]') ?? document;
    const generate = modal.querySelector('[data-ai-social-generate]');
    const copyButton = modal.querySelector('[data-ai-social-copy]');
    const text = root.querySelector('textarea[name="ai_social_text"]');
    const image = root.querySelector('[data-ai-social-image]');
    const download = root.querySelector('[data-ai-social-download]');
    const noImage = root.querySelector('[data-ai-social-no-image]');
    const texts = root.dataset;
    generate.hidden = false;

    generate.addEventListener('click', async () => {
        const platform = root.querySelector('[data-ai-social-platform]:checked')?.value;
        const locale = root.querySelector('select[name="ai_social_locale"]')?.value;
        busy(generate, true);
        copyButton.hidden = true;
        show(root, 'loading');

        try {
            const { data } = await http.post(root.dataset.url, { platform, locale }, { silent: true, toast: false });
            const post = data.post;
            text.value = post.text;
            text.setAttribute('dir', ['ar', 'fa', 'he', 'ur'].includes(post.locale) ? 'rtl' : 'ltr');
            root.querySelector('[data-ai-social-media]').hidden = !post.image;
            noImage.hidden = Boolean(post.image);

            if (post.image) {
                image.src = post.image;
                download.href = post.image;
            }

            root.querySelector('[data-ai-quota]').textContent = data.quota?.label ?? '';
            generate.querySelector('.ui-btn-label').textContent = generate.dataset.labelAgain;
            copyButton.hidden = false;
            show(root, 'result');
        } catch (error) {
            fail(root, error, texts);
        } finally {
            busy(generate, false);
        }
    });

    copyButton.addEventListener('click', async () => {
        if (await copy(text.value)) {
            notify.success(texts.textCopied);
        } else {
            text.select();
        }
    });
}

export default function initAiTools() {
    document.querySelectorAll('[data-ai-price]:not([data-ai-bound])').forEach((root) => {
        root.dataset.aiBound = '';
        bindPrice(root);
    });
    document.querySelectorAll('[data-ai-social]:not([data-ai-bound])').forEach((root) => {
        root.dataset.aiBound = '';
        bindSocial(root);
    });
}
