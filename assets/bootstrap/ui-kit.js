// UI kit demo page (dev only): wires the buttons in templates/ui_kit/bootstrap.html.twig to the helpers.
import { notify } from './lib/notify.js';
import { confirmAction } from './lib/confirm.js';
import { api, handleApiError } from './lib/api.js';
import { withBusy } from './lib/busy.js';
import { icon } from './lib/icons.js';
import { t } from '../shared/i18n/translate.js';

const on = (selector, handler) => document.querySelectorAll(selector).forEach((el) => el.addEventListener('click', (event) => handler(/** @type {HTMLButtonElement} */ (event.currentTarget))));

on('[data-demo-toast]', (button) => {
    const type = /** @type {'success'|'info'|'warning'|'error'} */ (button.dataset.demoToast);
    notify({ type, title: t(`ui_kit.toasts.${type}_title`), text: t(`ui_kit.toasts.${type}`), requestId: type === 'error' ? '7F3A-91C2' : null });
});

on('[data-demo-confirm]', async () => {
    const deleted = await confirmAction({
        title: t('ui_kit.confirm.title'),
        body: t('ui_kit.confirm.body'),
        confirmLabel: t('ui_kit.confirm.accept'),
        cancelLabel: t('ui_kit.confirm.cancel'),
    });
    notify(deleted ? { type: 'success', text: t('ui_kit.confirm.deleted') } : { type: 'info', text: t('ui_kit.confirm.kept') });
});

on('[data-demo-load]', async (button) => {
    const list = /** @type {HTMLElement} */ (document.getElementById('demo-list'));
    // Skeleton only when loading takes longer than 300 ms (no flicker).
    const skeleton = setTimeout(() => {
        list.innerHTML = '<p class="placeholder-glow mb-2"><span class="placeholder col-7"></span></p><p class="placeholder-glow mb-2"><span class="placeholder col-10"></span></p><p class="placeholder-glow mb-0"><span class="placeholder col-5"></span></p>';
    }, 300);
    try {
        const items = await withBusy(button, () => api.get(`/api/ui-kit/items${button.dataset.demoLoad === 'empty' ? '?empty=1' : ''}`));
        clearTimeout(skeleton);
        if (items.length === 0) {
            list.innerHTML = '';
            const empty = document.createElement('div');
            empty.className = 'text-center py-3';
            const iconEl = document.createElement('div');
            iconEl.className = 'fs-2 text-body-secondary';
            iconEl.append(icon('empty'));
            const title = document.createElement('div');
            title.className = 'fw-semibold text-body';
            title.textContent = t('empty.no_items_title');
            const text = document.createElement('div');
            text.textContent = t('empty.no_items_text');
            const action = document.createElement('button');
            action.className = 'btn btn-sm btn-primary mt-2';
            action.textContent = t('empty.clear');
            action.addEventListener('click', () => document.querySelector('[data-demo-load="items"]')?.dispatchEvent(new MouseEvent('click')));
            empty.append(iconEl, title, text, action);
            list.append(empty);
            return;
        }
        list.innerHTML = '';
        const ul = document.createElement('ul');
        ul.className = 'list-unstyled mb-0 text-body';
        items.forEach(({ name, detail }) => {
            const li = document.createElement('li');
            li.className = 'd-flex justify-content-between py-1 border-bottom';
            li.innerHTML = '<span></span><span class="text-body-secondary"></span>';
            li.children[0].textContent = name;
            li.children[1].textContent = detail;
            ul.append(li);
        });
        list.append(ul);
    } catch (error) {
        clearTimeout(skeleton);
        list.textContent = '';
        handleApiError(error);
    }
});

on('[data-demo-status]', (button) => withBusy(button, () => api.post(`/api/ui-kit/status/${button.dataset.demoStatus}`)).catch((error) => handleApiError(error)));

on('[data-demo-validation]', (button) => {
    const form = /** @type {HTMLFormElement} */ (document.querySelector('form[name="ui_kit_contact"]'));
    withBusy(button, () => api.post('/api/ui-kit/contact', { name: '', email: 'jan@', postcode: '10123', message: 'Hi' }))
        .catch((error) => handleApiError(error, { form }));
});

on('[data-demo-limited]', (button) => withBusy(button, () => api.post('/api/ui-kit/limited'))
    .then(({ message }) => notify({ type: 'success', text: message }))
    .catch((error) => handleApiError(error)));

on('[data-demo-timeout]', (button) => withBusy(button, () => api.get('/api/ui-kit/slow')).catch((error) => handleApiError(error)));

on('[data-demo-js-error]', () => {
    setTimeout(() => {
        throw new Error('Simulated JavaScript error (UI kit).');
    });
});

// Lets browser tests wait until the demo buttons are wired (this file loads after the page).
document.body.dataset.uiKitReady = '1';
