export class ApiError extends Error {
    constructor(status, message, errors = null) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Same-origin call to the REST API. The session login is turned into API access by Passport's laravel_token cookie.
 */
export async function api(path, { method = 'GET', body = null, form = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() };
    let payload;

    if (form) {
        payload = form;
    } else if (body) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    let response;

    try {
        response = await fetch(`/api${path}`, { method, headers, body: payload, credentials: 'same-origin' });
    } catch {
        throw new ApiError(0, 'Nincs hálózati kapcsolat.');
    }

    if (response.status === 401) {
        location.href = '/login';
        throw new ApiError(401, 'Be kell jelentkezned.');
    }

    if (response.status === 204) {
        return null;
    }

    const json = await response.json().catch(() => null);

    if (!response.ok) {
        const firstError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
        throw new ApiError(response.status, firstError ?? json?.message ?? 'Hiba történt.', json?.errors ?? null);
    }

    return json?.data ?? json;
}
