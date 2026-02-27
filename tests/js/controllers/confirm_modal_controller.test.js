import ConfirmModalController from '../../../assets/controllers/confirm_modal_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

function buildHtml(attrs = {}) {
    const message = attrs.message || 'Supprimer cette série ?';
    const destructive = attrs.destructive !== undefined ? attrs.destructive : false;
    const confirmLabel = attrs.confirmLabel || '';
    const cancelLabel = attrs.cancelLabel || '';

    let extra = '';
    if (confirmLabel) extra += ` data-confirm-modal-confirm-label-value="${confirmLabel}"`;
    if (cancelLabel) extra += ` data-confirm-modal-cancel-label-value="${cancelLabel}"`;

    return `
        <form data-controller="confirm-modal"
              data-confirm-modal-message-value="${message}"
              data-confirm-modal-destructive-value="${destructive}"
              ${extra}
              data-action="submit->confirm-modal#intercept"
              action="/test" method="post">
            <button type="submit">Supprimer</button>
        </form>
    `;
}

// Polyfill <dialog> pour jsdom (showModal, close, open)
beforeAll(() => {
    if (!HTMLDialogElement.prototype.showModal) {
        HTMLDialogElement.prototype.showModal = function () {
            this.setAttribute('open', '');
        };
    }
    const originalClose = HTMLDialogElement.prototype.close;
    HTMLDialogElement.prototype.close = function () {
        this.removeAttribute('open');
        if (originalClose) originalClose.call(this);
        this.dispatchEvent(new Event('close'));
    };
});

describe('confirm_modal_controller', () => {
    let application;

    afterEach(() => {
        if (application) stopStimulusController(application);
        // Nettoyer les dialogs restants
        document.querySelectorAll('dialog').forEach((d) => d.remove());
    });

    async function setup(attrs = {}) {
        ({ application } = await startStimulusController(
            ConfirmModalController, 'confirm-modal', buildHtml(attrs),
        ));
    }

    function getForm() {
        return document.querySelector('[data-controller="confirm-modal"]');
    }

    function getDialog() {
        return document.querySelector('dialog');
    }

    describe('intercept', () => {
        it('empêche la soumission du formulaire et affiche un dialog', async () => {
            await setup();
            const form = getForm();
            const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
            form.dispatchEvent(submitEvent);

            expect(submitEvent.defaultPrevented).toBe(true);
            const dialog = getDialog();
            expect(dialog).not.toBeNull();
            expect(dialog.open).toBe(true);
        });

        it('affiche le message configuré dans le dialog', async () => {
            await setup({ message: 'Vraiment supprimer ?' });
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const dialog = getDialog();
            expect(dialog.textContent).toContain('Vraiment supprimer ?');
        });

        it('utilise les labels par défaut pour les boutons', async () => {
            await setup();
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const dialog = getDialog();
            const buttons = dialog.querySelectorAll('button');
            expect(buttons[0].textContent).toBe('Annuler');
            expect(buttons[1].textContent).toBe('Confirmer');
        });

        it('utilise des labels personnalisés pour les boutons', async () => {
            await setup({ confirmLabel: 'Oui', cancelLabel: 'Non' });
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const dialog = getDialog();
            const buttons = dialog.querySelectorAll('button');
            expect(buttons[0].textContent).toBe('Non');
            expect(buttons[1].textContent).toBe('Oui');
        });

        it("n'ouvre pas un second dialog si déjà ouvert", async () => {
            await setup();
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            expect(document.querySelectorAll('dialog').length).toBe(1);
        });
    });

    describe('confirmer', () => {
        it('ferme le dialog et soumet le formulaire', async () => {
            await setup();
            const form = getForm();
            const requestSubmit = vi.fn();
            form.requestSubmit = requestSubmit;

            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const dialog = getDialog();
            const confirmBtn = dialog.querySelector('.confirm-modal-confirm');
            confirmBtn.click();

            expect(dialog.open).toBe(false);
            expect(requestSubmit).toHaveBeenCalled();
        });

        it('bypass l\'interception lors du re-submit après confirmation', async () => {
            await setup();
            const form = getForm();
            let submitCount = 0;
            form.requestSubmit = () => {
                // Simule le re-submit : le flag _confirmed doit empêcher l'interception
                const event = new Event('submit', { bubbles: true, cancelable: true });
                form.dispatchEvent(event);
                if (!event.defaultPrevented) submitCount++;
            };

            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            const confirmBtn = getDialog().querySelector('.confirm-modal-confirm');
            confirmBtn.click();

            expect(submitCount).toBe(1);
        });
    });

    describe('annuler', () => {
        it('ferme le dialog sans soumettre le formulaire', async () => {
            await setup();
            const form = getForm();
            const requestSubmit = vi.fn();
            form.requestSubmit = requestSubmit;

            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const dialog = getDialog();
            const cancelBtn = dialog.querySelector('.confirm-modal-cancel');
            cancelBtn.click();

            expect(dialog.open).toBe(false);
            expect(requestSubmit).not.toHaveBeenCalled();
        });

        it('supprime le dialog du DOM après annulation', async () => {
            await setup();
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const cancelBtn = getDialog().querySelector('.confirm-modal-cancel');
            cancelBtn.click();

            expect(getDialog()).toBeNull();
        });
    });

    describe('mode destructif', () => {
        it('ajoute la classe destructive au bouton confirmer', async () => {
            await setup({ destructive: true });
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const confirmBtn = getDialog().querySelector('.confirm-modal-confirm');
            expect(confirmBtn.classList.contains('confirm-modal-confirm--destructive')).toBe(true);
        });

        it("n'ajoute pas la classe destructive par défaut", async () => {
            await setup({ destructive: false });
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const confirmBtn = getDialog().querySelector('.confirm-modal-confirm');
            expect(confirmBtn.classList.contains('confirm-modal-confirm--destructive')).toBe(false);
        });
    });

    describe('fermeture par Escape (natif dialog)', () => {
        it('supprime le dialog du DOM quand il est fermé', async () => {
            await setup();
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            const dialog = getDialog();
            // Simule la fermeture native (Escape) — le polyfill dispatche 'close'
            dialog.close();

            expect(getDialog()).toBeNull();
        });
    });

    describe('disconnect', () => {
        it('nettoie le dialog ouvert quand le contrôleur se déconnecte', async () => {
            await setup();
            const form = getForm();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

            expect(getDialog()).not.toBeNull();
            stopStimulusController(application);
            application = null;

            expect(getDialog()).toBeNull();
        });
    });
});
