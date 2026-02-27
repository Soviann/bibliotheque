/**
 * Utilitaires pour la gestion du cache localStorage.
 */

/** @type {string} Clé par défaut du cache */
const DEFAULT_CACHE_KEY = 'bibliotheque_comics_cache';

/**
 * Récupère les données depuis le cache localStorage.
 *
 * @param {string} cacheKey - Clé du cache (défaut: bibliotheque_comics_cache)
 * @returns {Array|null}
 */
export function getFromCache(cacheKey = DEFAULT_CACHE_KEY) {
    try {
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
            return JSON.parse(cached);
        }
    } catch (error) {
        console.error('Erreur lors de la lecture du cache:', error);
    }
    return null;
}

/**
 * Sauvegarde les données dans le cache localStorage.
 *
 * @param {Array} data - Données à sauvegarder
 * @param {string} cacheKey - Clé du cache (défaut: bibliotheque_comics_cache)
 */
export function saveToCache(data, cacheKey = DEFAULT_CACHE_KEY) {
    try {
        localStorage.setItem(cacheKey, JSON.stringify(data));
    } catch (error) {
        console.error('Erreur lors de la sauvegarde du cache:', error);
    }
}
