import { ApiError } from '../../shared/http/api-client.js';
import { handleApiError } from './api.js';
import { notify } from './notify.js';
import { t } from './i18n.js';

/**
 * Admin forms with nested fields (e.g. "variants[2].priceNet"): puts every server violation into
 * `errors` under its property path, so each message shows under its field. Other errors use the
 * standard reactions (409 conflict dialog, toasts…).
 *
 * @param {unknown} error
 * @param {Record<string, string>} errors reactive object, cleared first
 * @returns {Promise<boolean>} true when the error was a validation error
 */
export async function applyServerErrors(error, errors) {
    Object.keys(errors).forEach((key) => delete errors[key]);
    if (error instanceof ApiError && error.kind === 'validation' && error.violations.length) {
        error.handled = true;
        error.violations.forEach(({ propertyPath, message }) => { errors[propertyPath || '_form'] = message; });
        notify({ type: 'warning', title: t('errors.validation_title'), text: t('admin.catalog.fix_fields', { count: error.violations.length }) });
        return true;
    }
    await handleApiError(error);
    return false;
}
