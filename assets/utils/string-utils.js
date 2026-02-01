/**
 * Utilitaires pour la manipulation de chaînes de caractères.
 */

/**
 * Normalise une chaîne pour la recherche (minuscules, sans accents).
 *
 * @param {string} str
 * @returns {string}
 */
export function normalizeString(str) {
    return str
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

/**
 * Échappe les caractères HTML pour éviter les injections XSS.
 *
 * @param {string} str
 * @returns {string}
 */
export function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
