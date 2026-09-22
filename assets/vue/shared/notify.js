import ToastEventBus from 'primevue/toasteventbus';

const MAX_VISIBLE = 3;
const AUTO_HIDE_MS = 4000;
const SEVERITY = { success: 'success', info: 'info', warning: 'warn', error: 'error', danger: 'error' };
const visible = [];

/**
 * Shows a toast (pages.html → UI standards → Toasts). Works from any island or plain module:
 * the one <AppToast> (mounted by the system layer) listens on PrimeVue's toast event bus.
 *
 * @param {{ type?: 'success'|'info'|'warning'|'error'|'danger', title?: string, text?: string, requestId?: string|null, countdown?: number|null }} message
 */
export function notify({ type = 'info', title = '', text = '', requestId = null, countdown = null }) {
    const autoHide = type === 'success' || type === 'info';
    const message = {
        group: 'app',
        severity: SEVERITY[type] ?? 'info',
        summary: title,
        detail: text,
        life: autoHide ? AUTO_HIDE_MS : countdown ? countdown * 1000 : undefined,
        requestId,
        countdown,
    };
    ToastEventBus.emit('add', message);
    visible.push(message);
    while (visible.length > MAX_VISIBLE) {
        ToastEventBus.emit('remove', { message: visible.shift(), type: 'close' });
    }
}

export const useNotify = () => ({ notify });
