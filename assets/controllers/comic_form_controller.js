import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'authors',
        'authorsWrapper',
        'coverUrl',
        'description',
        'isbn',
        'lookupButton',
        'lookupStatus',
        'publishedDate',
        'publisher',
        'title',
    ];

    connect() {
        this.element.addEventListener('submit', (e) => {
            const title = this.element.querySelector('[name$="[title]"]');
            if (title && !title.value.trim()) {
                e.preventDefault();
                title.focus();
                title.classList.add('error');
            }
        });
    }

    /**
     * Recherche les informations du livre par ISBN via l'API.
     */
    async lookupIsbn() {
        const isbn = this.isbnTarget.value.trim();

        if (!isbn) {
            this.showStatus('Veuillez saisir un ISBN', 'error');
            return;
        }

        this.lookupButtonTarget.disabled = true;
        this.showStatus('Recherche en cours...', 'loading');

        try {
            const response = await fetch(`/api/isbn-lookup?isbn=${encodeURIComponent(isbn)}`);
            const data = await response.json();

            if (!response.ok) {
                this.showStatus(data.error || 'Erreur lors de la recherche', 'error');
                return;
            }

            // Compte le nombre de champs remplis
            let filledCount = 0;

            // Remplit les champs si trouvés et si vides
            filledCount += this.fillIfEmpty('title', data.title);
            filledCount += this.fillAuthors(data.authors);
            filledCount += this.fillIfEmpty('publisher', data.publisher);
            filledCount += this.fillIfEmpty('publishedDate', data.publishedDate);
            filledCount += this.fillIfEmpty('description', data.description);
            filledCount += this.fillIfEmpty('coverUrl', data.thumbnail);

            // Affiche les sources utilisées
            const sourceNames = (data.sources || []).map(s =>
                s === 'google_books' ? 'Google Books' : 'Open Library'
            );
            const sourcesText = sourceNames.join(' + ');
            this.showStatus(`${filledCount} champ(s) rempli(s) via ${sourcesText}`, 'success');
        } catch (error) {
            this.showStatus('Erreur de connexion', 'error');
        } finally {
            this.lookupButtonTarget.disabled = false;
        }
    }

    /**
     * Remplit les auteurs via Tom Select (ux-autocomplete).
     * @returns {number} 1 si des auteurs ont été ajoutés, 0 sinon
     */
    fillAuthors(authorsString) {
        if (!authorsString || !this.hasAuthorsWrapperTarget) {
            return 0;
        }

        // Trouve le select avec Tom Select
        const selectElement = this.authorsWrapperTarget.querySelector('select');
        if (!selectElement || !selectElement.tomselect) {
            return 0;
        }

        const tomSelect = selectElement.tomselect;

        // Vérifie si des auteurs sont déjà sélectionnés
        if (tomSelect.items.length > 0) {
            return 0;
        }

        // Ajoute chaque auteur
        const authorNames = authorsString.split(',').map(s => s.trim()).filter(s => s !== '');
        let addedCount = 0;

        authorNames.forEach(name => {
            // Crée l'option et l'ajoute
            tomSelect.createItem(name, false);
            addedCount++;
        });

        return addedCount > 0 ? 1 : 0;
    }

    /**
     * Remplit un champ s'il est vide.
     * @returns {number} 1 si le champ a été rempli, 0 sinon
     */
    fillIfEmpty(targetName, value) {
        const targetMethod = `has${targetName.charAt(0).toUpperCase() + targetName.slice(1)}Target`;
        const target = `${targetName}Target`;

        if (!this[targetMethod] || !value) {
            return 0;
        }

        if (this[target].value.trim()) {
            return 0;
        }

        this[target].value = value;
        return 1;
    }

    /**
     * Affiche un message de statut.
     */
    showStatus(message, type) {
        if (!this.hasLookupStatusTarget) return;

        this.lookupStatusTarget.textContent = message;
        this.lookupStatusTarget.className = `lookup-status lookup-status--${type}`;

        if (type === 'success' || type === 'error') {
            setTimeout(() => {
                this.lookupStatusTarget.textContent = '';
                this.lookupStatusTarget.className = 'lookup-status';
            }, 5000);
        }
    }
}
