import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur de filtrage côté client pour la bibliothèque et la wishlist.
 * Charge les données depuis /api/comics et applique les filtres en JS.
 * Fonctionne hors ligne grâce au cache localStorage.
 */
export default class extends Controller {
    static targets = ['results', 'count', 'searchInput'];
    static values = {
        isWishlist: Boolean,
    };

    /** @type {Array|null} */
    comics = null;

    /** @type {string} */
    cacheKey = 'bibliotheque_comics_cache';

    /** @type {Object} */
    filters = {
        nas: null,
        search: '',
        sort: 'title_asc',
        status: null,
        type: null,
    };

    /** @type {Object} */
    statusLabels = {
        buying: "En cours d'achat",
        finished: 'Terminee',
        stopped: 'Arretee',
        wishlist: 'Liste de souhaits',
    };

    /** @type {Object} */
    typeLabels = {
        bd: 'BD',
        comics: 'Comics',
        livre: 'Livre',
        manga: 'Manga',
    };

    connect() {
        this.parseUrlFilters();
        this.loadComics();
        this.setupEventListeners();
    }

    /**
     * Parse les filtres depuis l'URL.
     */
    parseUrlFilters() {
        const params = new URLSearchParams(window.location.search);
        this.filters.nas = params.get('nas');
        this.filters.search = params.get('q') || '';
        this.filters.sort = params.get('sort') || 'title_asc';
        this.filters.status = params.get('status');
        this.filters.type = params.get('type');

        // Synchroniser le champ de recherche
        if (this.hasSearchInputTarget && this.filters.search) {
            this.searchInputTarget.value = this.filters.search;
        }
    }

    /**
     * Configure les écouteurs d'événements.
     */
    setupEventListeners() {
        // Intercepter la soumission du formulaire de recherche
        const searchForm = this.element.querySelector('.search-filter-form');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const input = searchForm.querySelector('input[name="q"]');
                if (input) {
                    this.updateFilter('search', input.value);
                }
            });
        }

        // Intercepter les clics sur les chips de filtres
        this.element.querySelectorAll('.chip').forEach((chip) => {
            chip.addEventListener('click', (e) => {
                e.preventDefault();
                const url = new URL(chip.href);
                this.filters.nas = url.searchParams.get('nas');
                this.filters.status = url.searchParams.get('status');
                this.filters.type = url.searchParams.get('type');
                this.updateUrlAndRender();
                this.updateChipsActiveState();
            });
        });

        // Intercepter le changement de tri
        const sortSelect = this.element.querySelector('.sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                e.stopPropagation();
                this.updateFilter('sort', e.target.value);
            });
            // Empêcher la fonction globale updateSort
            window.updateSort = (value) => {
                this.updateFilter('sort', value);
            };
        }

        // Intercepter le clic sur le bouton clear de recherche
        const clearBtn = this.element.querySelector('.search-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.hasSearchInputTarget) {
                    this.searchInputTarget.value = '';
                }
                this.updateFilter('search', '');
            });
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
            this.renderResults();
        }

        // Mettre à jour depuis l'API en arrière-plan
        try {
            const response = await fetch('/api/comics');
            if (response.ok) {
                this.comics = await response.json();
                this.saveToCache(this.comics);
                this.renderResults();
            }
        } catch (error) {
            console.log('Mode hors ligne, utilisation du cache');
            if (!cached) {
                this.showOfflineMessage();
            }
        }
    }

    /**
     * Met à jour un filtre et rafraîchit l'affichage.
     *
     * @param {string} key
     * @param {string|null} value
     */
    updateFilter(key, value) {
        this.filters[key] = value || null;
        this.updateUrlAndRender();
        this.updateChipsActiveState();
    }

    /**
     * Met à jour l'URL et affiche les résultats.
     */
    updateUrlAndRender() {
        const url = new URL(window.location.href);

        // Mettre à jour les paramètres
        if (this.filters.type) {
            url.searchParams.set('type', this.filters.type);
        } else {
            url.searchParams.delete('type');
        }

        if (this.filters.status) {
            url.searchParams.set('status', this.filters.status);
        } else {
            url.searchParams.delete('status');
        }

        if (this.filters.nas !== null) {
            url.searchParams.set('nas', this.filters.nas);
        } else {
            url.searchParams.delete('nas');
        }

        if (this.filters.search) {
            url.searchParams.set('q', this.filters.search);
        } else {
            url.searchParams.delete('q');
        }

        url.searchParams.set('sort', this.filters.sort);

        window.history.replaceState({}, '', url.toString());
        this.renderResults();
    }

    /**
     * Met à jour l'état actif des chips.
     */
    updateChipsActiveState() {
        // Type chips
        this.element.querySelectorAll('.filter-section').forEach((section) => {
            const label = section.querySelector('.filter-label');
            if (!label) return;

            const labelText = label.textContent.trim();
            section.querySelectorAll('.chip').forEach((chip) => {
                const url = new URL(chip.href);
                let isActive = false;

                if (labelText === 'Type') {
                    const chipType = url.searchParams.get('type');
                    isActive = chipType === this.filters.type;
                } else if (labelText === 'Statut') {
                    const chipStatus = url.searchParams.get('status');
                    isActive = chipStatus === this.filters.status;
                } else if (labelText === 'NAS') {
                    const chipNas = url.searchParams.get('nas');
                    isActive = chipNas === this.filters.nas;
                }

                chip.classList.toggle('active', isActive);
            });
        });
    }

    /**
     * Filtre et affiche les résultats.
     */
    renderResults() {
        if (!this.comics || !this.hasResultsTarget) return;

        // Filtrer les comics
        let filtered = this.comics.filter((comic) => {
            // Filtre wishlist/library
            if (this.isWishlistValue) {
                if (!comic.isWishlist) return false;
            } else {
                if (comic.isWishlist) return false;
            }

            // Filtre type
            if (this.filters.type && comic.type !== this.filters.type) {
                return false;
            }

            // Filtre status
            if (this.filters.status && comic.status !== this.filters.status) {
                return false;
            }

            // Filtre NAS
            if (this.filters.nas !== null) {
                const wantNas = this.filters.nas === '1';
                if (wantNas && !comic.hasNasTome) return false;
                if (!wantNas && comic.hasNasTome) return false;
            }

            // Filtre recherche
            if (this.filters.search && this.filters.search.length >= 2) {
                const query = this.normalizeString(this.filters.search);
                const title = this.normalizeString(comic.title || '');
                const authors = this.normalizeString(comic.authors || '');
                const description = this.normalizeString(comic.description || '');

                if (
                    !title.includes(query) &&
                    !authors.includes(query) &&
                    !description.includes(query)
                ) {
                    return false;
                }
            }

            return true;
        });

        // Trier les résultats
        filtered = this.sortComics(filtered);

        // Mettre à jour le compteur
        if (this.hasCountTarget) {
            this.countTarget.textContent = `${filtered.length} serie(s)`;
        }

        // Afficher les résultats
        if (filtered.length > 0) {
            let html = '<div class="cards-grid">';
            filtered.forEach((comic) => {
                html += this.renderCard(comic);
            });
            html += '</div>';
            this.resultsTarget.innerHTML = html;
        } else {
            this.resultsTarget.innerHTML = `
                <div class="empty-state">
                    <p>Aucune serie trouvee.</p>
                </div>`;
        }
    }

    /**
     * Trie les comics selon le critère sélectionné.
     *
     * @param {Array} comics
     * @returns {Array}
     */
    sortComics(comics) {
        const sorted = [...comics];

        switch (this.filters.sort) {
            case 'title_desc':
                sorted.sort((a, b) => (b.title || '').localeCompare(a.title || ''));
                break;
            case 'updated_desc':
                sorted.sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt));
                break;
            case 'updated_asc':
                sorted.sort((a, b) => new Date(a.updatedAt) - new Date(b.updatedAt));
                break;
            case 'status':
                sorted.sort((a, b) => {
                    const statusCompare = (a.status || '').localeCompare(b.status || '');
                    if (statusCompare !== 0) return statusCompare;
                    return (a.title || '').localeCompare(b.title || '');
                });
                break;
            case 'title_asc':
            default:
                sorted.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
                break;
        }

        return sorted;
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
        const statusLabel = comic.statusLabel || this.statusLabels[comic.status] || comic.status;
        const typeLabel = comic.typeLabel || this.typeLabels[comic.type] || comic.type;

        // Actions simplifiées (pas de CSRF en mode offline)
        let actionsHtml = `
            <div class="comic-card-actions">
                <a href="/comic/${comic.id}/edit" class="btn btn-text" data-turbo-frame="_top">Modifier</a>`;

        if (this.isWishlistValue) {
            actionsHtml += `
                <button type="button" class="btn btn-success" disabled title="Non disponible hors ligne">Ajouter</button>`;
        }

        actionsHtml += '</div>';

        return `
            <div class="comic-card">
                <a href="/comic/${comic.id}" class="comic-card-link" data-turbo-frame="_top">
                    ${coverHtml}
                    <div class="comic-card-content">
                        <div class="comic-card-title">
                            <h3>${this.escapeHtml(comic.title)}</h3>
                            <span class="status-badge status-${comic.status}">${this.escapeHtml(statusLabel)}</span>
                        </div>
                        <div class="comic-info">
                            ${infoHtml}
                        </div>
                        <div class="type-badges">
                            <span class="type-badge type-badge-${comic.type}">${this.escapeHtml(typeLabel)}</span>
                            ${nasBadge}
                        </div>
                    </div>
                </a>
                ${actionsHtml}
            </div>`;
    }

    /**
     * Affiche un message hors ligne.
     */
    showOfflineMessage() {
        if (!this.hasResultsTarget) return;

        this.resultsTarget.innerHTML = `
            <div class="empty-state">
                <p>Aucune donnee en cache. Connectez-vous a Internet pour charger les donnees.</p>
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
