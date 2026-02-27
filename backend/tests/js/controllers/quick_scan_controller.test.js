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
        // Nettoie le type picker s'il reste dans le DOM
        document.querySelector('.type-picker')?.remove();
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
        it('affiche le type picker au lieu d\'ouvrir le scanner', async () => {
            const controller = await setup();

            controller.scan();

            expect(document.querySelector('.type-picker')).not.toBeNull();
            expect(document.querySelector('.type-picker__title').textContent).toBe('Type de série');
        });

        it('affiche les 4 types : BD, Comics, Manga, Livre', async () => {
            const controller = await setup();

            controller.scan();

            const options = document.querySelectorAll('.type-picker__option');
            expect(options).toHaveLength(4);

            const labels = Array.from(options).map(o => o.textContent.trim());
            expect(labels).toEqual(['BD', 'Comics', 'Manga', 'Livre']);
        });

        it('ne crée pas de doublon si le picker est déjà ouvert', async () => {
            const controller = await setup();

            controller.scan();
            controller.scan();

            const pickers = document.querySelectorAll('.type-picker');
            expect(pickers).toHaveLength(1);
        });
    });

    describe('sélection de type', () => {
        it('stocke le type sélectionné et ouvre le scanner', async () => {
            const controller = await setup();
            const openSpy = vi.fn();
            controller.element.addEventListener('quick-scan:open-scanner', openSpy);

            controller.scan();

            // Clique sur "Manga"
            const mangaButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'Manga');
            mangaButton.click();

            expect(openSpy).toHaveBeenCalled();
            expect(document.querySelector('.type-picker')).toBeNull();
        });

        it('stocke le type bd quand on clique sur BD', async () => {
            const controller = await setup();
            const openSpy = vi.fn();
            controller.element.addEventListener('quick-scan:open-scanner', openSpy);

            controller.scan();

            const bdButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'BD');
            bdButton.click();

            expect(openSpy).toHaveBeenCalled();
        });
    });

    describe('fermeture du picker', () => {
        it('ferme le picker en cliquant sur le bouton fermer', async () => {
            const controller = await setup();

            controller.scan();
            expect(document.querySelector('.type-picker')).not.toBeNull();

            document.querySelector('.type-picker__close').click();
            expect(document.querySelector('.type-picker')).toBeNull();
        });

        it('ferme le picker en cliquant sur l\'overlay', async () => {
            const controller = await setup();

            controller.scan();
            expect(document.querySelector('.type-picker')).not.toBeNull();

            // Clique sur l'overlay (l'élément racine .type-picker)
            document.querySelector('.type-picker').click();
            expect(document.querySelector('.type-picker')).toBeNull();
        });

        it('ne ferme pas le picker en cliquant sur le sheet', async () => {
            const controller = await setup();

            controller.scan();

            // Clique sur le sheet lui-même (pas l'overlay)
            const clickEvent = new Event('click', { bubbles: true });
            document.querySelector('.type-picker__sheet').dispatchEvent(clickEvent);

            // Le picker est toujours visible (le stopPropagation empêche la fermeture)
            expect(document.querySelector('.type-picker')).not.toBeNull();
        });
    });

    describe('handleDetected', () => {
        it('inclut le type dans l\'appel API', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ title: 'Naruto' }),
                ok: true,
            });

            const assignMock = vi.fn();
            Object.defineProperty(window, 'location', {
                configurable: true,
                value: { ...window.location, assign: assignMock },
                writable: true,
            });

            const controller = await setup();

            // Simule la sélection du type manga puis la détection
            controller.scan();
            const mangaButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'Manga');
            mangaButton.click();

            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });
            await controller.handleDetected(event);

            expect(global.fetch).toHaveBeenCalledWith(
                '/api/isbn-lookup?isbn=9782723456789&type=manga'
            );
        });

        it('inclut le type dans l\'URL de redirection', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ title: 'Naruto' }),
                ok: true,
            });

            const assignMock = vi.fn();
            Object.defineProperty(window, 'location', {
                configurable: true,
                value: { ...window.location, assign: assignMock },
                writable: true,
            });

            const controller = await setup();

            // Simule la sélection du type manga
            controller.scan();
            const mangaButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'Manga');
            mangaButton.click();

            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });
            await controller.handleDetected(event);

            expect(assignMock).toHaveBeenCalledWith(
                '/comic/new?scan_isbn=9782723456789&type=manga'
            );
        });

        it('redirige aussi si l\'ISBN n\'est pas trouvé (avec type)', async () => {
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

            // Simule la sélection du type bd
            controller.scan();
            const bdButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'BD');
            bdButton.click();

            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });
            await controller.handleDetected(event);

            expect(assignMock).toHaveBeenCalledWith(
                '/comic/new?scan_isbn=9782723456789&type=bd'
            );
        });

        it('ajoute la classe loading au bouton pendant l\'appel API', async () => {
            let resolvePromise;
            global.fetch = vi.fn().mockReturnValue(
                new Promise((resolve) => { resolvePromise = resolve; })
            );

            const controller = await setup();
            const button = document.querySelector('.fab-scan');

            // Simule la sélection d'un type
            controller.scan();
            const mangaButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'Manga');
            mangaButton.click();

            // Mock location pour éviter l'erreur
            Object.defineProperty(window, 'location', {
                configurable: true,
                value: { ...window.location, assign: vi.fn() },
                writable: true,
            });

            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
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
