import messages from './en.json';

/**
 * t('errors.rate_limited', { seconds: 30 }) for the jQuery side. The Vue side uses vue-i18n with
 * the same file and the same {placeholder} syntax.
 *
 * @param {string} key
 * @param {Record<string, string|number>} [params]
 * @returns {string}
 */
export function t(key, params = {}) {
    const text = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), messages);
    if (typeof text !== 'string') {
        return key;
    }
    return text.replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match));
}

export { messages };
