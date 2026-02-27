import SearchController from '../../../assets/controllers/search_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

const COMICS_DATA = [
    { id: 1, title: 'Naruto', authors: 'Masashi Kishimoto', description: 'Ninja manga', status: 'buying', type: 'manga', coverUrl: null, isOneShot: false, hasNasTome: false },
    { id: 2, title: 'One Piece', authors: 'Eiichiro Oda', description: 'Pirate manga', status: 'buying', type: 'manga', coverUrl: null, isOneShot: false, hasNasTome: true },
    { id: 3, title: 'Astérix', authors: 'Goscinny, Uderzo', description: 'BD franco-belge', status: 'finished', type: 'bd', coverUrl: null, isOneShot: false, hasNasTome: false },
];

function buildHtml() {
    return `
        <div data-controller="search">
            <input data-search-target="input" type="text" value="">
            <div data-search-target="results"></div>
        </div>
    `;
}

describe('search_controller', () => {
    let application;

    beforeEach(() => {
        global.fetch = vi.fn().mockResolvedValue({
            json: () => Promise.resolve(COMICS_DATA),
            ok: true,
        });
        vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
    });

    afterEach(() => {
        if (application) stopStimulusController(application);
    });

    function getController() {
        return application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="search"]'), 'search'
        );
    }

    describe('loadComics', () => {
        it('charge les données depuis le cache puis l\'API', async () => {
            localStorage.setItem('bibliotheque_comics_cache', JSON.stringify([COMICS_DATA[0]]));

            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));

            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith('/api/comics');
            });
        });

        it('sauvegarde les données de l\'API dans le cache', async () => {
            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));

            await vi.waitFor(() => {
                expect(localStorage.getItem('bibliotheque_comics_cache')).toBe(JSON.stringify(COMICS_DATA));
            });
        });

        it('utilise le cache en mode offline', async () => {
            global.fetch = vi.fn().mockRejectedValue(new Error('offline'));
            localStorage.setItem('bibliotheque_comics_cache', JSON.stringify(COMICS_DATA));
            const consoleSpy = vi.spyOn(console, 'log').mockImplementation(() => {});

            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));

            await vi.waitFor(() => {
                expect(consoleSpy).toHaveBeenCalledWith('Mode hors ligne, utilisation du cache');
            });
        });
    });

    describe('search (debounce)', () => {
        it('attend 300ms avant d\'effectuer la recherche', async () => {
            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));

            const controller = getController();
            controller.comics = COMICS_DATA;

            // Maintenant activer les fake timers
            vi.useFakeTimers();

            const input = document.querySelector('[data-search-target="input"]');
            input.value = 'naruto';
            controller.search();

            // Pas encore de résultats
            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toBe('');

            vi.advanceTimersByTime(300);

            expect(results.innerHTML).toContain('Naruto');

            vi.useRealTimers();
        });

        it('affiche l\'état vide quand la query est effacée', async () => {
            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));

            const controller = getController();
            controller.comics = COMICS_DATA;

            vi.useFakeTimers();

            const input = document.querySelector('[data-search-target="input"]');
            input.value = '';
            controller.search();

            vi.advanceTimersByTime(300);

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('Entrez un terme de recherche');

            vi.useRealTimers();
        });
    });

    describe('performSearch', () => {
        async function setupWithComics() {
            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));
            const controller = getController();
            controller.comics = COMICS_DATA;
            return controller;
        }

        it('filtre par titre', async () => {
            const controller = await setupWithComics();
            controller.performSearch('naruto');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('Naruto');
            expect(results.innerHTML).not.toContain('Astérix');
        });

        it('filtre par auteur', async () => {
            const controller = await setupWithComics();
            controller.performSearch('goscinny');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('Astérix');
            expect(results.innerHTML).not.toContain('Naruto');
        });

        it('filtre par description', async () => {
            const controller = await setupWithComics();
            controller.performSearch('pirate');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('One Piece');
        });

        it('est insensible aux accents', async () => {
            const controller = await setupWithComics();
            controller.performSearch('asterix');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('Astérix');
        });
    });

    describe('renderResults', () => {
        async function setupWithComics() {
            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));
            const controller = getController();
            controller.comics = COMICS_DATA;
            return controller;
        }

        it('affiche le compteur de résultats', async () => {
            const controller = await setupWithComics();
            controller.performSearch('manga');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('2 resultat(s)');
        });

        it('affiche un message quand aucun résultat', async () => {
            const controller = await setupWithComics();
            controller.performSearch('inexistant');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('Aucun resultat');
        });

        it('affiche le message de chargement si comics non chargés', async () => {
            ({ application } = await startStimulusController(
                SearchController, 'search', buildHtml()
            ));
            const controller = getController();
            controller.comics = null;
            controller.performSearch('test');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('Chargement des donnees');
        });

        it('échappe la query dans le HTML', async () => {
            const controller = await setupWithComics();
            controller.performSearch('<script>xss</script>');

            const results = document.querySelector('[data-search-target="results"]');
            expect(results.innerHTML).toContain('&lt;script&gt;');
            expect(results.innerHTML).not.toContain('<script>xss</script>');
        });
    });
});
