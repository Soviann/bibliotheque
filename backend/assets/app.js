import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

/**
 * Injecte le HTML d'une page et réinitialise l'indicateur de connexion.
 * @param {string} html - Le contenu HTML à injecter
 * @param {string} url - L'URL à afficher dans la barre d'adresse
 * @param {boolean} pushState - Si true, ajoute à l'historique ; si false, remplace l'état actuel
 */
async function injectPageContent(html, url, pushState = true) {
    document.documentElement.innerHTML = html;

    if (pushState) {
        history.pushState({ cachedPage: true }, '', url);
    } else {
        history.replaceState({ cachedPage: true }, '', url);
    }

    // Réinitialise l'indicateur de connexion après injection du HTML
    // Le contrôleur Stimulus pwa--connection-status est perdu lors du remplacement innerHTML
    updateOfflineIndicator();
}

/**
 * Met à jour l'indicateur de connexion manuellement.
 * Nécessaire car le contrôleur Stimulus n'est pas réinitialisé après innerHTML.
 */
function updateOfflineIndicator() {
    const indicator = document.getElementById('offline-indicator');
    if (indicator) {
        if (navigator.onLine) {
            indicator.textContent = '';
            indicator.style.display = 'none';
        } else {
            indicator.textContent = 'Mode hors ligne';
            indicator.style.display = '';
        }
    }
}

/**
 * Charge une page depuis le cache ou affiche la page offline.
 * @param {string} url - L'URL de la page à charger
 * @param {boolean} pushState - Si true, ajoute à l'historique
 * @returns {Promise<boolean>} - true si une page a été chargée
 */
async function loadPageFromCache(url, pushState = true) {
    // Vérifie si la page est dans le cache
    const cache = await caches.open('bibliotheque-pages');
    const cachedResponse = await cache.match(url);

    if (cachedResponse) {
        const html = await cachedResponse.text();
        await injectPageContent(html, url, pushState);
        return true;
    }

    // Page pas en cache : charge la page offline
    const offlineCache = await caches.open('offline');
    const offlineResponse = await offlineCache.match('/offline');

    if (offlineResponse) {
        const html = await offlineResponse.text();
        await injectPageContent(html, '/offline', pushState);
    } else {
        window.location.href = '/offline';
    }

    return false;
}

// Gestion des erreurs réseau pour Turbo (mode hors ligne)
document.addEventListener('turbo:fetch-request-error', async (event) => {
    event.preventDefault();

    const targetUrl = event.detail?.request?.url || null;

    if (targetUrl) {
        await loadPageFromCache(targetUrl, true);
    } else {
        await loadPageFromCache('/offline', true);
    }
});

// Gestion du retour arrière (popstate) en mode hors ligne
window.addEventListener('popstate', async (event) => {
    // Ne gère que si on est hors ligne
    if (navigator.onLine) {
        return;
    }

    // Récupère l'URL actuelle (celle vers laquelle on navigue)
    const targetUrl = window.location.href;

    // Charge la page depuis le cache sans ajouter à l'historique
    await loadPageFromCache(targetUrl, false);
});
