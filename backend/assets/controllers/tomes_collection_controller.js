import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour gérer la collection de tomes dans le formulaire ComicSeries.
 */
export default class extends Controller {
    static targets = ['entry', 'list', 'prototype', 'summary'];

    connect() {
        this.index = this.entryTargets.length;
    }

    /**
     * Ajoute un nouveau tome à la collection.
     */
    addTome(event) {
        event.preventDefault();

        const prototype = this.prototypeTarget.innerHTML;
        const newEntry = prototype.replace(/__name__/g, this.index);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = newEntry;
        const entryElement = wrapper.firstElementChild;

        this.listTarget.appendChild(entryElement);
        this.index++;

        // Focus sur le champ numéro
        const numberInput = entryElement.querySelector('.tome-number-input');
        if (numberInput) {
            numberInput.focus();
        }
    }

    /**
     * Supprime un tome de la collection.
     */
    removeTome(event) {
        event.preventDefault();

        const entry = event.target.closest('[data-tomes-collection-target="entry"]');
        if (entry) {
            entry.classList.add('tome-entry--removing');
            setTimeout(() => {
                entry.remove();
            }, 200);
        }
    }
}
