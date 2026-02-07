import TomesCollectionController from '../../../assets/controllers/tomes_collection_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

const PROTOTYPE_HTML = `
    <div data-tomes-collection-target="entry" class="tome-entry">
        <input class="tome-number-input" name="tomes[__name__][number]" value="">
        <input class="tome-isbn-input" name="tomes[__name__][isbn]" value="">
        <button data-action="tomes-collection#removeTome" class="tome-remove">Supprimer</button>
    </div>
`;

function buildHtml(entries = '') {
    return `
        <div data-controller="tomes-collection">
            <div data-tomes-collection-target="list">
                ${entries}
            </div>
            <template data-tomes-collection-target="prototype">${PROTOTYPE_HTML}</template>
            <button data-action="tomes-collection#addTome">Ajouter</button>
        </div>
    `;
}

describe('tomes_collection_controller', () => {
    let application;

    afterEach(() => {
        if (application) stopStimulusController(application);
    });

    describe('addTome', () => {
        it('ajoute un tome à la liste', async () => {
            ({ application } = await startStimulusController(
                TomesCollectionController, 'tomes-collection', buildHtml()
            ));

            const addButton = document.querySelector('[data-action="tomes-collection#addTome"]');
            addButton.click();

            const entries = document.querySelectorAll('[data-tomes-collection-target="entry"]');
            expect(entries).toHaveLength(1);
        });

        it('remplace __name__ par l\'index courant', async () => {
            ({ application } = await startStimulusController(
                TomesCollectionController, 'tomes-collection', buildHtml()
            ));

            const addButton = document.querySelector('[data-action="tomes-collection#addTome"]');
            addButton.click();

            const input = document.querySelector('.tome-number-input');
            expect(input.name).toBe('tomes[0][number]');
        });

        it('incrémente l\'index à chaque ajout', async () => {
            ({ application } = await startStimulusController(
                TomesCollectionController, 'tomes-collection', buildHtml()
            ));

            const addButton = document.querySelector('[data-action="tomes-collection#addTome"]');
            addButton.click();
            addButton.click();

            const inputs = document.querySelectorAll('.tome-number-input');
            expect(inputs[0].name).toBe('tomes[0][number]');
            expect(inputs[1].name).toBe('tomes[1][number]');
        });

        it('met le focus sur le champ numéro', async () => {
            ({ application } = await startStimulusController(
                TomesCollectionController, 'tomes-collection', buildHtml()
            ));

            const addButton = document.querySelector('[data-action="tomes-collection#addTome"]');
            addButton.click();

            const input = document.querySelector('.tome-number-input');
            expect(document.activeElement).toBe(input);
        });

        it('initialise l\'index au nombre d\'entrées existantes', async () => {
            const existingEntry = `
                <div data-tomes-collection-target="entry" class="tome-entry">
                    <input class="tome-number-input" name="tomes[0][number]" value="1">
                </div>
            `;
            ({ application } = await startStimulusController(
                TomesCollectionController, 'tomes-collection', buildHtml(existingEntry)
            ));

            const addButton = document.querySelector('[data-action="tomes-collection#addTome"]');
            addButton.click();

            const inputs = document.querySelectorAll('.tome-number-input');
            expect(inputs[1].name).toBe('tomes[1][number]');
        });
    });

    describe('removeTome', () => {
        it('ajoute la classe --removing à l\'entrée', async () => {
            ({ application } = await startStimulusController(
                TomesCollectionController, 'tomes-collection', buildHtml()
            ));

            // Ajouter un tome via le contrôleur
            const addButton = document.querySelector('[data-action="tomes-collection#addTome"]');
            addButton.click();

            // Appeler removeTome directement avec un event simulé
            const controller = application.getControllerForElementAndIdentifier(
                document.querySelector('[data-controller="tomes-collection"]'), 'tomes-collection'
            );
            const removeButton = document.querySelector('.tome-remove');
            controller.removeTome({ preventDefault: () => {}, target: removeButton });

            const entry = document.querySelector('[data-tomes-collection-target="entry"]');
            expect(entry.classList.contains('tome-entry--removing')).toBe(true);
        });

        it('supprime l\'entrée après 200ms', async () => {
            ({ application } = await startStimulusController(
                TomesCollectionController, 'tomes-collection', buildHtml()
            ));

            const addButton = document.querySelector('[data-action="tomes-collection#addTome"]');
            addButton.click();

            const controller = application.getControllerForElementAndIdentifier(
                document.querySelector('[data-controller="tomes-collection"]'), 'tomes-collection'
            );

            // Activer les fake timers APRÈS que Stimulus soit connecté
            vi.useFakeTimers();

            const removeButton = document.querySelector('.tome-remove');
            controller.removeTome({ preventDefault: () => {}, target: removeButton });

            // Pas encore supprimé
            expect(document.querySelectorAll('[data-tomes-collection-target="entry"]')).toHaveLength(1);

            vi.advanceTimersByTime(200);

            expect(document.querySelectorAll('[data-tomes-collection-target="entry"]')).toHaveLength(0);

            vi.useRealTimers();
        });
    });
});
