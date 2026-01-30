import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'addTomeButton',
        'authors',
        'authorsWrapper',
        'coverUrl',
        'description',
        'isbn',
        'isOneShot',
        'latestPublishedIssue',
        'latestPublishedIssueComplete',
        'lookupButton',
        'lookupStatus',
        'lookupTitleButton',
        'publishedDate',
        'publishedIssueRow',
        'publisher',
        'title',
        'tomesList',
        'tomesPrototype',
        'tomesSection',
        'type',
    ];

    // Labels lisibles pour les champs
    static fieldLabels = {
        authors: 'Auteur(s)',
        coverUrl: 'Couverture',
        description: 'Description',
        isOneShot: 'One-shot',
        publishedDate: 'Date de publication',
        publisher: 'Éditeur',
        title: 'Titre',
        tomeIsbn: 'ISBN du tome',
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

        // Applique l'état initial du one-shot
        if (this.hasIsOneShotTarget) {
            this.applyOneShotState(this.isOneShotTarget.checked);
        }
    }

    /**
     * Bascule l'affichage one-shot quand la checkbox change.
     */
    toggleOneShot(event) {
        this.applyOneShotState(event.target.checked);
    }

    /**
     * Applique l'état one-shot : gère la collection de tomes et pré-remplit les valeurs.
     */
    applyOneShotState(isOneShot) {
        // Masque la ligne "Dernier tome paru"
        if (this.hasPublishedIssueRowTarget) {
            this.publishedIssueRowTarget.style.display = isOneShot ? 'none' : '';
        }

        // Masque/affiche le bouton "Ajouter un tome"
        if (this.hasAddTomeButtonTarget) {
            this.addTomeButtonTarget.style.display = isOneShot ? 'none' : '';
        }

        // Masque/affiche les boutons de suppression des tomes
        if (this.hasTomesSectionTarget) {
            const removeButtons = this.tomesSectionTarget.querySelectorAll('.tome-remove');
            removeButtons.forEach(btn => {
                btn.style.display = isOneShot ? 'none' : '';
            });

            // Affiche/masque les boutons de recherche ISBN
            const isbnLookupButtons = this.tomesSectionTarget.querySelectorAll('.tome-isbn-lookup');
            isbnLookupButtons.forEach(btn => {
                btn.style.display = isOneShot ? '' : 'none';
            });
        }

        // Pré-remplit les valeurs pour un one-shot
        if (isOneShot) {
            if (this.hasLatestPublishedIssueTarget) {
                this.latestPublishedIssueTarget.value = '1';
            }
            if (this.hasLatestPublishedIssueCompleteTarget) {
                this.latestPublishedIssueCompleteTarget.checked = true;
            }

            // Ajoute un tome avec le numéro 1 si la collection est vide
            this.ensureOneShotTome();
        }
    }

    /**
     * S'assure qu'un tome avec le numéro 1 existe pour un one-shot.
     */
    ensureOneShotTome() {
        if (!this.hasTomesListTarget || !this.hasTomesPrototypeTarget) {
            return;
        }

        // Vérifie si la collection est vide
        const existingEntries = this.tomesListTarget.querySelectorAll('[data-tomes-collection-target="entry"]');
        if (existingEntries.length > 0) {
            // Un tome existe déjà, on met juste le numéro à 1
            const numberInput = existingEntries[0].querySelector('.tome-number-input');
            if (numberInput && !numberInput.value) {
                numberInput.value = '1';
            }
            return;
        }

        // Crée un nouveau tome à partir du prototype
        const prototype = this.tomesPrototypeTarget.innerHTML;
        const newEntry = prototype.replace(/__name__/g, '0');

        const wrapper = document.createElement('div');
        wrapper.innerHTML = newEntry;
        const entryElement = wrapper.firstElementChild;

        // Pré-remplit le numéro à 1
        const numberInput = entryElement.querySelector('.tome-number-input');
        if (numberInput) {
            numberInput.value = '1';
        }

        // Masque le bouton de suppression
        const removeButton = entryElement.querySelector('.tome-remove');
        if (removeButton) {
            removeButton.style.display = 'none';
        }

        this.tomesListTarget.appendChild(entryElement);

        // Affiche le bouton de recherche ISBN si one-shot est coché
        if (this.hasIsOneShotTarget && this.isOneShotTarget.checked) {
            const isbnLookupButton = entryElement.querySelector('.tome-isbn-lookup');
            if (isbnLookupButton) {
                isbnLookupButton.style.display = '';
            }
        }
    }

    /**
     * Recherche les informations du livre par ISBN du tome (pour les one-shots).
     */
    async lookupTomeIsbn(event) {
        const button = event.currentTarget;
        const tomeEntry = button.closest('.tome-entry');
        const isbnInput = tomeEntry.querySelector('.tome-isbn-input');
        const isbn = isbnInput ? isbnInput.value.trim() : '';

        if (!isbn) {
            this.showFlashError('Veuillez saisir un ISBN');
            isbnInput?.focus();
            return;
        }

        // Récupère le type sélectionné
        const type = this.hasTypeTarget ? this.typeTarget.value : null;

        button.disabled = true;

        try {
            const url = `/api/isbn-lookup?isbn=${encodeURIComponent(isbn)}${type ? `&type=${type}` : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!response.ok) {
                this.showFlashError(data.error || 'Erreur lors de la recherche');
                return;
            }

            // Liste des champs remplis
            const filledFields = [];

            // Remplit les champs de la série
            if (this.fillField('title', data.title)) filledFields.push('title');
            if (this.fillAuthors(data.authors)) filledFields.push('authors');
            if (this.fillField('publisher', data.publisher)) filledFields.push('publisher');
            if (this.fillField('publishedDate', data.publishedDate)) filledFields.push('publishedDate');
            if (this.fillField('description', data.description)) filledFields.push('description');
            if (this.fillField('coverUrl', data.thumbnail)) filledFields.push('coverUrl');

            // Gère le one-shot détecté (coche la case si pas déjà cochée)
            if (data.isOneShot === true && this.hasIsOneShotTarget && !this.isOneShotTarget.checked) {
                this.isOneShotTarget.checked = true;
                this.applyOneShotState(true);
                filledFields.push('isOneShot');
            }

            // Affiche les sources utilisées
            const sourceLabels = {
                'anilist': 'AniList',
                'google_books': 'Google Books',
                'open_library': 'Open Library',
            };
            const sourceNames = (data.sources || []).map(s => sourceLabels[s] || s);
            const sourcesText = sourceNames.join(' + ');

            if (filledFields.length > 0) {
                this.showFlashNotification(filledFields, sourcesText);
            } else {
                this.showFlashInfo(`Aucun nouveau champ à remplir (${sourcesText})`);
            }
        } catch (error) {
            this.showFlashError('Erreur de connexion');
        } finally {
            button.disabled = false;
        }
    }

    /**
     * Recherche les informations par titre via l'API.
     */
    async lookupByTitle() {
        if (!this.hasTitleTarget) {
            return;
        }

        const title = this.titleTarget.value.trim();

        if (!title) {
            this.showFlashError('Veuillez saisir un titre');
            this.titleTarget.focus();
            return;
        }

        // Récupère le type sélectionné
        const type = this.hasTypeTarget ? this.typeTarget.value : null;

        if (this.hasLookupTitleButtonTarget) {
            this.lookupTitleButtonTarget.disabled = true;
        }

        try {
            const url = `/api/title-lookup?title=${encodeURIComponent(title)}${type ? `&type=${type}` : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!response.ok) {
                this.showFlashError(data.error || 'Erreur lors de la recherche');
                return;
            }

            // Liste des champs remplis
            const filledFields = [];

            // Remplit les champs (ne remplace pas le titre car l'utilisateur l'a saisi)
            if (this.fillAuthors(data.authors)) filledFields.push('authors');
            if (this.fillField('publisher', data.publisher)) filledFields.push('publisher');
            if (this.fillField('publishedDate', data.publishedDate)) filledFields.push('publishedDate');
            if (this.fillField('description', data.description)) filledFields.push('description');
            if (this.fillField('coverUrl', data.thumbnail)) filledFields.push('coverUrl');

            // Gère le one-shot détecté
            if (data.isOneShot === true && this.hasIsOneShotTarget && !this.isOneShotTarget.checked) {
                this.isOneShotTarget.checked = true;
                this.applyOneShotState(true);
                filledFields.push('isOneShot');
            }

            // Pré-remplit l'ISBN du tome si one-shot détecté et ISBN disponible
            if (data.isOneShot === true && data.isbn) {
                if (this.fillTomeIsbn(data.isbn)) {
                    filledFields.push('tomeIsbn');
                }
            }

            // Affiche les sources utilisées
            const sourceLabels = {
                'anilist': 'AniList',
                'google_books': 'Google Books',
                'open_library': 'Open Library',
            };
            const sourceNames = (data.sources || []).map(s => sourceLabels[s] || s);
            const sourcesText = sourceNames.join(' + ');

            if (filledFields.length > 0) {
                this.showFlashNotification(filledFields, sourcesText);
            } else {
                this.showFlashInfo(`Aucun nouveau champ à remplir (${sourcesText})`);
            }
        } catch (error) {
            this.showFlashError('Erreur de connexion');
        } finally {
            if (this.hasLookupTitleButtonTarget) {
                this.lookupTitleButtonTarget.disabled = false;
            }
        }
    }

    /**
     * Affiche une notification flash d'erreur.
     */
    showFlashError(message) {
        this.showFlashMessage(message, 'error');
    }

    /**
     * Affiche une notification flash d'info.
     */
    showFlashInfo(message) {
        this.showFlashMessage(message, 'info');
    }

    /**
     * Affiche une notification flash générique.
     */
    showFlashMessage(message, type) {
        const existing = document.querySelector('.api-lookup-flash');
        if (existing) {
            existing.remove();
        }

        const flash = document.createElement('div');
        flash.className = `api-lookup-flash api-lookup-flash--${type}`;
        flash.innerHTML = `
            <div class="api-lookup-flash__content">
                <span>${message}</span>
            </div>
            <button type="button" class="api-lookup-flash__close" aria-label="Fermer">&times;</button>
        `;

        flash.querySelector('.api-lookup-flash__close').addEventListener('click', () => {
            flash.classList.add('api-lookup-flash--hiding');
            setTimeout(() => flash.remove(), 300);
        });

        this.element.insertBefore(flash, this.element.firstChild);

        setTimeout(() => {
            if (flash.parentNode) {
                flash.classList.add('api-lookup-flash--hiding');
                setTimeout(() => flash.remove(), 300);
            }
        }, 5000);
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
     * Remplit l'ISBN du premier tome (pour les one-shots).
     * @returns {boolean} true si le champ a été rempli
     */
    fillTomeIsbn(isbn) {
        if (!isbn || !this.hasTomesListTarget) {
            return false;
        }

        // Trouve le premier tome
        const firstTome = this.tomesListTarget.querySelector('[data-tomes-collection-target="entry"]');
        if (!firstTome) {
            return false;
        }

        const isbnInput = firstTome.querySelector('.tome-isbn-input');
        if (!isbnInput || isbnInput.value.trim()) {
            return false;
        }

        isbnInput.value = isbn;
        this.highlightField(isbnInput);
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
