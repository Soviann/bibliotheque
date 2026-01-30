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
        'type',
    ];

    // Labels lisibles pour les champs
    static fieldLabels = {
        authors: 'Auteur(s)',
        coverUrl: 'Couverture',
        description: 'Description',
        publishedDate: 'Date de publication',
        publisher: 'Éditeur',
        title: 'Titre',
        type: 'Type',
    };

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

            // Liste des champs remplis
            const filledFields = [];

            // Remplit les champs
            if (this.fillField('title', data.title)) filledFields.push('title');
            if (this.fillAuthors(data.authors)) filledFields.push('authors');
            if (this.fillField('publisher', data.publisher)) filledFields.push('publisher');
            if (this.fillField('publishedDate', data.publishedDate)) filledFields.push('publishedDate');
            if (this.fillField('description', data.description)) filledFields.push('description');
            if (this.fillField('coverUrl', data.thumbnail)) filledFields.push('coverUrl');
            if (this.fillSelect('type', data.type)) filledFields.push('type');

            // Affiche les sources utilisées
            const sourceLabels = {
                'anilist': 'AniList',
                'google_books': 'Google Books',
                'open_library': 'Open Library',
            };
            const sourceNames = (data.sources || []).map(s => sourceLabels[s] || s);
            const sourcesText = sourceNames.join(' + ');

            if (filledFields.length > 0) {
                this.showStatus(`${filledFields.length} champ(s) rempli(s) via ${sourcesText}`, 'success');
                this.showFlashNotification(filledFields, sourcesText);
            } else {
                this.showStatus(`Aucun nouveau champ à remplir (${sourcesText})`, 'success');
            }
        } catch (error) {
            this.showStatus('Erreur de connexion', 'error');
        } finally {
            this.lookupButtonTarget.disabled = false;
        }
    }

    /**
     * Remplit les auteurs via Tom Select (ux-autocomplete).
     * @returns {boolean} true si des auteurs ont été ajoutés
     */
    fillAuthors(authorsString) {
        if (!authorsString || !this.hasAuthorsWrapperTarget) {
            return false;
        }

        // Trouve le select avec Tom Select
        const selectElement = this.authorsWrapperTarget.querySelector('select');
        if (!selectElement || !selectElement.tomselect) {
            return false;
        }

        const tomSelect = selectElement.tomselect;

        // Vérifie si des auteurs sont déjà sélectionnés
        if (tomSelect.items.length > 0) {
            return false;
        }

        // Ajoute chaque auteur
        const authorNames = authorsString.split(',').map(s => s.trim()).filter(s => s !== '');
        let addedCount = 0;

        authorNames.forEach(name => {
            tomSelect.createItem(name, false);
            addedCount++;
        });

        if (addedCount > 0) {
            this.highlightField(this.authorsWrapperTarget);
            return true;
        }

        return false;
    }

    /**
     * Remplit un champ texte s'il est vide.
     * @returns {boolean} true si le champ a été rempli
     */
    fillField(targetName, value) {
        const targetMethod = `has${targetName.charAt(0).toUpperCase() + targetName.slice(1)}Target`;
        const target = `${targetName}Target`;

        if (!this[targetMethod] || !value) {
            return false;
        }

        if (this[target].value.trim()) {
            return false;
        }

        this[target].value = value;
        this.highlightField(this[target]);
        return true;
    }

    /**
     * Remplit un select avec la valeur fournie.
     * @returns {boolean} true si le champ a été modifié
     */
    fillSelect(targetName, value) {
        const targetMethod = `has${targetName.charAt(0).toUpperCase() + targetName.slice(1)}Target`;
        const target = `${targetName}Target`;

        if (!this[targetMethod] || !value) {
            return false;
        }

        const selectElement = this[target];

        // Si déjà la bonne valeur, ne rien faire
        if (selectElement.value === value) {
            return false;
        }

        // Vérifie que la valeur existe dans les options
        const optionExists = Array.from(selectElement.options).some(opt => opt.value === value);
        if (!optionExists) {
            return false;
        }

        selectElement.value = value;
        this.highlightField(selectElement);
        return true;
    }

    /**
     * Met en surbrillance un champ modifié.
     */
    highlightField(element) {
        element.classList.add('field-filled-by-api');
        setTimeout(() => {
            element.classList.remove('field-filled-by-api');
        }, 3000);
    }

    /**
     * Affiche une notification flash en haut de la page.
     */
    showFlashNotification(filledFields, sources) {
        // Supprime une notification existante
        const existing = document.querySelector('.api-lookup-flash');
        if (existing) {
            existing.remove();
        }

        // Crée les labels des champs
        const fieldLabels = filledFields.map(f => this.constructor.fieldLabels[f] || f);

        // Crée la notification
        const flash = document.createElement('div');
        flash.className = 'api-lookup-flash';
        flash.innerHTML = `
            <div class="api-lookup-flash__content">
                <strong>Champs préremplis via ${sources} :</strong>
                <span class="api-lookup-flash__fields">${fieldLabels.join(', ')}</span>
            </div>
            <button type="button" class="api-lookup-flash__close" aria-label="Fermer">&times;</button>
        `;

        // Ajoute l'événement de fermeture
        flash.querySelector('.api-lookup-flash__close').addEventListener('click', () => {
            flash.classList.add('api-lookup-flash--hiding');
            setTimeout(() => flash.remove(), 300);
        });

        // Insère en haut du formulaire
        this.element.insertBefore(flash, this.element.firstChild);

        // Auto-fermeture après 8 secondes
        setTimeout(() => {
            if (flash.parentNode) {
                flash.classList.add('api-lookup-flash--hiding');
                setTimeout(() => flash.remove(), 300);
            }
        }, 8000);
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
