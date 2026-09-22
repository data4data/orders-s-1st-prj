import { createApiClient, describeError, ApiError } from '../../shared/http/api-client.js';
import { t } from '../../shared/i18n/translate.js';
import { notify } from './notify.js';
import { confirmAction } from './confirm.js';
import { applyViolations } from './forms.js';
import { persistUnsavedForms } from './unsaved-guard.js';

/** HTTP client for jQuery pages: api.get('/api/store'), api.post(url, body)… */
export const api = createApiClient({
    onAutoRetry: (error, delayMs) => notify({ type: 'info', text: t('errors.retrying', { seconds: Math.round(delayMs / 1000) }) }),
});

/**
 * The visible reaction to a failed call (pages.html → Error handling matrix).
 *
 * @param {unknown} error
 * @param {{ form?: HTMLFormElement }} [context] field errors go under the fields of this form
 */
export async function handleApiError(error, { form } = {}) {
    if (!(error instanceof ApiError)) {
        throw error;
    }
    error.handled = true;

    if (error.kind === 'validation' && form && error.violations.length) {
        const unmatched = applyViolations(form, error.violations);
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
            persistUnsavedForms();
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
