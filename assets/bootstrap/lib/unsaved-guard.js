import { confirmAction } from './confirm.js';
import { t } from '../../shared/i18n/translate.js';

/**
 * Unsaved-changes guard for <form data-unsaved-guard> (pages.html → UI standards → Unsaved changes).
 * Links inside the page ask with the standard dialog; closing or reloading the tab uses the
 * browser's own prompt (browsers do not allow custom text there).
 */

const STORAGE_KEY = 'unsaved-form:';
const forms = new Map();
let released = false;

/** @param {HTMLFormElement} form */
function snapshot(form) {
    const data = new FormData(form);
    return [...data.entries()].filter(([name]) => !name.endsWith('[_token]') && name !== '_csrf_token').map(([k, v]) => `${k}=${v}`).join('&');
}

export function hasUnsavedChanges() {
    return !released && [...forms.entries()].some(([form, initial]) => !form.dataset.submitting && snapshot(form) !== initial);
}

/** Keeps typed values when the session expired and the user logs in again. */
export function persistUnsavedForms() {
    forms.forEach((initial, form) => {
        const values = Object.fromEntries(new FormData(form).entries());
        sessionStorage.setItem(`${STORAGE_KEY}${location.pathname}:${form.id || form.name}`, JSON.stringify(values));
    });
}

/** @param {HTMLFormElement} form */
function restore(form) {
    const key = `${STORAGE_KEY}${location.pathname}:${form.id || form.name}`;
    const saved = sessionStorage.getItem(key);
    if (!saved) {
        return;
    }
    sessionStorage.removeItem(key);
    for (const [name, value] of Object.entries(JSON.parse(saved))) {
        const field = form.elements.namedItem(name);
        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
            if (!name.endsWith('[_token]')) field.value = String(value);
        }
    }
}

export function installUnsavedGuard() {
    document.querySelectorAll('form[data-unsaved-guard]').forEach((form) => {
        forms.set(form, snapshot(form));
        restore(/** @type {HTMLFormElement} */ (form));
    });
    if (forms.size === 0) {
        return;
    }

    window.addEventListener('beforeunload', (event) => {
        if (hasUnsavedChanges()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    document.addEventListener('click', async (event) => {
        const link = /** @type {HTMLElement} */ (event.target).closest?.('a[href]');
        if (!(link instanceof HTMLAnchorElement) || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || link.target === '_blank' || link.hasAttribute('download')
            || link.getAttribute('href')?.startsWith('#') || !hasUnsavedChanges()) {
            return;
        }
        event.preventDefault();
        const leave = await confirmAction({
            title: t('dialog.leave_title'),
            body: t('dialog.leave_body'),
            confirmLabel: t('dialog.leave'),
            cancelLabel: t('dialog.stay'),
        });
        if (leave) {
            released = true;
            window.location.href = link.href;
        }
    }, true);
}
