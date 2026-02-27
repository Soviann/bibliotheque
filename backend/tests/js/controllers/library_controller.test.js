import LibraryController from '../../../assets/controllers/library_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

const COMICS_DATA = [
    { id: 1, title: 'Naruto', authors: 'Kishimoto', description: '', status: 'buying', type: 'manga', isWishlist: false, hasNasTome: true, updatedAt: '2025-01-15T10:00:00Z', coverUrl: null, isOneShot: false },
    { id: 2, title: 'Astérix', authors: 'Goscinny', description: 'BD gauloise', status: 'finished', type: 'bd', isWishlist: false, hasNasTome: false, updatedAt: '2025-06-01T12:00:00Z', coverUrl: null, isOneShot: false },
    { id: 3, title: 'Batman', authors: 'DC', description: '', status: 'buying', type: 'comics', isWishlist: false, hasNasTome: false, updatedAt: '2025-03-20T08:00:00Z', coverUrl: null, isOneShot: false },
    { id: 4, title: 'Wish Comic', authors: 'Wish Author', description: '', status: 'wishlist', type: 'manga', isWishlist: true, hasNasTome: false, updatedAt: '2025-02-10T14:00:00Z', coverUrl: null, isOneShot: false },
];

function buildHtml({ isWishlist = false } = {}) {
    return `
        <div data-controller="library"
             data-library-is-wishlist-value="${isWishlist}">
            <input data-library-target="searchInput" type="text">
            <span data-library-target="count"></span>
            <div data-library-target="results"></div>
        </div>
    `;
}

describe('library_controller', () => {
    let application;

    beforeEach(() => {
        global.fetch = vi.fn().mockResolvedValue({
            json: () => Promise.resolve(COMICS_DATA),
            ok: true,
        });
        // Nettoyer l'URL avant chaque test
        window.history.replaceState({}, '', window.location.pathname);
    });

    afterEach(() => {
        if (application) stopStimulusController(application);
    });

    function getController() {
        return application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="library"]'), 'library'
        );
    }

    describe('parseUrlFilters', () => {
        it('parse les filtres depuis l\'URL', async () => {
            // Simuler des paramètres d'URL
            const url = new URL(window.location.href);
            url.searchParams.set('type', 'manga');
            url.searchParams.set('status', 'buying');
            url.searchParams.set('nas', '1');
            url.searchParams.set('sort', 'title_desc');
            url.searchParams.set('q', 'naruto');
            window.history.replaceState({}, '', url.toString());

            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml()
            ));

            const controller = getController();
            expect(controller.filters.type).toBe('manga');
            expect(controller.filters.status).toBe('buying');
            expect(controller.filters.nas).toBe('1');
            expect(controller.filters.sort).toBe('title_desc');
            expect(controller.filters.search).toBe('naruto');

            // Nettoyer l'URL
            window.history.replaceState({}, '', window.location.pathname);
        });

        it('utilise les valeurs par défaut si aucun paramètre', async () => {
            window.history.replaceState({}, '', window.location.pathname);

            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml()
            ));

            const controller = getController();
            expect(controller.filters.type).toBeNull();
            expect(controller.filters.status).toBeNull();
            expect(controller.filters.nas).toBeNull();
            expect(controller.filters.sort).toBe('title_asc');
            expect(controller.filters.search).toBe('');
        });
    });

    describe('loadComics', () => {
        it('charge depuis le cache puis l\'API', async () => {
            localStorage.setItem('bibliotheque_comics_cache', JSON.stringify(COMICS_DATA));

            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml()
            ));

            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith('/api/comics');
            });
        });

        it('affiche le message offline sans cache', async () => {
            global.fetch = vi.fn().mockRejectedValue(new Error('offline'));
            const consoleSpy = vi.spyOn(console, 'log').mockImplementation(() => {});

            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml()
            ));

            await vi.waitFor(() => {
                const results = document.querySelector('[data-library-target="results"]');
                expect(results.innerHTML).toContain('Aucune donnee en cache');
            });
        });
    });

    describe('renderResults - filtrage', () => {
        async function setupWithComics(options = {}) {
            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml(options)
            ));

            const controller = getController();
            controller.comics = COMICS_DATA;
            return controller;
        }

        it('exclut les comics wishlist en mode library', async () => {
            const controller = await setupWithComics({ isWishlist: false });
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('Naruto');
            expect(results.innerHTML).not.toContain('Wish Comic');
        });

        it('n\'affiche que les wishlist en mode wishlist', async () => {
            const controller = await setupWithComics({ isWishlist: true });
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('Wish Comic');
            expect(results.innerHTML).not.toContain('Naruto');
        });

        it('filtre par type', async () => {
            const controller = await setupWithComics();
            controller.filters.type = 'manga';
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('Naruto');
            expect(results.innerHTML).not.toContain('Astérix');
            expect(results.innerHTML).not.toContain('Batman');
        });

        it('filtre par statut', async () => {
            const controller = await setupWithComics();
            controller.filters.status = 'finished';
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('Astérix');
            expect(results.innerHTML).not.toContain('Naruto');
        });

        it('filtre par NAS (oui)', async () => {
            const controller = await setupWithComics();
            controller.filters.nas = '1';
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('Naruto');
            expect(results.innerHTML).not.toContain('Astérix');
        });

        it('filtre par NAS (non)', async () => {
            const controller = await setupWithComics();
            controller.filters.nas = '0';
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).not.toContain('Naruto');
            expect(results.innerHTML).toContain('Astérix');
        });

        it('filtre par recherche textuelle', async () => {
            const controller = await setupWithComics();
            controller.filters.search = 'gauloise';
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('Astérix');
            expect(results.innerHTML).not.toContain('Naruto');
        });

        it('ignore la recherche trop courte (< 2 caractères)', async () => {
            const controller = await setupWithComics();
            controller.filters.search = 'a';
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            // Tous les comics non-wishlist doivent être affichés
            expect(results.innerHTML).toContain('Naruto');
            expect(results.innerHTML).toContain('Astérix');
        });

        it('met à jour le compteur', async () => {
            const controller = await setupWithComics();
            controller.renderResults();

            const count = document.querySelector('[data-library-target="count"]');
            expect(count.textContent).toBe('3 serie(s)');
        });

        it('affiche un message si aucun résultat', async () => {
            const controller = await setupWithComics();
            controller.filters.type = 'livre';
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('Aucune serie trouvee');
        });

        it('affiche le bouton Ajouter en mode wishlist', async () => {
            const controller = await setupWithComics({ isWishlist: true });
            controller.renderResults();

            const results = document.querySelector('[data-library-target="results"]');
            expect(results.innerHTML).toContain('to-library');
        });
    });

    describe('sortComics', () => {
        async function setupWithComics() {
            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml()
            ));
            return getController();
        }

        it('trie par titre ascendant (par défaut)', async () => {
            const controller = await setupWithComics();
            const sorted = controller.sortComics([...COMICS_DATA]);

            expect(sorted[0].title).toBe('Astérix');
            expect(sorted[1].title).toBe('Batman');
            expect(sorted[2].title).toBe('Naruto');
        });

        it('trie par titre descendant', async () => {
            const controller = await setupWithComics();
            controller.filters.sort = 'title_desc';
            const sorted = controller.sortComics([...COMICS_DATA]);

            expect(sorted[0].title).toBe('Wish Comic');
            expect(sorted[sorted.length - 1].title).toBe('Astérix');
        });

        it('trie par date de mise à jour descendante', async () => {
            const controller = await setupWithComics();
            controller.filters.sort = 'updated_desc';
            const sorted = controller.sortComics([...COMICS_DATA]);

            expect(sorted[0].title).toBe('Astérix'); // 2025-06-01
            expect(sorted[1].title).toBe('Batman');   // 2025-03-20
        });

        it('trie par date de mise à jour ascendante', async () => {
            const controller = await setupWithComics();
            controller.filters.sort = 'updated_asc';
            const sorted = controller.sortComics([...COMICS_DATA]);

            expect(sorted[0].title).toBe('Naruto');   // 2025-01-15
            expect(sorted[sorted.length - 1].title).toBe('Astérix'); // 2025-06-01
        });

        it('trie par statut puis titre', async () => {
            const controller = await setupWithComics();
            controller.filters.sort = 'status';
            const sorted = controller.sortComics([...COMICS_DATA]);

            // buying < finished < wishlist (alphabétique)
            expect(sorted[0].status).toBe('buying');
            expect(sorted[1].status).toBe('buying');
            // Même statut → tri par titre
            expect(sorted[0].title).toBe('Batman');
            expect(sorted[1].title).toBe('Naruto');
        });
    });

    describe('updateFilter + updateUrlAndRender', () => {
        it('met à jour l\'URL quand un filtre change', async () => {
            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml()
            ));

            const controller = getController();
            controller.comics = COMICS_DATA;
            controller.updateFilter('type', 'manga');

            expect(window.location.search).toContain('type=manga');
        });

        it('supprime le paramètre URL quand la valeur est null', async () => {
            ({ application } = await startStimulusController(
                LibraryController, 'library', buildHtml()
            ));

            const controller = getController();
            controller.comics = COMICS_DATA;
            controller.updateFilter('type', null);

            expect(window.location.search).not.toContain('type=');
        });
    });
});
