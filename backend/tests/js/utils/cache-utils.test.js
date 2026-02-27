import { getFromCache, saveToCache } from '../../../assets/utils/cache-utils.js';

describe('getFromCache', () => {
    it('retourne null si le cache est vide', () => {
        expect(getFromCache()).toBeNull();
    });

    it('retourne les données désérialisées', () => {
        const data = [{ id: 1, title: 'Naruto' }];
        localStorage.setItem('bibliotheque_comics_cache', JSON.stringify(data));

        expect(getFromCache()).toEqual(data);
    });

    it('utilise la clé par défaut', () => {
        localStorage.setItem('bibliotheque_comics_cache', '"test"');

        expect(getFromCache()).toBe('test');
    });

    it('accepte une clé personnalisée', () => {
        localStorage.setItem('custom_key', JSON.stringify([1, 2]));

        expect(getFromCache('custom_key')).toEqual([1, 2]);
    });

    it('retourne null en cas de JSON invalide', () => {
        localStorage.setItem('bibliotheque_comics_cache', '{invalid');
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(getFromCache()).toBeNull();
        expect(consoleSpy).toHaveBeenCalled();
    });
});

describe('saveToCache', () => {
    it('sauvegarde les données dans localStorage', () => {
        const data = [{ id: 1, title: 'One Piece' }];
        saveToCache(data);

        expect(localStorage.getItem('bibliotheque_comics_cache')).toBe(JSON.stringify(data));
    });

    it('accepte une clé personnalisée', () => {
        saveToCache([42], 'my_cache');

        expect(localStorage.getItem('my_cache')).toBe('[42]');
    });

    it('gère silencieusement les erreurs de stockage', () => {
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const original = localStorage.setItem;
        localStorage.setItem = () => { throw new Error('QuotaExceeded'); };

        expect(() => saveToCache([1])).not.toThrow();
        expect(consoleSpy).toHaveBeenCalled();

        localStorage.setItem = original;
    });
});
