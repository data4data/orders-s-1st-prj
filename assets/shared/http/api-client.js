/**
 * The one HTTP client behind both frontends (docs/diagrams/pages.html → Error handling).
 *
 * It sends JSON with the session cookie and the X-CSRF-Token header, and turns every failure
 * into an ApiError with a `kind`. It also does the automatic parts of the error matrix:
 *   - 419: fetch a new CSRF token and retry once
 *   - 502 / 503 / 504 on GET: retry with backoff (1 s, 3 s, 9 s)
 *   - 429 on GET with a short Retry-After: wait and retry once
 *   - 15 s timeout, offline and network failures
 * What the user then sees (toast, dialog, field errors) is decided by each frontend's reaction layer.
 */

const TIMEOUT_MS = 15000;
const BACKOFF_MS = [1000, 3000, 9000];
const MAX_AUTO_RETRY_AFTER_S = 30;

export class ApiError extends Error {
    /**
     * @param {number} status HTTP status, 0 when no response arrived
     * @param {string} kind   see kindOf()
     * @param {{title?: string, detail?: string, violations?: {propertyPath: string, message: string}[], requestId?: string, retryAfter?: number}} [problem]
     */
    constructor(status, kind, problem = {}) {
        super(problem.detail || kind);
        this.name = 'ApiError';
        this.status = status;
        this.kind = kind;
        this.title = problem.title ?? null;
        this.detail = problem.detail ?? null;
        this.violations = problem.violations ?? [];
        this.requestId = problem.requestId ?? null;
        this.retryAfter = problem.retryAfter ?? null;
        /** Set by a reaction layer so the global error handler does not report it twice. */
        this.handled = false;
    }
}

/** @param {number} status */
export function kindOf(status) {
    switch (status) {
        case 400:
        case 405:
            return 'bad_request';
        case 401:
            return 'unauthenticated';
        case 403:
            return 'forbidden';
        case 404:
            return 'not_found';
        case 409:
            return 'conflict';
        case 413:
            return 'too_large';
        case 419:
            return 'csrf';
        case 422:
            return 'validation';
        case 429:
            return 'rate_limited';
        case 502:
        case 503:
        case 504:
            return 'unavailable';
        default:
            return status >= 500 ? 'server' : 'bad_request';
    }
}

const csrfMeta = () => document.querySelector('meta[name="csrf-token"]');
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function refreshCsrfToken() {
    const response = await fetch('/api/csrf-token', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (response.ok) {
        const { token } = await response.json();
        csrfMeta()?.setAttribute('content', token);
    }
}

async function parseProblem(response) {
    try {
        const body = await response.json();
        return body && typeof body === 'object' ? body : {};
    } catch {
        return {};
    }
}

/**
 * @param {{ onAutoRetry?: (error: ApiError, delayMs: number) => void }} [options]
 */
export function createApiClient({ onAutoRetry } = {}) {
    /**
     * @param {string} method
     * @param {string} url
     * @param {unknown} [body]
     * @param {{ timeoutMs?: number }} [options]
     */
    async function request(method, url, body, { timeoutMs = TIMEOUT_MS } = {}) {
        const unsafe = !['GET', 'HEAD', 'OPTIONS'].includes(method);
        let csrfRetried = false;
        let rateLimitRetried = false;

        for (let attempt = 0; ; attempt++) {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), timeoutMs);
            let response;
            try {
                response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
                        ...(unsafe ? { 'X-CSRF-Token': csrfMeta()?.getAttribute('content') ?? '' } : {}),
                    },
                    body: body !== undefined ? JSON.stringify(body) : undefined,
                });
            } catch (error) {
                if (error?.name === 'AbortError') {
                    throw new ApiError(0, 'timeout');
                }
                throw new ApiError(0, navigator.onLine === false ? 'offline' : 'network');
            } finally {
                clearTimeout(timer);
            }

            if (response.ok) {
                if (response.status === 204) {
                    return null;
                }
                return (response.headers.get('Content-Type') ?? '').includes('json') ? response.json() : response.text();
            }

            const problem = await parseProblem(response);
            const error = new ApiError(response.status, kindOf(response.status), problem);
            if (error.retryAfter === null && response.headers.has('Retry-After')) {
                error.retryAfter = parseInt(response.headers.get('Retry-After') ?? '0', 10) || null;
            }

            if (error.kind === 'csrf' && !csrfRetried) {
                csrfRetried = true;
                await refreshCsrfToken();
                continue;
            }
            if (method === 'GET' && error.kind === 'unavailable' && attempt < BACKOFF_MS.length) {
                onAutoRetry?.(error, BACKOFF_MS[attempt]);
                await sleep(BACKOFF_MS[attempt]);
                continue;
            }
            if (method === 'GET' && error.kind === 'rate_limited' && !rateLimitRetried && error.retryAfter && error.retryAfter <= MAX_AUTO_RETRY_AFTER_S) {
                rateLimitRetried = true;
                onAutoRetry?.(error, error.retryAfter * 1000);
                await sleep(error.retryAfter * 1000);
                continue;
            }
            throw error;
        }
    }

    return {
        get: (url, options) => request('GET', url, undefined, options),
        post: (url, body, options) => request('POST', url, body ?? {}, options),
        put: (url, body, options) => request('PUT', url, body ?? {}, options),
        patch: (url, body, options) => request('PATCH', url, body ?? {}, options),
        delete: (url, options) => request('DELETE', url, undefined, options),
    };
}

/**
 * Title and text for a toast about this error, the same in both frontends.
 *
 * @param {ApiError} error
 * @param {(key: string, params?: object) => string} t
 * @returns {{ level: 'info'|'warning'|'error', title: string, text: string, requestId: string|null, countdown: number|null }}
 */
export function describeError(error, t) {
    const toast = (level, key, text = t(`errors.${key}`)) => ({
        level,
        title: t(`errors.${key}_title`) !== `errors.${key}_title` ? t(`errors.${key}_title`) : '',
        text,
        requestId: error.requestId,
        countdown: null,
    });

    switch (error.kind) {
        case 'validation':
            return toast('warning', 'validation', error.violations.length ? error.violations.map((v) => v.message).join(' ') : error.detail || t('errors.validation'));
        case 'rate_limited':
            return { ...toast('warning', 'rate_limited', t('errors.rate_limited', { seconds: error.retryAfter ?? 60 })), countdown: error.retryAfter ?? 60 };
        case 'forbidden':
            return toast('error', 'forbidden', error.detail || t('errors.forbidden'));
        case 'not_found':
        case 'bad_request':
        case 'too_large':
            return toast('error', error.kind, error.detail || t(`errors.${error.kind}`));
        case 'csrf':
        case 'server':
        case 'unavailable':
        case 'timeout':
            return toast(error.kind === 'timeout' ? 'warning' : 'error', error.kind);
        case 'network':
        case 'offline':
            return toast('warning', 'network');
        default:
            return toast('error', 'server');
    }
}
