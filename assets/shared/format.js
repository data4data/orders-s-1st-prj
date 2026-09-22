/**
 * Money and pack formatting shared by both frontends. Amounts arrive in cents.
 *
 * @param {number} cents
 * @param {string} currency ISO 4217, e.g. "EUR"
 */
export function formatMoney(cents, currency) {
    const locale = document.documentElement.lang || 'en';
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(cents / 100);
}
