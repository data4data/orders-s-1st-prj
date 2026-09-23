import { ApiError } from '../../shared/http/api-client.js';
import { handleApiError } from './api.js';
import { notify } from './notify.js';
import { t } from './i18n.js';

let last = 0;

/** Unexpected errors become one generic toast (at most one every 5 s). */
export function reportError(reason) {
    if (reason instanceof ApiError) {
        if (!reason.handled) handleApiError(reason);
        return;
    }
    if (Date.now() - last < 5000) return;
    last = Date.now();
    notify({ type: 'error', title: t('errors.js_title'), text: t('errors.js') });
    console.error(reason);  
}

let installed = false;

/** @param {import('vue').App} [app] */
export function installGlobalErrorHandler(app) {
    if (app) {
        app.config.errorHandler = (error) => reportError(error);
    }
    if (!installed) {
        installed = true;
        window.addEventListener('error', (event) => reportError(event.error));
        window.addEventListener('unhandledrejection', (event) => reportError(event.reason));
    }
}
