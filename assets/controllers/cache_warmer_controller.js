import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour pré-charger les pages principales dans le cache du service worker.
 * Se déclenche au chargement de la page pour les utilisateurs connectés.
 */
export default class extends Controller {
    static values = {
        urls: Array
    }

    /**
     * Mapping des URLs vers les noms de cache du service worker.
     */
    static cacheNames = {
        api: 'bibliotheque-api',
        pages: 'bibliotheque-pages'
    };

    connect() {
        // Pré-charger les pages après un court délai pour ne pas bloquer le rendu initial
        setTimeout(() => this.warmCache(), 1000);
    }

    async warmCache() {
        if (!('caches' in window)) {
            return;
        }

        const urlsToCache = this.urlsValue;

        // Pré-charger les URLs en parallèle
        await Promise.all(urlsToCache.map(async (url) => {
            try {
                // Déterminer le cache à utiliser selon le type d'URL
                const cacheName = url.startsWith('/api/')
                    ? this.constructor.cacheNames.api
                    : this.constructor.cacheNames.pages;

                // Ouvrir le cache
                const cache = await caches.open(cacheName);

                // Vérifier si l'URL est déjà en cache
                const existingResponse = await cache.match(url);
                if (existingResponse) {
                    return; // Déjà en cache, pas besoin de recharger
                }

                // Faire une requête fetch et mettre la réponse en cache
                const response = await fetch(url, { credentials: 'same-origin' });

                if (response.ok) {
                    // Cloner la réponse car elle ne peut être lue qu'une fois
                    await cache.put(url, response.clone());
                }
            } catch (error) {
                // Ignorer silencieusement les erreurs (hors ligne, etc.)
            }
        }));
    }
}
