import { icon } from './icons.js';
import { t } from '../../shared/i18n/translate.js';
import { ApiError } from '../../shared/http/api-client.js';

const countdowns = new WeakMap();

/**
 * Spinner on the clicked button, disabled while waiting, so nothing is submitted twice.
 *
 * @param {HTMLButtonElement} button
 * @param {boolean} busy
 */
export function setBusy(button, busy) {
    if (busy) {
        if (button.dataset.busyLabel === undefined) {
            button.dataset.busyLabel = button.innerHTML;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.prepend(icon('loading', 'icon-spin me-1'));
    } else if (!countdowns.has(button)) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.dataset.busyLabel !== undefined) {
            button.innerHTML = button.dataset.busyLabel;
            delete button.dataset.busyLabel;
        }
    }
}

/**
 * After a 429 the button stays disabled and counts down ("Wait 45 s").
 *
 * @param {HTMLButtonElement} button
 * @param {number} seconds
 */
export function disableFor(button, seconds) {
    const label = button.dataset.busyLabel ?? button.innerHTML;
    let left = seconds;
    button.disabled = true;
    button.textContent = t('form.wait', { seconds: left });
    const timer = setInterval(() => {
        left -= 1;
        button.textContent = t('form.wait', { seconds: left });
        if (left <= 0) {
            clearInterval(timer);
            countdowns.delete(button);
            button.innerHTML = label;
            delete button.dataset.busyLabel;
            button.disabled = false;
        }
    }, 1000);
    countdowns.set(button, timer);
}

/**
 * @template T
 * @param {HTMLButtonElement} button
 * @param {() => Promise<T>} action
 * @returns {Promise<T>}
 */
export async function withBusy(button, action) {
    setBusy(button, true);
    try {
        return await action();
    } catch (error) {
        if (error instanceof ApiError && error.kind === 'rate_limited' && error.retryAfter) {
            disableFor(button, error.retryAfter);
        }
        throw error;
    } finally {
        setBusy(button, false);
    }
}
