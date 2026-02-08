/**
 * Tests d'intégration : flux complet scan → remplissage ISBN → API → remplissage formulaire.
 * Vérifie la communication inter-contrôleurs (barcode-scanner ↔ comic-form / quick-scan).
 */
import BarcodeScannerController from '../../../assets/controllers/barcode_scanner_controller.js';
import ComicFormController from '../../../assets/controllers/comic_form_controller.js';
import QuickScanController from '../../../assets/controllers/quick_scan_controller.js';
import { startStimulusControllers, stopStimulusController } from '../helpers/stimulus-helper.js';

// --- Mocks caméra / BarcodeDetector ---
let mockStream;
let mockDetector;

function setupBrowserMocks() {
    const mockTrack = { stop: vi.fn() };
    mockStream = {
        getTracks: vi.fn(() => [mockTrack]),
    };

    mockDetector = {
        detect: vi.fn().mockResolvedValue([]),
    };

    global.navigator.mediaDevices = {
        getUserMedia: vi.fn().mockResolvedValue(mockStream),
    };
    global.BarcodeDetector = vi.fn(function () {
        return mockDetector;
    });
    global.BarcodeDetector.getSupportedFormats = vi.fn().mockResolvedValue(['ean_13']);
    global.navigator.vibrate = vi.fn();
}

// --- HTML des formulaires ---

function buildFormHtml() {
    return `
        <form data-controller="comic-form barcode-scanner"
              data-action="barcode-scanner:detected->comic-form#handleBarcodeScan">
            <select data-comic-form-target="type">
                <option value="">--</option>
                <option value="manga">Manga</option>
            </select>
            <input data-comic-form-target="title" type="text" value="">
            <input data-comic-form-target="isbn" type="text" value="">
            <input data-comic-form-target="publisher" type="text" value="">
            <input data-comic-form-target="publishedDate" type="text" value="">
            <textarea data-comic-form-target="description"></textarea>
            <input data-comic-form-target="coverUrl" type="text" value="">
            <div data-comic-form-target="authorsWrapper">
                <select multiple></select>
            </div>
            <input data-comic-form-target="isOneShot" type="checkbox">
            <div data-comic-form-target="publishedIssueRow">
                <input data-comic-form-target="latestPublishedIssue" type="number" value="">
                <input data-comic-form-target="latestPublishedIssueComplete" type="checkbox">
            </div>
            <div data-comic-form-target="oneShotIsbnRow" style="display: none;">
                <input data-comic-form-target="oneShotIsbn" type="text" value="">
            </div>
            <div data-comic-form-target="tomesSection">
                <div data-comic-form-target="tomesList">
                    <div data-tomes-collection-target="entry" class="tome-entry">
                        <input class="tome-number-input" name="tomes[0][number]" value="1">
                        <input class="tome-isbn-input" name="tomes[0][isbn]" value="">
                    </div>
                </div>
                <template data-comic-form-target="tomesPrototype">
                    <div data-tomes-collection-target="entry" class="tome-entry">
                        <input class="tome-number-input" name="tomes[__name__][number]" value="">
                        <input class="tome-isbn-input" name="tomes[__name__][isbn]" value="">
                    </div>
                </template>
            </div>
            <button data-comic-form-target="lookupButton">Rechercher ISBN</button>
            <button data-comic-form-target="lookupTitleButton">Rechercher Titre</button>
            <div data-comic-form-target="lookupStatus" class="lookup-status"></div>
        </form>
    `;
}

function buildQuickScanHtml() {
    return `
        <div data-controller="quick-scan barcode-scanner"
             data-action="barcode-scanner:detected->quick-scan#handleDetected">
            <button data-action="barcode-scanner#open" class="fab fab-scan">Scanner</button>
        </div>
    `;
}

// --- Tests d'intégration ---

describe('Intégration : scan code-barres → formulaire', () => {
    let application;

    beforeEach(() => {
        setupBrowserMocks();
    });

    afterEach(() => {
        if (application) stopStimulusController(application);
        delete global.BarcodeDetector;
    });

    describe('Formulaire : scan → ISBN → API → champs remplis', () => {
        it('scanne un ISBN, appelle l\'API et remplit le titre + éditeur + couverture', async () => {
            // Mock API : retourne des données complètes
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    authors: 'Eiichiro Oda',
                    description: 'Aventure pirate',
                    isOneShot: false,
                    publishedDate: '1997',
                    publisher: 'Glénat',
                    sources: ['google_books'],
                    thumbnail: 'https://covers.example.com/onepiece.jpg',
                    title: 'One Piece',
                }),
                ok: true,
            });

            ({ application } = await startStimulusControllers(
                { 'barcode-scanner': BarcodeScannerController, 'comic-form': ComicFormController },
                buildFormHtml()
            ));

            const form = document.querySelector('form');
            const scannerController = application.getControllerForElementAndIdentifier(form, 'barcode-scanner');

            // 1. Ouvre le scanner (crée le modal + caméra)
            await scannerController.open();
            expect(document.querySelector('.scanner-modal')).not.toBeNull();
            expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalled();

            // 2. Simule la détection d'un code-barres
            mockDetector.detect.mockResolvedValueOnce([
                { rawValue: '9782723489102', format: 'ean_13' },
            ]);
            await scannerController.detectBarcode();

            // 3. Vérifie que le modal est fermé
            expect(document.querySelector('.scanner-modal')).toBeNull();

            // 4. Vérifie que le champ ISBN est rempli
            expect(form.querySelector('[data-comic-form-target="isbn"]').value).toBe('9782723489102');

            // 5. Attend que l'appel API soit fait avec l'ISBN scanné
            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    expect.stringContaining('isbn=9782723489102')
                );
            });

            // 6. Vérifie que les champs du formulaire sont remplis par les données API
            await vi.waitFor(() => {
                expect(form.querySelector('[data-comic-form-target="title"]').value).toBe('One Piece');
            });
            expect(form.querySelector('[data-comic-form-target="publisher"]').value).toBe('Glénat');
            expect(form.querySelector('[data-comic-form-target="publishedDate"]').value).toBe('1997');
            expect(form.querySelector('[data-comic-form-target="description"]').value).toBe('Aventure pirate');
            expect(form.querySelector('[data-comic-form-target="coverUrl"]').value).toBe('https://covers.example.com/onepiece.jpg');
        });

        it('scanne un ISBN de tome, remplit le champ tome et appelle l\'API en mode fromTome', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    authors: 'Eiichiro Oda',
                    description: 'Tome 1 de One Piece',
                    publisher: 'Glénat',
                    sources: ['google_books'],
                    thumbnail: 'https://covers.example.com/op1.jpg',
                    title: 'One Piece Vol. 1',
                }),
                ok: true,
            });

            ({ application } = await startStimulusControllers(
                { 'barcode-scanner': BarcodeScannerController, 'comic-form': ComicFormController },
                buildFormHtml()
            ));

            const form = document.querySelector('form');

            // Simule le flux complet : l'utilisateur clique sur le bouton scan d'un tome,
            // le scanner détecte un ISBN, et l'événement arrive avec le contexte "tome-0".
            // En réalité, le data-comic-form-context-param="tome-0" sur le bouton dans le
            // template envoie ce contexte. Ici on simule l'événement résultant.
            const comicFormController = application.getControllerForElementAndIdentifier(form, 'comic-form');
            const scanEvent = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723489119', format: 'ean_13' },
            });
            scanEvent.params = { context: 'tome-0' };
            comicFormController.handleBarcodeScan(scanEvent);

            // Vérifie : le champ ISBN du tome est rempli
            expect(form.querySelector('.tome-isbn-input').value).toBe('9782723489119');

            // Vérifie : l'API est appelée
            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    expect.stringContaining('isbn=9782723489119')
                );
            });

            // Vérifie : les champs série sont remplis, mais PAS les champs volume-spécifiques
            await vi.waitFor(() => {
                expect(form.querySelector('[data-comic-form-target="publisher"]').value).toBe('Glénat');
            });
            expect(form.querySelector('[data-comic-form-target="coverUrl"]').value).toBe('https://covers.example.com/op1.jpg');
            // En mode fromTome, title/description/publishedDate ne sont PAS remplis
            expect(form.querySelector('[data-comic-form-target="title"]').value).toBe('');
            expect(form.querySelector('[data-comic-form-target="description"]').value).toBe('');
        });

        it('gère un ISBN non trouvé sans erreur', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ error: 'ISBN introuvable' }),
                ok: false,
            });

            ({ application } = await startStimulusControllers(
                { 'barcode-scanner': BarcodeScannerController, 'comic-form': ComicFormController },
                buildFormHtml()
            ));

            const form = document.querySelector('form');
            const scannerController = application.getControllerForElementAndIdentifier(form, 'barcode-scanner');

            await scannerController.open();
            mockDetector.detect.mockResolvedValueOnce([
                { rawValue: '0000000000000', format: 'ean_13' },
            ]);
            await scannerController.detectBarcode();

            // Le champ ISBN est quand même rempli
            expect(form.querySelector('[data-comic-form-target="isbn"]').value).toBe('0000000000000');

            // Une notification d'erreur est affichée
            await vi.waitFor(() => {
                const flash = document.querySelector('.api-lookup-flash');
                expect(flash).not.toBeNull();
                expect(flash.textContent).toContain('ISBN introuvable');
            });
        });
    });

    describe('Saisie rapide : FAB scan → type picker → API → redirection', () => {
        it('affiche le type picker, scanne un ISBN, appelle l\'API avec le type et redirige', async () => {
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

            ({ application } = await startStimulusControllers(
                { 'barcode-scanner': BarcodeScannerController, 'quick-scan': QuickScanController },
                buildQuickScanHtml()
            ));

            const container = document.querySelector('[data-controller*="quick-scan"]');
            const quickScanController = application.getControllerForElementAndIdentifier(container, 'quick-scan');
            const scannerController = application.getControllerForElementAndIdentifier(container, 'barcode-scanner');

            // 1. Clique sur le FAB → affiche le type picker
            quickScanController.scan();
            expect(document.querySelector('.type-picker')).not.toBeNull();

            // 2. Sélectionne "Manga" → ferme le picker, ouvre le scanner
            const mangaButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'Manga');
            mangaButton.click();
            expect(document.querySelector('.type-picker')).toBeNull();

            // 3. Ouvre le scanner (déclenché par l'événement open-scanner)
            await scannerController.open();
            expect(document.querySelector('.scanner-modal')).not.toBeNull();

            // 4. Simule la détection
            mockDetector.detect.mockResolvedValueOnce([
                { rawValue: '9782505044123', format: 'ean_13' },
            ]);
            await scannerController.detectBarcode();

            // 5. Le modal est fermé
            expect(document.querySelector('.scanner-modal')).toBeNull();

            // 6. L'API est appelée avec l'ISBN ET le type
            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    '/api/isbn-lookup?isbn=9782505044123&type=manga'
                );
            });

            // 7. Redirection vers le formulaire avec scan_isbn ET type
            await vi.waitFor(() => {
                expect(assignMock).toHaveBeenCalledWith(
                    '/comic/new?scan_isbn=9782505044123&type=manga'
                );
            });
        });

        it('redirige avec le type même si l\'API retourne une erreur réseau', async () => {
            global.fetch = vi.fn().mockRejectedValue(new Error('Network error'));

            const assignMock = vi.fn();
            Object.defineProperty(window, 'location', {
                configurable: true,
                value: { ...window.location, assign: assignMock },
                writable: true,
            });

            ({ application } = await startStimulusControllers(
                { 'barcode-scanner': BarcodeScannerController, 'quick-scan': QuickScanController },
                buildQuickScanHtml()
            ));

            const container = document.querySelector('[data-controller*="quick-scan"]');
            const quickScanController = application.getControllerForElementAndIdentifier(container, 'quick-scan');
            const scannerController = application.getControllerForElementAndIdentifier(container, 'barcode-scanner');

            // Sélectionne un type BD
            quickScanController.scan();
            const bdButton = Array.from(document.querySelectorAll('.type-picker__option'))
                .find(btn => btn.textContent.trim() === 'BD');
            bdButton.click();

            await scannerController.open();
            mockDetector.detect.mockResolvedValueOnce([
                { rawValue: '9782505044123', format: 'ean_13' },
            ]);
            await scannerController.detectBarcode();

            // Même en erreur réseau, la redirection a lieu avec le type
            await vi.waitFor(() => {
                expect(assignMock).toHaveBeenCalledWith(
                    '/comic/new?scan_isbn=9782505044123&type=bd'
                );
            });
        });
    });
});
