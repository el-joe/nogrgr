/**
 * VEN-034a AI logo generator (Appearance › Logo, [data-ai-logo]): Generate → four logo
 * options (OpenAI images, plan "AI logos"); choosing one enables "Use as store logo",
 * which replaces the store logo and refreshes the "Current logo" preview.
 * States: idle, generating, options, limit reached (429), provider error.
 */
import { http } from '../../core/http.js';
import { notify } from '../../core/notify.js';

const show = (root, state) => {
    ['empty', 'loading', 'result', 'limit', 'error'].forEach((name) => {
        const node = root.querySelector(`[data-ai-${name}]`);

        if (node) {
            node.hidden = name !== state;
        }
    });
};

const busy = (button, on) => {
    button.toggleAttribute('data-loading', on);
    button.setAttribute('aria-busy', on ? 'true' : 'false');
    button.disabled = on;
};

function bind(root) {
    const generate = root.querySelector('[data-ai-logo-generate]');
    const apply = root.querySelector('[data-ai-logo-apply]');
    const list = root.querySelector('[data-ai-logo-options]');
    const template = root.querySelector('[data-ai-logo-option]');
    const texts = root.dataset;
    const subtitle = root.closest('.ui-card, section, article')?.querySelector('.ui-card-subtitle, [data-card-subtitle]');

    root.addEventListener('submit', async (event) => {
        event.preventDefault();
        busy(generate, true);
        apply.disabled = true;
        show(root, 'loading');

        try {
            const { data } = await http.post(texts.generateUrl, new FormData(root), { silent: true, toast: false, form: root });
            list.replaceChildren(...data.options.map((option, index) => {
                const node = template.content.firstElementChild.cloneNode(true);
                const input = node.querySelector('input');
                input.value = option.key;
                node.querySelector('[data-option-image]').src = option.url;
                node.querySelector('[data-option-label]').textContent = texts.textOption.replace('__N__', String(index + 1));

                return node;
            }));

            if (subtitle && data.used_label) {
                subtitle.textContent = data.used_label;
            }

            generate.querySelector('.ui-btn-label').textContent = texts.textAgain;
            show(root, 'result');
        } catch (error) {
            if (error?.status === 429) {
                root.querySelector('[data-ai-limit-text]').textContent = error.payload?.message ?? '';
                show(root, 'limit');
            } else if (error?.status === 422 && error.payload?.errors) {
                show(root, 'empty');
            } else {
                root.querySelector('[data-ai-error]').textContent = error?.payload?.message || texts.textGeneric;
                show(root, 'error');
            }
        } finally {
            busy(generate, false);
        }
    });

    list.addEventListener('change', () => {
        apply.disabled = !list.querySelector('input:checked');
    });

    apply.addEventListener('click', async () => {
        const option = list.querySelector('input:checked')?.value;

        if (!option) {
            return;
        }

        busy(apply, true);

        try {
            const response = await http.post(texts.applyUrl, { option }, { silent: true, toast: false });
            notify.success(response.message);
            document.querySelectorAll('[data-logo-preview]').forEach((image) => {
                image.src = response.data.url;
            });

            if (!document.querySelector('[data-logo-preview]')) {
                window.location.reload();
            }
        } catch (error) {
            notify.error(error?.payload?.message || texts.textGeneric);
        } finally {
            busy(apply, false);
        }
    });
}

export default function initAiLogo(scope = document) {
    scope.querySelectorAll('[data-ai-logo]:not([data-ai-bound])').forEach((root) => {
        root.dataset.aiBound = '';
        bind(root);
    });
}
