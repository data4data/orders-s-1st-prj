// Entry for Twig pages styled with Bootstrap and driven by jQuery.
// Must never import anything from assets/vue (enforced by ESLint); shared logic lives in assets/shared.
import './app.scss';
import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import { notify } from './lib/notify.js';
import { confirmAction } from './lib/confirm.js';
import { api, handleApiError } from './lib/api.js';
import { withBusy } from './lib/busy.js';
import { installValidation, installBusyOnSubmit } from './lib/forms.js';
import { installUnsavedGuard } from './lib/unsaved-guard.js';
import { installOfflineBanner } from './lib/offline.js';
import { installGlobalErrorHandler } from './lib/global-errors.js';
import { t } from '../shared/i18n/translate.js';

window.$ = window.jQuery = $;
window.bootstrap = bootstrap;
/** Page scripts use these helpers; also handy in the browser console. */
window.App = { notify, confirmAction, api, handleApiError, withBusy, t };

$(() => {
    installGlobalErrorHandler();
    installOfflineBanner();
    document.querySelectorAll('[data-i18n]').forEach((element) => { element.textContent = t(element.getAttribute('data-i18n') ?? ''); });
    document.querySelectorAll('form[data-validate]').forEach((form) => installValidation(/** @type {HTMLFormElement} */ (form)));
    document.querySelectorAll('form:not([data-validate])').forEach((form) => installBusyOnSubmit(/** @type {HTMLFormElement} */ (form)));
    installUnsavedGuard();

    // Server flash messages appear as the same toasts.
    const flashes = JSON.parse(document.getElementById('app-flashes')?.textContent || '[]');
    flashes.forEach(({ type, text }) => notify({ type, text }));

    // 429 error page: live countdown.
    document.querySelectorAll('[data-countdown]').forEach((element) => {
        let seconds = parseInt(element.getAttribute('data-countdown') ?? '0', 10);
        const timer = setInterval(() => {
            seconds = Math.max(0, seconds - 1);
            element.textContent = String(seconds);
            if (seconds === 0) clearInterval(timer);
        }, 1000);
    });

    if (document.querySelector('.ui-kit')) {
        import('./ui-kit.js');
    }
});
