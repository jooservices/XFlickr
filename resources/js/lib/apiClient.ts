export class ApiError extends Error {
    constructor(
        public status: number,
        public body: unknown,
        message?: string,
    ) {
        super(message ?? `Request failed with status ${status}`);
        this.name = 'ApiError';
    }
}

export type ApiRequestOptions = {
    params?: Record<string, string | number | boolean | undefined | null>;
    signal?: AbortSignal;
    headers?: HeadersInit;
};

function buildUrl(path: string, params?: ApiRequestOptions['params']): string {
    if (!params) {
        return path;
    }

    const search = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null || value === '') {
            continue;
        }

        search.set(key, String(value));
    }

    const query = search.toString();

    return query ? `${path}?${query}` : path;
}

async function parseJsonBody(response: Response): Promise<unknown> {
    const text = await response.text();

    if (text === '') {
        return null;
    }

    try {
        return JSON.parse(text) as unknown;
    } catch {
        return text;
    }
}

function errorMessage(body: unknown, status: number): string {
    if (body && typeof body === 'object' && 'message' in body && typeof body.message === 'string') {
        return body.message;
    }

    return `Request failed with status ${status}`;
}

async function request<T>(path: string, init: RequestInit, params?: ApiRequestOptions['params']): Promise<T> {
    const response = await fetch(buildUrl(path, params), init);
    const body = await parseJsonBody(response);

    if (!response.ok) {
        throw new ApiError(response.status, body, errorMessage(body, response.status));
    }

    return body as T;
}

export async function apiGet<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
    return request<T>(
        path,
        {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                ...options.headers,
            },
            signal: options.signal,
        },
        options.params,
    );
}

export async function apiPost<T>(
    path: string,
    body?: unknown,
    options: ApiRequestOptions = {},
): Promise<T> {
    return request<T>(
        path,
        {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...options.headers,
            },
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: options.signal,
        },
        options.params,
    );
}
