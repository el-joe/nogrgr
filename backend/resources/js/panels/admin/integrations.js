/**
 * Admin › Integrations (ADM-100): gateway list search, "Test connection" buttons
 * ([data-integration-test] = URL, optional [data-test-target]) that update the
 * pre-rendered status badge / note of their card, and the gateway form's dirty flag
 * (a test runs against saved values, so unsaved edits are saved first).
 */
import { http } from '../../core/http';
import { bindUnsavedForms } from './unsaved-form.js';

function bindGatewaySearch(root) {
    const input = root.querySelector('[data-gateway-search]');

    if (!input) {
        return;
    }

    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        let visible = 0;

        root.querySelectorAll('[data-gateway-item]').forEach((item) => {
            const match = term === '' || item.dataset.search.includes(term);
            item.hidden = !match;
            visible += match ? 1 : 0;
        });

        root.querySelector('[data-gateway-empty]')?.classList.toggle('hidden', visible > 0);
    });
}

function showStatus(root, key, payload, ok) {
    const status = payload?.data?.status ?? (ok ? 'connected' : 'failed');

    root.querySelectorAll(`[data-status-badge="${key}"] [data-status-value]`).forEach((badge) => {
        badge.hidden = badge.dataset.statusValue !== status;
    });

    const note = root.querySelector(`[data-status-note="${key}"]`);

    if (note) {
        note.textContent = [payload?.message, payload?.data?.detail].filter(Boolean).join(' — ');
        note.classList.toggle('text-success', ok);
        note.classList.toggle('text-danger', !ok);
    }
}

function bindTests(root) {
    root.addEventListener('click', async (event) => {
        const button = event.target.closest?.('[data-integration-test]');

        if (!button || button.hasAttribute('data-loading')) {
            return;
        }

        const target = button.dataset.testTarget ?? '';
        const key = target || button.closest('form')?.querySelector('[data-status-badge]')?.dataset.statusBadge || '';
        const form = button.closest('form[data-unsaved-form]') ?? (button.dataset.testDirtyCheck ? root.querySelector(button.dataset.testDirtyCheck) : null);

        button.setAttribute('data-loading', '');
        button.setAttribute('aria-busy', 'true');

        try {
            if (form?.dataset.dirty === 'true') {
                const method = form.querySelector('input[name="_method"]')?.value ?? 'POST';
                await http.request(method, form.action, { data: new FormData(form), form, followRedirect: false, toast: false });
                delete form.dataset.dirty;
            }

            const payload = await http.post(button.dataset.integrationTest, target ? { target } : {});
            showStatus(root, key, payload, true);
        } catch (error) {
            if (error?.payload?.data) {
                showStatus(root, key, error.payload, false);
            }
        } finally {
            button.removeAttribute('data-loading');
            button.removeAttribute('aria-busy');
        }
    });
}

export default function initIntegrations(root) {
    bindUnsavedForms(root);
    bindGatewaySearch(root);
    bindTests(root);
}
