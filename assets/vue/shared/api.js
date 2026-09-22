import { createApiClient, describeError, ApiError } from '../../shared/http/api-client.js';
import { t } from './i18n.js';
import { notify } from './notify.js';
import { confirmAction } from './confirm.js';

/** HTTP client for Vue pages: await api.get('/api/store')… */
export const api = createApiClient({
    onAutoRetry: (error, delayMs) => notify({ type: 'info', text: t('errors.retrying', { seconds: Math.round(delayMs / 1000) }) }),
});

/**
 * The visible reaction to a failed call (pages.html → Error handling matrix).
 *
 * @param {unknown} error
 * @param {{ errors?: Record<string, string> }} [context] reactive field-error object; violations for known fields go there
 */
export async function handleApiError(error, { errors } = {}) {
    if (!(error instanceof ApiError)) {
        throw error;
    }
    error.handled = true;

    if (error.kind === 'validation' && errors && error.violations.length) {
        const unmatched = error.violations.filter((violation) => {
            if (violation.propertyPath in errors) {
                errors[violation.propertyPath] = violation.message;
                return false;
            }
            return true;
        });
        if (unmatched.length === 0) {
            return;
        }
        error.violations = unmatched;
    }

    if (error.kind === 'unauthenticated') {
        const logIn = await confirmAction({
            title: t('errors.unauthenticated_title'),
            body: t('errors.unauthenticated'),
            confirmLabel: t('dialog.log_in'),
            cancelLabel: t('common.close'),
            tone: 'primary',
        });
        if (logIn) {
            window.location.href = '/login';
        }
        return;
    }

    if (error.kind === 'conflict') {
        const reload = await confirmAction({
            title: t('errors.conflict_title'),
            body: error.detail || t('errors.conflict'),
            confirmLabel: t('dialog.reload'),
            cancelLabel: t('common.close'),
            tone: 'primary',
        });
        if (reload) {
            window.location.reload();
        }
        return;
    }

    const { level, title, text, requestId, countdown } = describeError(error, t);
    notify({ type: level, title, text, requestId, countdown });
}

export { ApiError };
