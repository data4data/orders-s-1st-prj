import { ApiError } from '../../shared/http/api-client.js';
import { handleApiError } from './api.js';
import { notify } from './notify.js';
import { t } from '../../shared/i18n/translate.js';

/** Unexpected JavaScript errors become one generic toast (at most one every 5 s). */
export function installGlobalErrorHandler() {
    let last = 0;
    const report = (reason) => {
        if (reason instanceof ApiError) {
            if (!reason.handled) handleApiError(reason);
            return;
        }
        if (Date.now() - last < 5000) return;
        last = Date.now();
        notify({ type: 'error', title: t('errors.js_title'), text: t('errors.js') });
    };
    window.addEventListener('error', (event) => report(event.error));
    window.addEventListener('unhandledrejection', (event) => report(event.reason));
}
