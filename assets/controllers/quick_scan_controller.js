import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur pour la saisie rapide via scan ISBN depuis la page d'accueil.
 * Affiche un type picker, ouvre le scanner, appelle l'API, et redirige vers le formulaire.
 */
export default class extends Controller {
    static types = [
        { label: 'BD', value: 'bd' },
        { label: 'Comics', value: 'comics' },
        { label: 'Manga', value: 'manga' },
        { label: 'Livre', value: 'livre' },
    ];

    /**
     * Affiche le type picker avant d'ouvrir le scanner.
     */
    scan() {
        // Empêche les doublons
        if (document.querySelector('.type-picker')) {
            return;
        }

        this.createTypePicker();
    }

    /**
     * Gère la détection d'un code-barres : appelle l'API avec le type et redirige.
     */
    async handleDetected(event) {
        const isbn = event.detail.rawValue;
        const type = this.selectedType;
        const button = this.element.querySelector('.fab-scan');

        if (button) {
            button.classList.add('fab-scan--loading');
        }

        try {
            const apiUrl = `/api/isbn-lookup?isbn=${encodeURIComponent(isbn)}${type ? `&type=${type}` : ''}`;
            await fetch(apiUrl);
        } catch {
            // Ignore les erreurs réseau, on redirige quand même
        } finally {
            if (button) {
                button.classList.remove('fab-scan--loading');
            }
        }

        const redirectUrl = `/comic/new?scan_isbn=${encodeURIComponent(isbn)}${type ? `&type=${type}` : ''}`;
        window.location.assign(redirectUrl);
    }

    /**
     * Crée le type picker (bottom sheet) dans le DOM.
     */
    createTypePicker() {
        const overlay = document.createElement('div');
        overlay.className = 'type-picker';

        const sheet = document.createElement('div');
        sheet.className = 'type-picker__sheet';

        // Empêche les clics sur le sheet de fermer l'overlay
        sheet.addEventListener('click', (e) => e.stopPropagation());

        const title = document.createElement('h3');
        title.className = 'type-picker__title';
        title.textContent = 'Type de série';

        const options = document.createElement('div');
        options.className = 'type-picker__options';

        this.constructor.types.forEach(({ label, value }) => {
            const button = document.createElement('button');
            button.className = `type-picker__option type-picker__option--${value}`;
            button.type = 'button';
            button.textContent = label;
            button.addEventListener('click', () => this.selectType(value));
            options.appendChild(button);
        });

        const closeButton = document.createElement('button');
        closeButton.className = 'type-picker__close';
        closeButton.type = 'button';
        closeButton.textContent = 'Annuler';
        closeButton.addEventListener('click', () => this.closePicker());

        sheet.appendChild(title);
        sheet.appendChild(options);
        sheet.appendChild(closeButton);
        overlay.appendChild(sheet);

        // Ferme le picker en cliquant sur l'overlay
        overlay.addEventListener('click', () => this.closePicker());

        document.body.appendChild(overlay);
    }

    /**
     * Sélectionne un type, ferme le picker et ouvre le scanner.
     */
    selectType(value) {
        this.selectedType = value;
        this.closePicker();
        this.dispatch('open-scanner');
    }

    /**
     * Ferme le type picker.
     */
    closePicker() {
        const picker = document.querySelector('.type-picker');
        if (picker) {
            picker.remove();
        }
    }
}
