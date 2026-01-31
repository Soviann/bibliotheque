import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// Gestion des erreurs réseau pour Turbo (mode hors ligne)
document.addEventListener('turbo:fetch-request-error', async (event) => {
    event.preventDefault();

    // Récupère l'URL cible depuis l'événement
    const targetUrl = event.detail?.request?.url || null;

    if (targetUrl) {
        // Vérifie si la page est dans le cache des pages
        const cache = await caches.open('bibliotheque-pages');
        const cachedResponse = await cache.match(targetUrl);

        if (cachedResponse) {
            // Page en cache : injecte le contenu directement depuis le cache
            const html = await cachedResponse.text();
            document.documentElement.innerHTML = html;
            history.pushState({}, '', targetUrl);
            return;
        }
    }

    // Page pas en cache : charge la page offline depuis le cache du SW
    const offlineCache = await caches.open('offline');
    const offlineResponse = await offlineCache.match('/offline');

    if (offlineResponse) {
        const html = await offlineResponse.text();
        document.documentElement.innerHTML = html;
        history.pushState({}, '', '/offline');
    } else {
        // Fallback: navigation classique
        window.location.href = '/offline';
    }
});
