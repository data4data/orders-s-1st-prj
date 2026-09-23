import { Modal } from 'bootstrap';

/**
 * The one confirmation dialog for destructive actions (pages.html → UI standards → Confirmation):
 * title = action + object, body = consequence, safe button left, red verb button right.
 * Esc, clicking outside and the close button all choose the safe option. Resolves true on confirm.
 *
 * @param {{ title: string, body: string, confirmLabel: string, cancelLabel: string, tone?: 'danger'|'primary' }} options
 * @returns {Promise<boolean>}
 */
export function confirmAction({ title, body, confirmLabel, cancelLabel, tone = 'danger' }) {
    const element = document.getElementById('confirm-modal');
    if (!element) {
        return Promise.resolve(window.confirm(`${title}\n\n${body}`));
    }

    element.querySelector('#confirm-modal-title').textContent = title;
    element.querySelector('#confirm-modal-body').textContent = body;
    const accept = /** @type {HTMLButtonElement} */ (element.querySelector('[data-confirm="accept"]'));
    const cancel = /** @type {HTMLButtonElement} */ (element.querySelector('.modal-footer [data-confirm="cancel"]'));
    accept.textContent = confirmLabel;
    accept.className = `btn btn-${tone}`;
    cancel.textContent = cancelLabel;

    const modal = Modal.getOrCreateInstance(element);

    return new Promise((resolve) => {
        let confirmed = false;
        const onAccept = () => {
            confirmed = true;
            modal.hide();
        };
        const onShown = () => cancel.focus();
        const onHidden = () => {
            accept.removeEventListener('click', onAccept);
            element.removeEventListener('shown.bs.modal', onShown);
            resolve(confirmed);
        };
        accept.addEventListener('click', onAccept);
        element.querySelectorAll('[data-confirm="cancel"]').forEach((button) => button.addEventListener('click', () => modal.hide(), { once: true }));
        element.addEventListener('shown.bs.modal', onShown, { once: true });
        element.addEventListener('hidden.bs.modal', onHidden, { once: true });
        modal.show();
    });
}
