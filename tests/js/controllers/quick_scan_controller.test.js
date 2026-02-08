import QuickScanController from '../../../assets/controllers/quick_scan_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

function buildHtml() {
    return `
        <div data-controller="quick-scan barcode-scanner">
            <button data-action="quick-scan#scan" class="fab fab-scan">Scanner</button>
        </div>
    `;
}

describe('quick_scan_controller', () => {
    let application;

    afterEach(() => {
        if (application) stopStimulusController(application);
    });

    function getController() {
        return application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller*="quick-scan"]'), 'quick-scan'
        );
    }

    async function setup() {
        ({ application } = await startStimulusController(
            QuickScanController, 'quick-scan', buildHtml()
        ));
        return getController();
    }

    describe('scan', () => {
        it('dispatche un événement pour ouvrir le scanner', async () => {
            const controller = await setup();
            const openSpy = vi.fn();
            controller.element.addEventListener('quick-scan:open-scanner', openSpy);

            controller.scan();

            expect(openSpy).toHaveBeenCalled();
        });
    });

    describe('handleDetected', () => {
        it('appelle l\'API isbn-lookup avec l\'ISBN scanné', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ title: 'Naruto' }),
                ok: true,
            });

            const controller = await setup();
            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });

            await controller.handleDetected(event);

            expect(global.fetch).toHaveBeenCalledWith(
                '/api/isbn-lookup?isbn=9782723456789'
            );
        });

        it('redirige vers le formulaire avec scan_isbn si trouvé', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ title: 'Naruto' }),
                ok: true,
            });

            // Mock window.location
            const assignMock = vi.fn();
            Object.defineProperty(window, 'location', {
                configurable: true,
                value: { ...window.location, assign: assignMock },
                writable: true,
            });

            const controller = await setup();
            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });

            await controller.handleDetected(event);

            expect(assignMock).toHaveBeenCalledWith(
                '/comic/new?scan_isbn=9782723456789'
            );
        });

        it('redirige aussi si l\'ISBN n\'est pas trouvé', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ error: 'Not found' }),
                ok: false,
            });

            const assignMock = vi.fn();
            Object.defineProperty(window, 'location', {
                configurable: true,
                value: { ...window.location, assign: assignMock },
                writable: true,
            });

            const controller = await setup();
            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });

            await controller.handleDetected(event);

            expect(assignMock).toHaveBeenCalledWith(
                '/comic/new?scan_isbn=9782723456789'
            );
        });

        it('ajoute la classe loading au bouton pendant l\'appel API', async () => {
            let resolvePromise;
            global.fetch = vi.fn().mockReturnValue(
                new Promise((resolve) => { resolvePromise = resolve; })
            );

            const controller = await setup();
            const button = document.querySelector('.fab-scan');
            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });

            // Mock location pour éviter l'erreur
            Object.defineProperty(window, 'location', {
                configurable: true,
                value: { ...window.location, assign: vi.fn() },
                writable: true,
            });

            const handlePromise = controller.handleDetected(event);
            expect(button.classList.contains('fab-scan--loading')).toBe(true);

            resolvePromise({
                json: () => Promise.resolve({}),
                ok: true,
            });

            await handlePromise;
            expect(button.classList.contains('fab-scan--loading')).toBe(false);
        });
    });
});
