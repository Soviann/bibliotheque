import { Controller } from '@hotwired/stimulus';

/**
 * Affiche un bandeau de notification quand le Service Worker se met a jour.
 * Ecoute l'evenement `controllerchange` sur navigator.serviceWorker.
 */
export default class extends Controller {
    connect() {
        if (!('serviceWorker' in navigator)) return;
        if (!navigator.serviceWorker.controller) return;

        this.handleControllerChange = this.handleControllerChange.bind(this);
        navigator.serviceWorker.addEventListener('controllerchange', this.handleControllerChange);
    }

    disconnect() {
        if ('serviceWorker' in navigator && this.handleControllerChange) {
            navigator.serviceWorker.removeEventListener('controllerchange', this.handleControllerChange);
        }
    }

    handleControllerChange() {
        if (this.element.querySelector('.sw-update-banner')) return;

        const banner = document.createElement('div');
        banner.className = 'sw-update-banner';
        banner.setAttribute('role', 'alert');

        const text = document.createElement('span');
        text.textContent = 'Nouvelle version disponible';

        const refreshBtn = document.createElement('button');
        refreshBtn.className = 'sw-update-banner__refresh';
        refreshBtn.textContent = 'Rafraîchir';
        refreshBtn.addEventListener('click', () => window.location.reload());

        const closeBtn = document.createElement('button');
        closeBtn.className = 'sw-update-banner__close';
        closeBtn.setAttribute('aria-label', 'Fermer');
        closeBtn.textContent = '\u00d7';
        closeBtn.addEventListener('click', () => banner.remove());

        banner.append(text, refreshBtn, closeBtn);
        this.element.appendChild(banner);
    }
}
