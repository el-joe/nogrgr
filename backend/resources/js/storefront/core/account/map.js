/**
 * "Select address on map" (hook: [data-address-map] inside [data-address-form]):
 * browser geolocation → GET /geo/reverse?lat=&lng= fills street, city, state, country
 * and the hidden coordinates. When geolocation is denied / unavailable or the point
 * cannot be resolved, GET /geo/ip fills what it can and shows the "approximate" notice;
 * if that fails too, the error banner. The button shows its busy state meanwhile.
 */
import { http } from '../../../core/http';

const position = () => new Promise((resolve, reject) => {
    if (!('geolocation' in navigator)) {
        reject(new Error('unsupported'));

        return;
    }

    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
});

function setField(form, name, value) {
    const field = form.elements.namedItem(name);

    if (!field || value === null || value === undefined || value === '') {
        return;
    }

    field.value = value;
    field.removeAttribute?.('aria-invalid');
    field.dispatchEvent(new Event('input', { bubbles: true }));
}

export function fillLocation(form, location) {
    setField(form, 'address_line_1', location.address_line_1);
    setField(form, 'city', location.city);
    setField(form, 'state', location.state);
    setField(form, 'latitude', location.latitude);
    setField(form, 'longitude', location.longitude);

    const country = form.querySelector('select[data-country-select]');

    if (country) {
        const match = [...country.options].find((option) => (location.country_id && Number(option.value) === Number(location.country_id))
            || (location.country_iso2 && option.dataset.iso2?.toUpperCase() === String(location.country_iso2).toUpperCase()));

        if (match && country.value !== match.value) {
            country.value = match.value;
            country.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}

export function resetMapNotices(form) {
    form.querySelectorAll('[data-map-approximate], [data-map-error]').forEach((node) => {
        node.hidden = true;
    });
}

export function initMap(form) {
    const button = form.querySelector('[data-address-map]');

    if (!button || button.__map) {
        return;
    }

    button.__map = true;
    const approximate = form.querySelector('[data-map-approximate]');
    const failed = form.querySelector('[data-map-error]');
    const reverseUrl = form.dataset.geoReverseUrl;
    const ipUrl = form.dataset.geoIpUrl;
    const quiet = { silent: true, toast: false };

    button.addEventListener('click', async () => {
        if (button.dataset.loading !== undefined) {
            return;
        }

        resetMapNotices(form);
        button.dataset.loading = '';
        button.setAttribute('aria-busy', 'true');

        try {
            try {
                const { coords } = await position();
                const { data } = await http.get(reverseUrl, { lat: coords.latitude, lng: coords.longitude }, quiet);
                fillLocation(form, { ...data, latitude: data?.latitude ?? coords.latitude, longitude: data?.longitude ?? coords.longitude });

                return;
            } catch {
                // fall through to the IP estimate
            }

            if (!ipUrl) {
                throw new Error('no-ip');
            }

            const { data } = await http.get(ipUrl, undefined, quiet);

            if (!data || (!data.country_id && !data.country_iso2 && !data.city)) {
                throw new Error('unknown');
            }

            fillLocation(form, data);
            if (approximate) {
                approximate.hidden = false;
            }
        } catch {
            if (failed) {
                failed.hidden = false;
            }
        } finally {
            delete button.dataset.loading;
            button.removeAttribute('aria-busy');
        }
    });
}
