/**
 * "MyOil's Synth Pro 5W-30" -> "myoils-synth-pro-5w-30" (URL names for products and categories).
 *
 * @param {string} text
 */
export function slugify(text) {
    return text
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/['’]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
