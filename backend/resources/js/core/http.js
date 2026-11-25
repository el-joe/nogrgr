/**
 * HTTP client for every surface. Speaks the JSON contract:
 *   2xx {success, message, data?, redirect?}   422 {message, errors}
 *
 * Status handling:
 *   401 → toast + redirect to payload.redirect or <html data-login-url>, else reload
 *   403 → toast "forbidden"
 *   419 → toast "page expired" + reload (CSRF/session)
 *   422 → render field errors when a form is given; rejects with HttpError
 *   429 → toast with Retry-After seconds
 *   5xx / network → generic toast
 *
 * Usage:
 *   import { http } from '@/core/http';
 *   const { data } = await http.post('/admin/products', formData, { form });
 */
import { notify } from './notify';
import { showErrors, clearErrors } from './form-errors';
import { t } from './i18n';

export class HttpError extends Error {
    constructor(status, payload = {}, response = null) {
        super(payload?.message || `HTTP ${status}`);
        this.name = 'HttpError';
        this.status = status;
        this.payload = payload;
        this.errors = payload?.errors ?? {};
        this.response = response;
    }
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function buildBody(data, headers) {
    if (data === undefined || data === null) {
        return undefined;
    }

    if (data instanceof FormData || data instanceof URLSearchParams || data instanceof Blob) {
        return data;
    }

    headers['Content-Type'] = 'application/json';

    return JSON.stringify(data);
}

async function parse(response) {
    const type = response.headers.get('content-type') ?? '';

    if (type.includes('application/json')) {
        try {
            return await response.json();
        } catch {
            return {};
        }
    }

    return { message: '', raw: await response.text() };
}

function handleFailure(status, payload, response, options) {
    if (options.silent) {
        return;
    }

    switch (true) {
        case status === 401: {
            notify.warning(payload.message || t('http.unauthenticated'));
            const target = payload.redirect || document.documentElement.dataset.loginUrl;
            setTimeout(() => (target ? window.location.assign(target) : window.location.reload()), 800);
            break;
        }
        case status === 403:
            notify.error(payload.message || t('http.forbidden'));
            break;
        case status === 419:
            notify.warning(t('http.session_expired'));
            setTimeout(() => window.location.reload(), 1500);
            break;
        case status === 422:
            if (options.form) {
                showErrors(options.form, payload.errors);
            }
            notify.error(payload.message || t('http.validation'));
            break;
        case status === 429:
            notify.warning(t('http.throttled', { seconds: response.headers.get('Retry-After') ?? '60' }));
            break;
        case status >= 500:
            notify.error(t('http.server_error'));
            break;
        default:
            notify.error(payload.message || t('http.server_error'));
    }
}

/**
 * @param {string} method
 * @param {string} url
 * @param {object} [options] { data, headers, form, signal, silent, followRedirect=true, toast=true }
 */
export async function request(method, url, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
        ...(options.headers ?? {}),
    };

    let finalMethod = method.toUpperCase();
    let data = options.data;

    // Laravel method spoofing so multipart PUT/PATCH work.
    if (data instanceof FormData && !['GET', 'POST'].includes(finalMethod)) {
        data.append('_method', finalMethod);
        finalMethod = 'POST';
    }

    if (options.form) {
        clearErrors(options.form);
    }

    let response;

    try {
        response = await fetch(url, {
            method: finalMethod,
            headers,
            body: finalMethod === 'GET' ? undefined : buildBody(data, headers),
            credentials: 'same-origin',
            signal: options.signal,
        });
    } catch (error) {
        if (error?.name !== 'AbortError' && !options.silent) {
            notify.error(t('http.network_error'));
        }
        throw error;
    }

    const payload = await parse(response);

    if (!response.ok) {
        handleFailure(response.status, payload, response, options);
        throw new HttpError(response.status, payload, response);
    }

    if (options.toast !== false && payload?.message && !options.silent) {
        notify.success(payload.message);
    }

    if (payload?.redirect && options.followRedirect !== false) {
        window.location.assign(payload.redirect);
    }

    return payload;
}

const withQuery = (url, params) => {
    if (!params) {
        return url;
    }

    const query = new URLSearchParams(params).toString();

    return query ? `${url}${url.includes('?') ? '&' : '?'}${query}` : url;
};

export const http = {
    request,
    get: (url, params, options = {}) => request('GET', withQuery(url, params), options),
    post: (url, data, options = {}) => request('POST', url, { ...options, data }),
    put: (url, data, options = {}) => request('PUT', url, { ...options, data }),
    patch: (url, data, options = {}) => request('PATCH', url, { ...options, data }),
    delete: (url, data, options = {}) => request('DELETE', url, { ...options, data }),
};

export default http;
