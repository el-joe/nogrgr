/**
 * StorefrontContext JSON island (#sf-context) rendered by x-sf::layout.
 */
let cached = null;

export function context() {
    if (cached) {
        return cached;
    }

    try {
        cached = JSON.parse(document.getElementById('sf-context')?.textContent || '{}');
    } catch {
        cached = {};
    }

    cached.routes ??= {};

    return cached;
}

export const isRtl = () => document.documentElement.dir === 'rtl';
