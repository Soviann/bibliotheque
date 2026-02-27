import { Controller } from '@hotwired/stimulus';
import { getFromCache, saveToCache } from '../utils/cache-utils.js';
import { renderCard } from '../utils/card-renderer.js';
import { escapeHtml, normalizeString } from '../utils/string-utils.js';

/**
 * Contrôleur de recherche côté client.
 * Charge les données depuis /api/comics et effectue la recherche en JS.
 * Fonctionne hors ligne grâce au cache localStorage.
 */
export default class extends Controller {
    static targets = ['input', 'results'];

    /** @type {Array|null} */
    comics = null;

    /** @type {string} */
    cacheKey = 'bibliotheque_comics_cache';

    connect() {
        this.loadComics();

        // Exécuter la recherche initiale si une query est présente
        const urlParams = new URLSearchParams(window.location.search);
        const initialQuery = urlParams.get('q');
        if (initialQuery && this.hasInputTarget) {
            this.inputTarget.value = initialQuery;
        }
    }

    /**
     * Charge les données des comics depuis l'API ou le cache.
     */
    async loadComics() {
        // Essayer de charger depuis le cache d'abord
        const cached = getFromCache(this.cacheKey);
        if (cached) {
            this.comics = cached;
            // Exécuter la recherche si une query est présente
            this.performSearchIfNeeded();
        }

        // Mettre à jour depuis l'API en arrière-plan
        try {
            const response = await fetch('/api/comics');
            if (response.ok) {
                this.comics = await response.json();
                saveToCache(this.comics, this.cacheKey);
                // Relancer la recherche avec les données fraîches
                this.performSearchIfNeeded();
            }
        } catch (error) {
            // En mode hors ligne, on utilise le cache
            console.log('Mode hors ligne, utilisation du cache');
        }
    }

    /**
     * Exécute la recherche si une query est présente dans l'URL.
     */
    performSearchIfNeeded() {
        const urlParams = new URLSearchParams(window.location.search);
        const query = urlParams.get('q');
        if (query && query.length >= 2 && this.comics) {
            this.performSearch(query);
        }
    }

    /**
     * Gère l'événement de saisie dans le champ de recherche.
     */
    search() {
        clearTimeout(this.timeout);

        this.timeout = setTimeout(() => {
            const query = this.inputTarget.value;

            if (query.length >= 2) {
                this.performSearch(query);

                // Mettre à jour l'URL
                const url = new URL(window.location.href);
                url.searchParams.set('q', query);
                window.history.replaceState({}, '', url.toString());
            } else if (query.length === 0) {
                this.showEmptyState();

                // Nettoyer l'URL
                const url = new URL(window.location.href);
                url.searchParams.delete('q');
                window.history.replaceState({}, '', url.toString());
            }
        }, 300);
    }

    /**
     * Effectue la recherche côté client.
     *
     * @param {string} query
     */
    performSearch(query) {
        if (!this.comics) {
            this.showLoading();
            return;
        }

        const normalizedQuery = normalizeString(query);
        const results = this.comics.filter((comic) => {
            const title = normalizeString(comic.title || '');
            const authors = normalizeString(comic.authors || '');
            const description = normalizeString(comic.description || '');

            return (
                title.includes(normalizedQuery) ||
                authors.includes(normalizedQuery) ||
                description.includes(normalizedQuery)
            );
        });

        this.renderResults(results, query);
    }

    /**
     * Affiche les résultats de recherche.
     *
     * @param {Array} results
     * @param {string} query
     */
    renderResults(results, query) {
        if (!this.hasResultsTarget) return;

        let html = `<p class="text-subtitle" style="margin-bottom: 16px;">${results.length} resultat(s) pour "${escapeHtml(query)}"</p>`;

        if (results.length > 0) {
            html += '<div class="cards-grid">';
            results.forEach((comic) => {
                html += renderCard(comic);
            });
            html += '</div>';
        } else {
            html += `<div class="empty-state"><p>Aucun resultat trouve pour "${escapeHtml(query)}".</p></div>`;
        }

        this.resultsTarget.innerHTML = html;
    }

    /**
     * Affiche l'état vide (aucune recherche).
     */
    showEmptyState() {
        if (!this.hasResultsTarget) return;

        this.resultsTarget.innerHTML = `
            <div class="empty-state">
                <p>Entrez un terme de recherche pour trouver des series.</p>
            </div>`;
    }

    /**
     * Affiche un état de chargement.
     */
    showLoading() {
        if (!this.hasResultsTarget) return;

        this.resultsTarget.innerHTML = `
            <div class="empty-state">
                <p>Chargement des donnees...</p>
            </div>`;
    }
}
