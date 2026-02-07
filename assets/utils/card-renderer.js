/**
 * Utilitaires pour le rendu des cartes de comics.
 */

import { escapeHtml } from './string-utils.js';

/** @type {Object} Labels par défaut des statuts */
const STATUS_LABELS = {
    buying: "En cours d'achat",
    finished: 'Terminee',
    stopped: 'Arretee',
    wishlist: 'Liste de souhaits',
};

/** @type {Object} Labels par défaut des types */
const TYPE_LABELS = {
    bd: 'BD',
    comics: 'Comics',
    livre: 'Livre',
    manga: 'Manga',
};

/**
 * Génère le HTML des informations d'un comic (tomes, parus, etc.).
 *
 * @param {Object} comic
 * @returns {string}
 */
function renderComicInfo(comic) {
    let infoHtml = '';

    if (comic.isOneShot) {
        return `
            <div class="comic-info-row">
                <span class="comic-info-label">Type</span>
                <span class="comic-info-value">Tome unique</span>
            </div>`;
    }

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

    return infoHtml;
}

/**
 * Génère le HTML d'une carte de comic.
 *
 * @param {Object} comic - Données du comic
 * @param {Object} options - Options de rendu
 * @param {boolean} options.showAddButton - Afficher le bouton "Ajouter" (wishlist)
 * @param {Object} options.statusLabels - Labels personnalisés des statuts
 * @param {Object} options.typeLabels - Labels personnalisés des types
 * @returns {string}
 */
export function renderCard(comic, options = {}) {
    const {
        showAddButton = false,
        statusLabels = STATUS_LABELS,
        typeLabels = TYPE_LABELS,
    } = options;

    const coverHtml = comic.coverUrl
        ? `<div class="comic-card-cover"><img src="${escapeHtml(comic.coverUrl)}" alt="Couverture de ${escapeHtml(comic.title)}" loading="lazy"></div>`
        : '';

    const infoHtml = renderComicInfo(comic);
    const nasBadge = comic.hasNasTome ? '<span class="type-badge type-badge-nas">NAS</span>' : '';
    const statusLabel = comic.statusLabel || statusLabels[comic.status] || comic.status;
    const typeLabel = comic.typeLabel || typeLabels[comic.type] || comic.type;

    // Actions
    let actionsHtml = `
        <div class="comic-card-actions">
            <a href="/comic/${comic.id}/edit" class="btn btn-outlined btn-full-width" data-turbo-frame="_top">Modifier</a>`;

    if (showAddButton) {
        actionsHtml += `
            <form action="/comic/${comic.id}/to-library" method="post" class="inline-form" data-turbo-frame="_top">
                <input type="hidden" name="_token" value="${escapeHtml(comic.toLibraryToken || '')}">
                <button type="submit" class="btn btn-success btn-full-width">Ajouter à la bibliothèque</button>
            </form>`;
    }

    actionsHtml += `
            <form action="/comic/${comic.id}/delete" method="post" class="inline-form" data-turbo-frame="_top" onsubmit="return confirm('Supprimer cette serie ?');">
                <input type="hidden" name="_token" value="${escapeHtml(comic.deleteToken || '')}">
                <button type="submit" class="btn btn-danger btn-full-width">Supprimer</button>
            </form>
        </div>`;

    return `
        <div class="comic-card">
            <a href="/comic/${comic.id}" class="comic-card-link" data-turbo-frame="_top">
                ${coverHtml}
                <div class="comic-card-content">
                    <div class="comic-card-title">
                        <h3>${escapeHtml(comic.title)}</h3>
                        <span class="status-badge status-${comic.status}">${escapeHtml(statusLabel)}</span>
                    </div>
                    <div class="comic-info">
                        ${infoHtml}
                    </div>
                    <div class="type-badges">
                        <span class="type-badge type-badge-${comic.type}">${escapeHtml(typeLabel)}</span>
                        ${nasBadge}
                    </div>
                </div>
            </a>
            ${actionsHtml}
        </div>`;
}
