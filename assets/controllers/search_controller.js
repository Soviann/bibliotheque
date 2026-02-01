import { Controller } from '@hotwired/stimulus';

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
        const cached = this.getFromCache();
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
                this.saveToCache(this.comics);
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

        const normalizedQuery = this.normalizeString(query);
        const results = this.comics.filter((comic) => {
            const title = this.normalizeString(comic.title || '');
            const authors = this.normalizeString(comic.authors || '');
            const description = this.normalizeString(comic.description || '');

            return (
                title.includes(normalizedQuery) ||
                authors.includes(normalizedQuery) ||
                description.includes(normalizedQuery)
            );
        });

        this.renderResults(results, query);
    }

    /**
     * Normalise une chaîne pour la recherche (minuscules, sans accents).
     *
     * @param {string} str
     * @returns {string}
     */
    normalizeString(str) {
        return str
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    /**
     * Affiche les résultats de recherche.
     *
     * @param {Array} results
     * @param {string} query
     */
    renderResults(results, query) {
        if (!this.hasResultsTarget) return;

        let html = `<p class="text-subtitle" style="margin-bottom: 16px;">${results.length} resultat(s) pour "${this.escapeHtml(query)}"</p>`;

        if (results.length > 0) {
            html += '<div class="cards-grid">';
            results.forEach((comic) => {
                html += this.renderCard(comic);
            });
            html += '</div>';
        } else {
            html += `<div class="empty-state"><p>Aucun resultat trouve pour "${this.escapeHtml(query)}".</p></div>`;
        }

        this.resultsTarget.innerHTML = html;
    }

    /**
     * Génère le HTML d'une carte de comic.
     *
     * @param {Object} comic
     * @returns {string}
     */
    renderCard(comic) {
        const coverHtml = comic.coverUrl
            ? `<div class="comic-card-cover"><img src="${this.escapeHtml(comic.coverUrl)}" alt="Couverture de ${this.escapeHtml(comic.title)}" loading="lazy"></div>`
            : '';

        let infoHtml = '';
        if (comic.isOneShot) {
            infoHtml = `
                <div class="comic-info-row">
                    <span class="comic-info-label">Type</span>
                    <span class="comic-info-value">Tome unique</span>
                </div>`;
        } else {
            if (comic.currentIssue !== null || comic.currentIssueComplete) {
                infoHtml += `
                    <div class="comic-info-row">
                        <span class="comic-info-label">Possede</span>
                        <span class="comic-info-value">${comic.currentIssueComplete ? 'Complet' : comic.currentIssue}</span>
                    </div>`;
            }
            if (comic.lastBought !== null || comic.lastBoughtComplete) {
                infoHtml += `
                    <div class="comic-info-row">
                        <span class="comic-info-label">Dernier achat</span>
                        <span class="comic-info-value">${comic.lastBoughtComplete ? 'Complet' : comic.lastBought}</span>
                    </div>`;
            }
            if (comic.latestPublishedIssue !== null || comic.latestPublishedIssueComplete) {
                infoHtml += `
                    <div class="comic-info-row">
                        <span class="comic-info-label">Parus</span>
                        <span class="comic-info-value">${comic.latestPublishedIssueComplete ? comic.latestPublishedIssue + ' (termine)' : comic.latestPublishedIssue}</span>
                    </div>`;
            }
            if (comic.lastDownloaded !== null || comic.lastDownloadedComplete) {
                infoHtml += `
                    <div class="comic-info-row">
                        <span class="comic-info-label">Telecharge</span>
                        <span class="comic-info-value">${comic.lastDownloadedComplete ? 'Complet' : comic.lastDownloaded}</span>
                    </div>`;
            }
            if (comic.ownedTomesNumbers && comic.ownedTomesNumbers.length > 0) {
                infoHtml += `
                    <div class="comic-info-row">
                        <span class="comic-info-label">Tomes</span>
                        <span class="comic-info-value">${comic.ownedTomesNumbers.join(', ')}</span>
                    </div>`;
            }
            if (comic.missingTomesNumbers && comic.missingTomesNumbers.length > 0) {
                infoHtml += `
                    <div class="comic-info-row">
                        <span class="comic-info-label">Manquants</span>
                        <span class="comic-info-value warning">${comic.missingTomesNumbers.join(', ')}</span>
                    </div>`;
            }
        }

        const nasBadge = comic.hasNasTome ? '<span class="type-badge type-badge-nas">NAS</span>' : '';

        // Actions simplifiées sans CSRF (pas de suppression/ajout en mode recherche offline)
        const actionsHtml = `
            <div class="comic-card-actions">
                <a href="/comic/${comic.id}/edit" class="btn btn-text" data-turbo-frame="_top">Modifier</a>
            </div>`;

        return `
            <div class="comic-card">
                <a href="/comic/${comic.id}" class="comic-card-link" data-turbo-frame="_top">
                    ${coverHtml}
                    <div class="comic-card-content">
                        <div class="comic-card-title">
                            <h3>${this.escapeHtml(comic.title)}</h3>
                            <span class="status-badge status-${comic.status}">${this.escapeHtml(comic.statusLabel)}</span>
                        </div>
                        <div class="comic-info">
                            ${infoHtml}
                        </div>
                        <div class="type-badges">
                            <span class="type-badge type-badge-${comic.type}">${this.escapeHtml(comic.typeLabel)}</span>
                            ${nasBadge}
                        </div>
                    </div>
                </a>
                ${actionsHtml}
            </div>`;
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

    /**
     * Échappe les caractères HTML.
     *
     * @param {string} str
     * @returns {string}
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Récupère les données depuis le cache localStorage.
     *
     * @returns {Array|null}
     */
    getFromCache() {
        try {
            const cached = localStorage.getItem(this.cacheKey);
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
     * @param {Array} data
     */
    saveToCache(data) {
        try {
            localStorage.setItem(this.cacheKey, JSON.stringify(data));
        } catch (error) {
            console.error('Erreur lors de la sauvegarde du cache:', error);
        }
    }
}
