import ConfirmationEventBus from 'primevue/confirmationeventbus';
import { t } from './i18n.js';

/**
 * The one confirmation dialog for destructive actions (pages.html → UI standards → Confirmation).
 * Esc, clicking outside and the close button choose the safe option. Resolves true on confirm.
 *
 * @param {{ title: string, body: string, confirmLabel: string, cancelLabel: string, tone?: 'danger'|'primary' }} options
 * @returns {Promise<boolean>}
 */
export function confirmAction({ title, body, confirmLabel, cancelLabel, tone = 'danger' }) {
    return new Promise((resolve) => {
        let settled = false;
        const settle = (value) => {
            if (!settled) {
                settled = true;
                resolve(value);
            }
        };
        ConfirmationEventBus.emit('confirm', {
            group: 'app',
            header: title,
            message: body,
            acceptLabel: confirmLabel,
            rejectLabel: cancelLabel,
            tone,
            accept: () => settle(true),
            reject: () => settle(false),
            onHide: () => settle(false),
        });
    });
}

/** The standard "Leave without saving?" question. */
export const confirmLeave = () => confirmAction({
    title: t('dialog.leave_title'),
    body: t('dialog.leave_body'),
    confirmLabel: t('dialog.leave'),
    cancelLabel: t('dialog.stay'),
});

export const useConfirmAction = () => ({ confirmAction });
