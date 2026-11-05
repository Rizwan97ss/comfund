/**
 * Typed client for the Laravel API. Authentication is the HttpOnly session cookie; no
 * token ever reaches JavaScript. The readable XSRF cookie is echoed back for CSRF.
 */
export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
    }

    get isValidation(): boolean {
        return this.status === 422 && Object.keys(this.errors).length > 0;
    }
}

export type Query = Record<
    string,
    string | number | boolean | null | undefined
>;

type RequestOptions = {
    query?: Query;
    body?: unknown;
    idempotencyKey?: string;
};

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function refreshCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
}

export function buildUrl(path: string, query?: Query): string {
    const search = new URLSearchParams();

    Object.entries(query ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            search.set(key, String(value));
        }
    });

    const queryString = search.toString();

    return queryString ? `${path}?${queryString}` : path;
}

async function send(
    method: string,
    path: string,
    options: RequestOptions,
    isRetry = false,
): Promise<Response> {
    const isMutation = method !== 'GET';

    if (isMutation && xsrfToken() === null) {
        await refreshCsrfCookie();
    }

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const token = xsrfToken();

    if (token !== null && isMutation) {
        headers['X-XSRF-TOKEN'] = token;
    }

    if (options.idempotencyKey) {
        headers['Idempotency-Key'] = options.idempotencyKey;
    }

    let body: BodyInit | undefined;

    if (options.body instanceof FormData) {
        body = options.body;
    } else if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(options.body);
    }

    const response = await fetch(buildUrl(path, options.query), {
        method,
        headers,
        body,
        credentials: 'same-origin',
    });

    if (response.status === 419 && !isRetry) {
        await refreshCsrfCookie();

        return send(method, path, options, true);
    }

    return response;
}

async function toError(response: Response): Promise<ApiError> {
    const fallback = 'Something went wrong. Please try again.';

    try {
        const data = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const isServerFault = response.status >= 500;

        return new ApiError(
            isServerFault ? fallback : (data.message ?? fallback),
            response.status,
            data.errors ?? {},
        );
    } catch {
        return new ApiError(fallback, response.status);
    }
}

async function request<T>(
    method: string,
    path: string,
    options: RequestOptions = {},
): Promise<T> {
    const response = await send(method, path, options);

    if (!response.ok) {
        throw await toError(response);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    // A redirect to an HTML page must surface as a readable error, not a JSON parse failure.
    if (!response.headers.get('content-type')?.includes('json')) {
        throw new ApiError(
            'The server sent an unexpected response. Please refresh the page and try again.',
            response.status,
        );
    }

    return (await response.json()) as T;
}

export const api = {
    get: <T>(path: string, query?: Query) => request<T>('GET', path, { query }),
    post: <T>(path: string, body?: unknown, idempotencyKey?: string) =>
        request<T>('POST', path, { body, idempotencyKey }),
    patch: <T>(path: string, body?: unknown) =>
        request<T>('PATCH', path, { body }),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, { body }),
    delete: <T>(path: string) => request<T>('DELETE', path),
    blob: async (path: string): Promise<Blob> => {
        const response = await send('GET', path, {});

        if (!response.ok) {
            throw await toError(response);
        }

        return response.blob();
    },
};

/**
 * A browser-generated request key. Uses getRandomValues because crypto.randomUUID is
 * unavailable on plain-HTTP local domains.
 */
export function newUuid(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(16));

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function toFormData(values: Record<string, unknown>): FormData {
    const data = new FormData();

    Object.entries(values).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        if (value instanceof FileList || Array.isArray(value)) {
            Array.from(value as ArrayLike<File>).forEach((file) =>
                data.append(`${key}[]`, file),
            );
        } else if (value instanceof Blob) {
            data.append(key, value);
        } else {
            data.append(key, String(value as string | number | boolean));
        }
    });

    return data;
}
