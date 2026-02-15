import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';
import SwUpdateController from '../../../assets/controllers/sw_update_controller.js';

describe('sw_update_controller', () => {
    let application;
    let element;
    let controllerChangeListeners;

    function mockServiceWorker() {
        controllerChangeListeners = [];
        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                addEventListener: vi.fn((event, handler) => {
                    if (event === 'controllerchange') {
                        controllerChangeListeners.push(handler);
                    }
                }),
                controller: { scriptURL: '/sw.js' },
                removeEventListener: vi.fn(),
            },
        });
    }

    function triggerControllerChange() {
        controllerChangeListeners.forEach((fn) => fn());
    }

    beforeEach(() => {
        mockServiceWorker();
    });

    afterEach(() => {
        if (application) stopStimulusController(application);
        delete navigator.serviceWorker;
    });

    async function startController() {
        const result = await startStimulusController(
            SwUpdateController,
            'sw-update',
            '<div data-controller="sw-update"></div>',
        );
        application = result.application;
        element = result.element;
    }

    it('affiche un bandeau quand le SW se met a jour', async () => {
        await startController();

        triggerControllerChange();

        const banner = element.querySelector('.sw-update-banner');
        expect(banner).not.toBeNull();
        expect(banner.textContent).toContain('Nouvelle version disponible');
    });

    it('contient un bouton Rafraichir qui recharge la page', async () => {
        await startController();
        const originalLocation = window.location;
        const reloadMock = vi.fn();
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { reload: reloadMock },
        });

        triggerControllerChange();

        const refreshBtn = element.querySelector('.sw-update-banner__refresh');
        expect(refreshBtn).not.toBeNull();
        refreshBtn.click();
        expect(reloadMock).toHaveBeenCalled();

        Object.defineProperty(window, 'location', {
            configurable: true,
            value: originalLocation,
        });
    });

    it('contient un bouton fermer qui supprime le bandeau', async () => {
        await startController();

        triggerControllerChange();

        const closeBtn = element.querySelector('.sw-update-banner__close');
        expect(closeBtn).not.toBeNull();
        closeBtn.click();
        expect(element.querySelector('.sw-update-banner')).toBeNull();
    });

    it('ne fait rien si serviceWorker non supporte', async () => {
        delete navigator.serviceWorker;

        await startController();

        expect(element.querySelector('.sw-update-banner')).toBeNull();
    });

    it('ne fait rien si aucun SW controleur actif (premier install)', async () => {
        navigator.serviceWorker.controller = null;

        await startController();

        triggerControllerChange();

        expect(element.querySelector('.sw-update-banner')).toBeNull();
    });

    it('n\'affiche qu\'un seul bandeau meme si controllerchange fire plusieurs fois', async () => {
        await startController();

        triggerControllerChange();
        triggerControllerChange();

        const banners = element.querySelectorAll('.sw-update-banner');
        expect(banners.length).toBe(1);
    });
});
