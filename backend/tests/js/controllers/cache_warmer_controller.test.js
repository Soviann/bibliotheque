import CacheWarmerController from '../../../assets/controllers/cache_warmer_controller.js';

/**
 * Teste warmCache() directement plutôt que via Stimulus.
 * Le connect() utilise setTimeout(1000) qui interagit mal avec vi.useFakeTimers()
 * et le cycle de vie Stimulus. On teste la logique métier en isolation.
 */
describe('cache_warmer_controller', () => {
    /**
     * Crée une instance minimale du contrôleur avec les dépendances mockées.
     */
    function createController(urls = []) {
        const controller = new CacheWarmerController();
        // Simule Stimulus values
        controller.urlsValue = urls;
        return controller;
    }

    it('met les URLs API dans le cache api', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: true, clone: () => 'cloned-response' });

        const controller = createController(['/api/comics']);
        await controller.warmCache();

        const apiCache = await caches.open('bibliotheque-api');
        expect(apiCache.put).toHaveBeenCalledWith('/api/comics', 'cloned-response');
    });

    it('met les URLs pages dans le cache pages', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: true, clone: () => 'cloned-response' });

        const controller = createController(['/wishlist']);
        await controller.warmCache();

        const pagesCache = await caches.open('bibliotheque-pages');
        expect(pagesCache.put).toHaveBeenCalledWith('/wishlist', 'cloned-response');
    });

    it('route /api/* vers le cache api et les autres vers pages', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: true, clone: () => 'cloned-response' });

        const controller = createController(['/api/comics', '/']);
        await controller.warmCache();

        const apiCache = await caches.open('bibliotheque-api');
        const pagesCache = await caches.open('bibliotheque-pages');

        expect(apiCache.put).toHaveBeenCalledWith('/api/comics', 'cloned-response');
        expect(pagesCache.put).toHaveBeenCalledWith('/', 'cloned-response');
    });

    it('ne recharge pas une URL déjà en cache', async () => {
        global.fetch = vi.fn();

        // Pré-remplir le cache
        const pagesCache = await caches.open('bibliotheque-pages');
        pagesCache.match = vi.fn().mockResolvedValue('existing-response');

        const controller = createController(['/']);
        await controller.warmCache();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('ignore silencieusement les erreurs fetch', async () => {
        global.fetch = vi.fn().mockRejectedValue(new Error('network error'));

        const controller = createController(['/api/comics']);

        // Ne devrait pas lever d'erreur
        await expect(controller.warmCache()).resolves.not.toThrow();
    });

    it('ne met pas en cache les réponses non-ok', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: false });

        const controller = createController(['/api/comics']);
        await controller.warmCache();

        const apiCache = await caches.open('bibliotheque-api');
        expect(apiCache.put).not.toHaveBeenCalled();
    });

    it('pré-charge plusieurs URLs en parallèle', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: true, clone: () => 'cloned' });

        const controller = createController(['/api/comics', '/', '/wishlist']);
        await controller.warmCache();

        expect(global.fetch).toHaveBeenCalledTimes(3);
    });

    it('envoie les credentials same-origin avec fetch', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: true, clone: () => 'cloned' });

        const controller = createController(['/api/comics']);
        await controller.warmCache();

        expect(global.fetch).toHaveBeenCalledWith('/api/comics', { credentials: 'same-origin' });
    });

    it('ne fait rien si la Cache API n\'est pas disponible', async () => {
        global.fetch = vi.fn();

        // Supprime caches temporairement
        const originalCaches = global.caches;
        delete global.caches;

        const controller = createController(['/api/comics']);
        await controller.warmCache();

        expect(global.fetch).not.toHaveBeenCalled();

        // Restaure
        Object.defineProperty(global, 'caches', { value: originalCaches });
    });
});
