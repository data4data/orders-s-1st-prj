import { onMounted, onBeforeUnmount } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { confirmLeave } from './confirm.js';

/**
 * Unsaved-changes guard (pages.html → UI standards → Unsaved changes).
 *  - in the admin SPA: Vue Router navigation asks with the standard dialog
 *  - on storefront islands: page links ask with the standard dialog
 *  - closing or reloading the tab: the browser's own prompt (custom text is not allowed there)
 */

const sources = new Set();
let released = false;
let globalListenersInstalled = false;

export function hasUnsavedChanges() {
    return !released && [...sources].some((isDirty) => isDirty());
}

/** Call after the user chose "Leave without saving" for a full-page navigation. */
export function releaseUnsavedChanges() {
    released = true;
}

function installGlobalListeners() {
    if (globalListenersInstalled) {
        return;
    }
    globalListenersInstalled = true;

    window.addEventListener('beforeunload', (event) => {
        if (hasUnsavedChanges()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    document.addEventListener('click', async (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!(link instanceof HTMLAnchorElement) || link.hasAttribute('data-router-link') || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || link.target === '_blank'
            || link.getAttribute('href')?.startsWith('#') || !hasUnsavedChanges()) {
            return;
        }
        event.preventDefault();
        if (await confirmLeave()) {
            releaseUnsavedChanges();
            window.location.href = link.href;
        }
    }, true);
}

/**
 * @param {() => boolean} isDirty
 * @param {{ router?: boolean }} [options] router: true inside the admin SPA
 */
export function useUnsavedChanges(isDirty, { router = false } = {}) {
    installGlobalListeners();
    onMounted(() => sources.add(isDirty));
    onBeforeUnmount(() => sources.delete(isDirty));
    if (router) {
        onBeforeRouteLeave(async () => (isDirty() ? confirmLeave() : true));
    }
}
