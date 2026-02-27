import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour remplacer les confirm() natifs par une modale <dialog>.
 *
 * Usage :
 *   <form data-controller="confirm-modal"
 *         data-confirm-modal-message-value="Supprimer ?"
 *         data-confirm-modal-destructive-value="true"
 *         data-action="submit->confirm-modal#intercept">
 */
export default class extends Controller {
    static values = {
        cancelLabel: { default: 'Annuler', type: String },
        confirmLabel: { default: 'Confirmer', type: String },
        destructive: { default: false, type: Boolean },
        message: String,
    };

    #confirmed = false;
    #dialog = null;

    intercept(event) {
        if (this.#confirmed) {
            this.#confirmed = false;
            return;
        }

        event.preventDefault();

        if (this.#dialog) return;

        this.#dialog = this.#createDialog();
        document.body.appendChild(this.#dialog);
        this.#dialog.showModal();
    }

    disconnect() {
        this.#cleanup();
    }

    #createDialog() {
        const dialog = document.createElement('dialog');
        dialog.classList.add('confirm-modal');

        const content = document.createElement('div');
        content.classList.add('confirm-modal-content');

        const message = document.createElement('p');
        message.classList.add('confirm-modal-message');
        message.textContent = this.messageValue;

        const actions = document.createElement('div');
        actions.classList.add('confirm-modal-actions');

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.classList.add('confirm-modal-cancel');
        cancelBtn.textContent = this.cancelLabelValue;
        cancelBtn.addEventListener('click', () => this.#cancel());

        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.classList.add('confirm-modal-confirm');
        if (this.destructiveValue) {
            confirmBtn.classList.add('confirm-modal-confirm--destructive');
        }
        confirmBtn.textContent = this.confirmLabelValue;
        confirmBtn.addEventListener('click', () => this.#confirm());

        actions.append(cancelBtn, confirmBtn);
        content.append(message, actions);
        dialog.appendChild(content);
        dialog.addEventListener('close', () => this.#cleanup());

        return dialog;
    }

    #confirm() {
        this.#confirmed = true;
        this.#dialog.close();
        this.element.requestSubmit();
    }

    #cancel() {
        this.#dialog.close();
    }

    #cleanup() {
        if (this.#dialog) {
            this.#dialog.remove();
            this.#dialog = null;
        }
    }
}
