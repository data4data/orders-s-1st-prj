// Contact page: jQuery validation (data-validate) in the browser, then AJAX to /api/contact.
// Server violations appear under the same fields; 429 shows the standard countdown toast.
import { api, handleApiError } from '../lib/api.js';
import { withBusy } from '../lib/busy.js';
import { notify } from '../lib/notify.js';
import { t } from '../../shared/i18n/translate.js';

const form = /** @type {HTMLFormElement|null} */ (document.querySelector('form[data-contact-form]'));
if (form) {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        // The validation layer cancels invalid submits first; nothing to send then.
        if (form.querySelector('.is-invalid')) return;
        const button = /** @type {HTMLButtonElement} */ (form.querySelector('button[type=submit]'));
        const data = Object.fromEntries(new FormData(form).entries());
        withBusy(button, () => api.post('/api/contact', { ...data, orderNumber: data.orderNumber || null }))
            .then(() => {
                form.reset();
                form.dataset.submitting = '1';
                form.hidden = true;
                const sent = document.getElementById('contact-sent');
                if (sent) sent.hidden = false;
                notify({ type: 'success', text: t('content.contact_sent') });
            })
            .catch((error) => handleApiError(error, { form }));
    });
}

// Safety data sheets: filter the list while typing.
const filter = /** @type {HTMLInputElement|null} */ (document.querySelector('[data-sds-filter]'));
if (filter) {
    filter.addEventListener('input', () => {
        const q = filter.value.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('[data-sds-item]').forEach((item) => {
            const match = (item.getAttribute('data-sds-item') ?? '').includes(q);
            // Bootstrap .d-flex (display: flex !important) wins over [hidden]: use .d-none.
            item.classList.toggle('d-none', !match);
            if (match) visible += 1;
        });
        const none = /** @type {HTMLElement|null} */ (document.querySelector('[data-sds-none]'));
        if (none) none.hidden = visible > 0;
    });
}
