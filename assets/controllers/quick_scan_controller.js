import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur pour la saisie rapide via scan ISBN depuis la page d'accueil.
 * Ouvre le scanner barcode, appelle l'API, et redirige vers le formulaire.
 */
export default class extends Controller {
    /**
     * Ouvre le scanner en dispatchant un événement.
     */
    scan() {
        this.dispatch('open-scanner');
    }

    /**
     * Gère la détection d'un code-barres : appelle l'API et redirige.
     */
    async handleDetected(event) {
        const isbn = event.detail.rawValue;
        const button = this.element.querySelector('.fab-scan');

        if (button) {
            button.classList.add('fab-scan--loading');
        }

        try {
            await fetch(`/api/isbn-lookup?isbn=${encodeURIComponent(isbn)}`);
        } catch {
            // Ignore les erreurs réseau, on redirige quand même
        } finally {
            if (button) {
                button.classList.remove('fab-scan--loading');
            }
        }

        window.location.assign(`/comic/new?scan_isbn=${encodeURIComponent(isbn)}`);
    }
}
