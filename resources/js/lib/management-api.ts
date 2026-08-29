interface ApiRequestOptions extends RequestInit {
    csrfToken: string;
}

export class ApiError extends Error {
    public readonly status: number;
    public readonly errors: Record<string, string[]>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

const errorMessage = (payload: unknown, status: number): string => {
    if (!payload) {
        return `خطای HTTP ${status}`;
    }

    if (typeof payload === 'string') {
        return payload;
    }

    if (typeof payload === 'object') {
        const data = payload as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (data.message) {
            return data.message;
        }

        if (data.errors) {
            return Object.values(data.errors).flat().join(' | ');
        }
    }

    return `خطای HTTP ${status}`;
};

export const apiRequest = async <T>(
    url: string,
    { csrfToken, headers, ...options }: ApiRequestOptions,
): Promise<T> => {
    const requestHeaders = new Headers(headers);
    requestHeaders.set('Accept', 'application/json');
    requestHeaders.set('X-Requested-With', 'XMLHttpRequest');
    requestHeaders.set('X-CSRF-TOKEN', csrfToken);

    if (options.body && !(options.body instanceof FormData)) {
        requestHeaders.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: requestHeaders,
    });

    const contentType = response.headers.get('content-type') ?? '';
    let payload: unknown = null;

    if (response.status !== 204) {
        payload = contentType.includes('application/json')
            ? await response.json()
            : await response.text();
    }

    if (!response.ok) {
        const errors = typeof payload === 'object' && payload
            ? (payload as { errors?: Record<string, string[]> }).errors ?? {}
            : {};

        throw new ApiError(
            errorMessage(payload, response.status),
            response.status,
            errors,
        );
    }

    return payload as T;
};

export const rowsFromPayload = <T>(payload: unknown): T[] => {
    if (Array.isArray(payload)) {
        return payload as T[];
    }

    if (payload && typeof payload === 'object') {
        const data = (payload as { data?: unknown }).data;

        if (Array.isArray(data)) {
            return data as T[];
        }

        if (data && typeof data === 'object') {
            return [data as T];
        }
    }

    return [];
};
