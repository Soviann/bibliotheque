import BarcodeScannerController from '../../../assets/controllers/barcode_scanner_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

function buildHtml() {
    return `
        <div data-controller="barcode-scanner">
            <button data-action="barcode-scanner#open">Scanner</button>
        </div>
    `;
}

describe('barcode_scanner_controller', () => {
    let application;
    let mockStream;
    let mockDetector;

    beforeEach(() => {
        // Mock MediaStream avec track.stop()
        const mockTrack = { stop: vi.fn() };
        mockStream = {
            getTracks: vi.fn(() => [mockTrack]),
        };

        // Mock getUserMedia
        global.navigator.mediaDevices = {
            getUserMedia: vi.fn().mockResolvedValue(mockStream),
        };

        // Mock BarcodeDetector (doit utiliser function, pas arrow, pour new)
        mockDetector = {
            detect: vi.fn().mockResolvedValue([]),
        };
        global.BarcodeDetector = vi.fn(function () {
            return mockDetector;
        });
        global.BarcodeDetector.getSupportedFormats = vi.fn().mockResolvedValue(['ean_13']);

        // Mock navigator.vibrate
        global.navigator.vibrate = vi.fn();
    });

    afterEach(() => {
        if (application) stopStimulusController(application);
        delete global.BarcodeDetector;
    });

    function getController() {
        return application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="barcode-scanner"]'), 'barcode-scanner'
        );
    }

    async function setup() {
        ({ application } = await startStimulusController(
            BarcodeScannerController, 'barcode-scanner', buildHtml()
        ));
        return getController();
    }

    describe('open', () => {
        it('crée le modal plein écran', async () => {
            const controller = await setup();
            await controller.open();

            const modal = document.querySelector('.scanner-modal');
            expect(modal).not.toBeNull();
            expect(modal.querySelector('video')).not.toBeNull();
            expect(modal.querySelector('.scanner-modal__close')).not.toBeNull();
        });

        it('demande accès à la caméra arrière', async () => {
            const controller = await setup();
            await controller.open();

            expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledWith({
                video: { facingMode: 'environment' },
            });
        });

        it('attache le stream au video element', async () => {
            const controller = await setup();
            await controller.open();

            const video = document.querySelector('.scanner-modal video');
            expect(video.srcObject).toBe(mockStream);
        });

        it('crée un BarcodeDetector avec le format ean_13', async () => {
            const controller = await setup();
            await controller.open();

            expect(global.BarcodeDetector).toHaveBeenCalledWith({ formats: ['ean_13'] });
        });
    });

    describe('détection', () => {
        it('émet barcode-scanner:detected quand un code-barres est détecté', async () => {
            const controller = await setup();
            const detectedPromise = new Promise((resolve) => {
                controller.element.addEventListener('barcode-scanner:detected', (e) => {
                    resolve(e.detail);
                });
            });

            // Ouvre le scanner
            await controller.open();

            // Simule une détection au prochain appel
            mockDetector.detect.mockResolvedValueOnce([
                { rawValue: '9782723456789', format: 'ean_13' },
            ]);

            // Déclenche manuellement un cycle de détection
            await controller.detectBarcode();

            const detail = await detectedPromise;
            expect(detail.rawValue).toBe('9782723456789');
            expect(detail.format).toBe('ean_13');
        });

        it('vibre lors de la détection', async () => {
            const controller = await setup();
            await controller.open();

            mockDetector.detect.mockResolvedValueOnce([
                { rawValue: '9782723456789', format: 'ean_13' },
            ]);

            await controller.detectBarcode();

            expect(navigator.vibrate).toHaveBeenCalledWith(200);
        });

        it('ferme le modal après détection', async () => {
            const controller = await setup();
            await controller.open();

            mockDetector.detect.mockResolvedValueOnce([
                { rawValue: '9782723456789', format: 'ean_13' },
            ]);

            await controller.detectBarcode();

            const modal = document.querySelector('.scanner-modal');
            expect(modal).toBeNull();
        });

        it('ne fait rien si aucun code-barres détecté', async () => {
            const controller = await setup();
            const detectedSpy = vi.fn();
            controller.element.addEventListener('barcode-scanner:detected', detectedSpy);

            await controller.open();
            mockDetector.detect.mockResolvedValueOnce([]);

            await controller.detectBarcode();

            expect(detectedSpy).not.toHaveBeenCalled();
            // Le modal est toujours ouvert
            expect(document.querySelector('.scanner-modal')).not.toBeNull();
        });
    });

    describe('close', () => {
        it('arrête le stream caméra', async () => {
            const controller = await setup();
            await controller.open();

            controller.close();

            const track = mockStream.getTracks()[0];
            expect(track.stop).toHaveBeenCalled();
        });

        it('supprime le modal du DOM', async () => {
            const controller = await setup();
            await controller.open();
            expect(document.querySelector('.scanner-modal')).not.toBeNull();

            controller.close();
            expect(document.querySelector('.scanner-modal')).toBeNull();
        });
    });

    describe('BarcodeDetector non supporté', () => {
        it('émet un événement unsupported si BarcodeDetector est absent', async () => {
            delete global.BarcodeDetector;

            const controller = await setup();
            const unsupportedPromise = new Promise((resolve) => {
                controller.element.addEventListener('barcode-scanner:unsupported', () => {
                    resolve(true);
                });
            });

            await controller.open();

            expect(await unsupportedPromise).toBe(true);
        });

        it('ne crée pas de modal si BarcodeDetector est absent', async () => {
            delete global.BarcodeDetector;

            const controller = await setup();
            await controller.open();

            expect(document.querySelector('.scanner-modal')).toBeNull();
        });
    });

    describe('erreur caméra', () => {
        it('émet un événement error si la caméra est refusée', async () => {
            navigator.mediaDevices.getUserMedia.mockRejectedValueOnce(
                new DOMException('Permission denied', 'NotAllowedError')
            );

            const controller = await setup();
            const errorPromise = new Promise((resolve) => {
                controller.element.addEventListener('barcode-scanner:error', (e) => {
                    resolve(e.detail);
                });
            });

            await controller.open();

            const detail = await errorPromise;
            expect(detail.message).toContain('Permission');
        });

        it('nettoie le modal en cas d\'erreur caméra', async () => {
            navigator.mediaDevices.getUserMedia.mockRejectedValueOnce(
                new DOMException('Permission denied', 'NotAllowedError')
            );

            const controller = await setup();
            await controller.open();

            expect(document.querySelector('.scanner-modal')).toBeNull();
        });
    });
});
