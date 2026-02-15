import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';

/**
 * Contrôleur Stimulus pour initialiser Flatpickr sur un champ de date.
 *
 * Usage : <input data-controller="datepicker" type="text">
 *
 * La valeur soumise au serveur est en format YYYY-MM-DD (dateFormat).
 * L'affichage utilisateur est en format DD/MM/YYYY (altFormat).
 * Un bouton "effacer" apparaît quand une date est sélectionnée.
 * Une icône calendrier permet d'ouvrir le datepicker.
 */
export default class extends Controller {
    connect() {
        this.picker = flatpickr(this.element, {
            allowInput: true,
            altFormat: 'd/m/Y',
            altInput: true,
            dateFormat: 'Y-m-d',
            locale: French,
            onChange: () => this.toggleClearButton(),
        });

        this.createIcons();
        this.toggleClearButton();
    }

    disconnect() {
        if (this.iconsWrapper) {
            this.iconsWrapper.remove();
            this.iconsWrapper = null;
        }

        if (this.picker) {
            this.picker.destroy();
        }
    }

    createIcons() {
        this.iconsWrapper = document.createElement('span');
        this.iconsWrapper.className = 'datepicker-icons';

        // Bouton effacer
        this.clearBtn = document.createElement('button');
        this.clearBtn.type = 'button';
        this.clearBtn.className = 'datepicker-clear';
        this.clearBtn.setAttribute('aria-label', 'Effacer la date');
        this.clearBtn.textContent = '\u00d7';
        this.clearBtn.addEventListener('click', () => {
            this.picker.clear();
            this.toggleClearButton();
        });

        // Icône calendrier
        const calendarBtn = document.createElement('button');
        calendarBtn.type = 'button';
        calendarBtn.className = 'datepicker-calendar';
        calendarBtn.setAttribute('aria-label', 'Ouvrir le calendrier');
        calendarBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>';
        calendarBtn.addEventListener('click', () => this.picker.open());

        this.iconsWrapper.appendChild(this.clearBtn);
        this.iconsWrapper.appendChild(calendarBtn);

        // Insère après l'altInput (l'input visible créé par Flatpickr)
        const altInput = this.picker.altInput || this.element;
        altInput.parentNode.insertBefore(this.iconsWrapper, altInput.nextSibling);
    }

    toggleClearButton() {
        if (this.clearBtn) {
            this.clearBtn.style.display = this.element.value ? '' : 'none';
        }
    }
}
