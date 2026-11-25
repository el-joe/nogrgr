/**
 * UI strings for JS, read from the JSON island rendered by the document layout:
 *   <script type="application/json" id="app-i18n">{...}</script>
 * Falls back to English defaults.
 */
const defaults = {
    'http.unauthenticated': 'Your session has ended. Please sign in again.',
    'http.forbidden': 'You do not have permission to do this.',
    'http.session_expired': 'The page expired. Reload and try again.',
    'http.validation': 'Please check the highlighted fields.',
    'http.throttled': 'Too many attempts. Try again in :seconds seconds.',
    'http.server_error': 'Something went wrong. Please try again.',
    'http.network_error': 'Network error. Check your connection.',
    'ui.confirm': 'Confirm',
    'ui.cancel': 'Cancel',
    'table.empty': 'No records found.',
};

let messages = null;

function load() {
    if (messages !== null) {
        return messages;
    }

    messages = { ...defaults };
    const island = document.getElementById('app-i18n');

    if (island) {
        try {
            Object.assign(messages, JSON.parse(island.textContent || '{}'));
        } catch {
            // keep defaults
        }
    }

    return messages;
}

export function t(key, replacements = {}) {
    let message = load()[key] ?? key;

    // Longest names first so ":total" is not clobbered by ":to".
    const entries = Object.entries(replacements).sort(([a], [b]) => b.length - a.length);

    for (const [name, value] of entries) {
        message = message.replaceAll(`:${name}`, String(value));
    }

    return message;
}

/**
 * Intl locale with Latin digits in every language (matches the server formatter and
 * the Figma RTL frame): "ar" → "ar-u-nu-latn".
 */
export function numberLocale() {
    const lang = document.documentElement.lang || 'en';

    return lang.includes('-u-') ? lang : `${lang}-u-nu-latn`;
}
