import { Toast } from 'bootstrap';
import { icon } from './icons.js';
import { t } from '../../shared/i18n/translate.js';

const MAX_VISIBLE = 3;
const AUTO_HIDE_MS = 4000;
const ICON_BY_TYPE = { success: 'success', info: 'info', warning: 'warning', error: 'error' };

/**
 * Shows a toast (pages.html → UI standards → Toasts). Success and info close after 4 s
 * (Bootstrap pauses on hover); warnings and errors stay until closed. At most 3 are visible.
 *
 * @param {{ type?: 'success'|'info'|'warning'|'error'|'danger', title?: string, text?: string, requestId?: string|null, countdown?: number|null }} message
 */
export function notify({ type = 'info', title = '', text = '', requestId = null, countdown = null }) {
    const level = type === 'danger' ? 'error' : type;
    const stack = document.getElementById('toast-stack');
    if (!stack) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast app-toast app-toast--${level}`;
    toast.setAttribute('role', level === 'error' || level === 'warning' ? 'alert' : 'status');
    toast.setAttribute('aria-atomic', 'true');

    const row = document.createElement('div');
    row.className = 'd-flex align-items-start gap-2 p-3';
    const iconWrap = document.createElement('span');
    iconWrap.className = 'app-toast__icon';
    iconWrap.append(icon(ICON_BY_TYPE[level] ?? 'info'));

    const content = document.createElement('div');
    content.className = 'flex-grow-1';
    if (title) {
        const titleEl = document.createElement('div');
        titleEl.className = 'fw-semibold';
        titleEl.textContent = title;
        content.append(titleEl);
    }
    const textEl = document.createElement('div');
    textEl.className = 'small text-body-secondary';
    textEl.textContent = text;
    content.append(textEl);
    if (requestId) {
        const ref = document.createElement('div');
        ref.className = 'small text-body-secondary mt-1';
        ref.textContent = t('errors.reference', { code: requestId });
        content.append(ref);
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', t('common.close'));

    row.append(iconWrap, content, close);
    toast.append(row);
    stack.append(toast);

    const instance = new Toast(toast, { autohide: level === 'success' || level === 'info', delay: AUTO_HIDE_MS });
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    instance.show();

    if (countdown) {
        let seconds = countdown;
        const timer = setInterval(() => {
            seconds -= 1;
            textEl.textContent = t('errors.rate_limited', { seconds: Math.max(seconds, 0) });
            if (seconds <= 0 || !toast.isConnected) {
                clearInterval(timer);
                instance.hide();
            }
        }, 1000);
    }

    while (stack.children.length > MAX_VISIBLE) {
        const oldest = /** @type {HTMLElement} */ (stack.firstElementChild);
        Toast.getInstance(oldest)?.dispose();
        oldest.remove();
    }
}
