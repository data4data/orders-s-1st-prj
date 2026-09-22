import { notify } from './notify.js';
import { t } from '../../shared/i18n/translate.js';

/** "You're offline" banner; submit buttons are disabled by the .is-offline body class. */
export function installOfflineBanner() {
    const banner = document.getElementById('offline-banner');
    const update = () => {
        const offline = navigator.onLine === false;
        if (banner) banner.hidden = !offline;
        document.body.classList.toggle('is-offline', offline);
    };
    window.addEventListener('offline', update);
    window.addEventListener('online', () => {
        update();
        notify({ type: 'success', text: t('offline.back') });
    });
    update();
}
