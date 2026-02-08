import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour le scan de code-barres ISBN via la caméra.
 *
 * Utilise l'API native BarcodeDetector (Chrome Android 83+).
 * Émet des événements : detected, unsupported, error.
 */
export default class extends Controller {
    static values = {
        formats: { default: ['ean_13'], type: Array },
    };

    connect() {
        this.detector = null;
        this.modal = null;
        this.scanning = false;
        this.stream = null;
    }

    /**
     * Ouvre le scanner : crée le modal, démarre la caméra et la boucle de détection.
     */
    async open() {
        if (typeof BarcodeDetector === 'undefined') {
            this.dispatch('unsupported');
            return;
        }

        this.detector = new BarcodeDetector({ formats: this.formatsValue });

        this.createModal();

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
            });

            const video = this.modal.querySelector('video');
            video.srcObject = this.stream;
            await video.play();

            this.scanning = true;
            this.startDetectionLoop();
        } catch (error) {
            this.dispatch('error', { detail: { message: error.message } });
            this.destroyModal();
        }
    }

    /**
     * Ferme le scanner : arrête le stream et supprime le modal.
     */
    close() {
        this.scanning = false;
        this.stopStream();
        this.destroyModal();
    }

    /**
     * Effectue un cycle de détection (exposé pour les tests).
     */
    async detectBarcode() {
        if (!this.detector || !this.modal) {
            return;
        }

        const video = this.modal.querySelector('video');

        try {
            const barcodes = await this.detector.detect(video);

            if (barcodes.length > 0) {
                const { format, rawValue } = barcodes[0];

                if (navigator.vibrate) {
                    navigator.vibrate(200);
                }

                this.dispatch('detected', { detail: { format, rawValue } });
                this.close();
            }
        } catch {
            // Ignore les erreurs de détection (frame pas prête, etc.)
        }
    }

    /**
     * Boucle de détection avec throttle ~300ms.
     */
    startDetectionLoop() {
        let lastDetection = 0;

        const loop = async () => {
            if (!this.scanning) {
                return;
            }

            const now = performance.now();
            if (now - lastDetection >= 300) {
                lastDetection = now;
                await this.detectBarcode();
            }

            if (this.scanning) {
                requestAnimationFrame(loop);
            }
        };

        requestAnimationFrame(loop);
    }

    /**
     * Crée le modal plein écran avec le flux vidéo.
     */
    createModal() {
        this.modal = document.createElement('div');
        this.modal.className = 'scanner-modal';
        this.modal.innerHTML = `
            <video autoplay playsinline muted></video>
            <div class="scanner-modal__overlay">
                <div class="scanner-modal__scan-area">
                    <div class="scanner-modal__scan-line"></div>
                </div>
            </div>
            <button type="button" class="scanner-modal__close" aria-label="Fermer le scanner">&times;</button>
        `;

        this.modal.querySelector('.scanner-modal__close').addEventListener('click', () => this.close());

        document.body.appendChild(this.modal);
    }

    /**
     * Supprime le modal du DOM.
     */
    destroyModal() {
        if (this.modal) {
            this.modal.remove();
            this.modal = null;
        }
    }

    /**
     * Arrête le stream caméra.
     */
    stopStream() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }

    disconnect() {
        this.close();
    }
}
