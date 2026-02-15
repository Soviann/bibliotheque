import DatepickerController from '../../../assets/controllers/datepicker_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

function buildHtml() {
    return `
        <div>
            <input type="text" data-controller="datepicker" value="">
        </div>
    `;
}

let app;

async function setup() {
    const { application } = await startStimulusController(DatepickerController, 'datepicker', buildHtml());
    app = application;
}

describe('DatepickerController', () => {
    afterEach(() => {
        if (app) {
            stopStimulusController(app);
            app = null;
        }
    });

    it('exporte un contrôleur Stimulus valide', () => {
        expect(DatepickerController).toBeDefined();
        expect(typeof DatepickerController.prototype.connect).toBe('function');
        expect(typeof DatepickerController.prototype.disconnect).toBe('function');
    });

    it('initialise Flatpickr sur l\'élément', async () => {
        await setup();

        const input = document.querySelector('[data-controller="datepicker"]');
        expect(input.classList.contains('flatpickr-input')).toBe(true);
        expect(input._flatpickr).toBeTruthy();
    });

    it('configure le format YYYY-MM-DD pour la soumission', async () => {
        await setup();

        const input = document.querySelector('[data-controller="datepicker"]');
        expect(input._flatpickr.config.dateFormat).toBe('Y-m-d');
    });

    it('configure altInput avec le format français dd/mm/yyyy', async () => {
        await setup();

        const input = document.querySelector('[data-controller="datepicker"]');
        expect(input._flatpickr.config.altInput).toBe(true);
        expect(input._flatpickr.config.altFormat).toBe('d/m/Y');
    });

    it('crée un bouton effacer et une icône calendrier', async () => {
        await setup();

        const iconsWrapper = document.querySelector('.datepicker-icons');
        expect(iconsWrapper).not.toBeNull();

        const clearBtn = document.querySelector('.datepicker-clear');
        expect(clearBtn).not.toBeNull();
        expect(clearBtn.type).toBe('button');
        expect(clearBtn.getAttribute('aria-label')).toBe('Effacer la date');
        expect(clearBtn.textContent).toBe('\u00d7');

        const calendarBtn = document.querySelector('.datepicker-calendar');
        expect(calendarBtn).not.toBeNull();
        expect(calendarBtn.type).toBe('button');
        expect(calendarBtn.getAttribute('aria-label')).toBe('Ouvrir le calendrier');
        expect(calendarBtn.querySelector('svg')).not.toBeNull();
    });

    it('masque le bouton effacer quand aucune date n\'est sélectionnée', async () => {
        await setup();

        const clearBtn = document.querySelector('.datepicker-clear');
        expect(clearBtn.style.display).toBe('none');
    });

    it('affiche le bouton effacer quand une date est sélectionnée', async () => {
        await setup();

        const input = document.querySelector('[data-controller="datepicker"]');
        input._flatpickr.setDate('2024-06-15', true);

        const clearBtn = document.querySelector('.datepicker-clear');
        expect(clearBtn.style.display).toBe('');
    });

    it('efface la date au clic sur le bouton', async () => {
        await setup();

        const input = document.querySelector('[data-controller="datepicker"]');
        input._flatpickr.setDate('2024-06-15', true);

        const clearBtn = document.querySelector('.datepicker-clear');
        clearBtn.click();

        expect(input.value).toBe('');
        expect(clearBtn.style.display).toBe('none');
    });
});
