import ComicFormController from '../../../assets/controllers/comic_form_controller.js';
import { startStimulusController, stopStimulusController } from '../helpers/stimulus-helper.js';

const TOME_PROTOTYPE = `
    <div data-tomes-collection-target="entry" class="tome-entry">
        <input class="tome-number-input" name="tomes[__name__][number]" value="">
        <input class="tome-isbn-input" name="tomes[__name__][isbn]" value="">
        <button class="tome-remove">Supprimer</button>
    </div>
`;

function buildHtml({ isOneShot = false, existingTome = false } = {}) {
    const tomeEntry = existingTome ? `
        <div data-tomes-collection-target="entry" class="tome-entry">
            <input class="tome-number-input" name="tomes[0][number]" value="1">
            <input class="tome-isbn-input" name="tomes[0][isbn]" value="">
            <button class="tome-remove">Supprimer</button>
        </div>
    ` : '';

    return `
        <form data-controller="comic-form">
            <select data-comic-form-target="type">
                <option value="">--</option>
                <option value="bd">BD</option>
                <option value="comics">Comics</option>
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
            <input data-comic-form-target="isOneShot" type="checkbox" ${isOneShot ? 'checked' : ''}>
            <div data-comic-form-target="publishedIssueRow">
                <input data-comic-form-target="latestPublishedIssue" type="number" value="">
                <input data-comic-form-target="latestPublishedIssueComplete" type="checkbox">
            </div>
            <div data-comic-form-target="oneShotIsbnRow" style="display: none;">
                <input data-comic-form-target="oneShotIsbn" type="text" value="">
                <button data-comic-form-target="lookupOneShotIsbnButton">Rechercher</button>
            </div>
            <div data-comic-form-target="tomesSection">
                <div data-comic-form-target="tomesList">${tomeEntry}</div>
                <template data-comic-form-target="tomesPrototype">${TOME_PROTOTYPE}</template>
                <button data-comic-form-target="addTomeButton">Ajouter</button>
            </div>
            <button data-comic-form-target="lookupButton">Rechercher ISBN</button>
            <button data-comic-form-target="lookupTitleButton">Rechercher Titre</button>
            <div data-comic-form-target="lookupStatus" class="lookup-status"></div>
        </form>
    `;
}

describe('comic_form_controller', () => {
    let application;

    afterEach(() => {
        if (application) stopStimulusController(application);
    });

    function getController() {
        return application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="comic-form"]'), 'comic-form'
        );
    }

    async function setup(options = {}) {
        ({ application } = await startStimulusController(
            ComicFormController, 'comic-form', buildHtml(options)
        ));
        return getController();
    }

    describe('applyOneShotState', () => {
        it('masque la ligne "Dernier tome paru" quand one-shot', async () => {
            const controller = await setup();
            controller.applyOneShotState(true);

            const publishedRow = document.querySelector('[data-comic-form-target="publishedIssueRow"]');
            expect(publishedRow.style.display).toBe('none');
        });

        it('affiche le champ ISBN one-shot', async () => {
            const controller = await setup();
            controller.applyOneShotState(true);

            const isbnRow = document.querySelector('[data-comic-form-target="oneShotIsbnRow"]');
            expect(isbnRow.style.display).toBe('');
        });

        it('masque la section tomes quand one-shot', async () => {
            const controller = await setup();
            controller.applyOneShotState(true);

            const tomesSection = document.querySelector('[data-comic-form-target="tomesSection"]');
            expect(tomesSection.style.display).toBe('none');
        });

        it('pré-remplit latestPublishedIssue à 1', async () => {
            const controller = await setup();
            controller.applyOneShotState(true);

            const input = document.querySelector('[data-comic-form-target="latestPublishedIssue"]');
            expect(input.value).toBe('1');
        });

        it('coche latestPublishedIssueComplete', async () => {
            const controller = await setup();
            controller.applyOneShotState(true);

            const checkbox = document.querySelector('[data-comic-form-target="latestPublishedIssueComplete"]');
            expect(checkbox.checked).toBe(true);
        });

        it('restaure l\'affichage quand désactivé', async () => {
            const controller = await setup();
            controller.applyOneShotState(true);
            controller.applyOneShotState(false);

            expect(document.querySelector('[data-comic-form-target="publishedIssueRow"]').style.display).toBe('');
            expect(document.querySelector('[data-comic-form-target="oneShotIsbnRow"]').style.display).toBe('none');
            expect(document.querySelector('[data-comic-form-target="tomesSection"]').style.display).toBe('');
        });

        it('crée un tome avec numéro 1 si collection vide', async () => {
            const controller = await setup();
            controller.applyOneShotState(true);

            const numberInput = document.querySelector('.tome-number-input');
            expect(numberInput).not.toBeNull();
            expect(numberInput.value).toBe('1');
        });
    });

    describe('fillField', () => {
        it('remplit un champ vide', async () => {
            const controller = await setup();
            const result = controller.fillField('title', 'Naruto');

            expect(result).toBe(true);
            expect(document.querySelector('[data-comic-form-target="title"]').value).toBe('Naruto');
        });

        it('ne remplace pas un champ déjà rempli', async () => {
            await setup();
            document.querySelector('[data-comic-form-target="title"]').value = 'Existing';

            const controller = getController();
            const result = controller.fillField('title', 'Naruto');

            expect(result).toBe(false);
            expect(document.querySelector('[data-comic-form-target="title"]').value).toBe('Existing');
        });

        it('retourne false si pas de valeur', async () => {
            const controller = await setup();
            expect(controller.fillField('title', null)).toBe(false);
            expect(controller.fillField('title', '')).toBe(false);
        });

        it('ajoute la classe de mise en surbrillance', async () => {
            const controller = await setup();

            // Activer les fake timers APRÈS la connexion Stimulus
            vi.useFakeTimers();

            controller.fillField('title', 'Naruto');

            const input = document.querySelector('[data-comic-form-target="title"]');
            expect(input.classList.contains('field-filled-by-api')).toBe(true);

            vi.advanceTimersByTime(3000);
            expect(input.classList.contains('field-filled-by-api')).toBe(false);

            vi.useRealTimers();
        });
    });

    describe('fillSelect', () => {
        it('remplit un select avec une valeur valide', async () => {
            const controller = await setup();
            const result = controller.fillSelect('type', 'manga');

            expect(result).toBe(true);
            expect(document.querySelector('[data-comic-form-target="type"]').value).toBe('manga');
        });

        it('retourne false si la valeur n\'existe pas', async () => {
            const controller = await setup();
            const result = controller.fillSelect('type', 'unknown');

            expect(result).toBe(false);
        });

        it('retourne false si déjà la bonne valeur', async () => {
            await setup();
            document.querySelector('[data-comic-form-target="type"]').value = 'manga';

            const controller = getController();
            const result = controller.fillSelect('type', 'manga');

            expect(result).toBe(false);
        });
    });

    describe('fillTomeIsbn', () => {
        it('remplit l\'ISBN du premier tome', async () => {
            const controller = await setup({ existingTome: true });
            const result = controller.fillTomeIsbn('978-2-1234-5678-9');

            expect(result).toBe(true);
            expect(document.querySelector('.tome-isbn-input').value).toBe('978-2-1234-5678-9');
        });

        it('ne remplace pas un ISBN existant', async () => {
            await setup({ existingTome: true });
            document.querySelector('.tome-isbn-input').value = 'existing-isbn';

            const controller = getController();
            const result = controller.fillTomeIsbn('978-2-1234-5678-9');

            expect(result).toBe(false);
        });

        it('retourne false sans ISBN', async () => {
            const controller = await setup({ existingTome: true });
            expect(controller.fillTomeIsbn(null)).toBe(false);
            expect(controller.fillTomeIsbn('')).toBe(false);
        });

        it('synchronise avec le champ ISBN one-shot', async () => {
            const controller = await setup({ isOneShot: true, existingTome: true });
            controller.fillTomeIsbn('978-2-1234-5678-9');

            const oneShotIsbn = document.querySelector('[data-comic-form-target="oneShotIsbn"]');
            expect(oneShotIsbn.value).toBe('978-2-1234-5678-9');
        });
    });

    describe('performIsbnLookup', () => {
        it('remplit les champs avec les données de l\'API', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    authors: 'Kishimoto',
                    description: 'Ninja manga',
                    isbn: null,
                    isOneShot: false,
                    publishedDate: '1999',
                    publisher: 'Kana',
                    sources: ['google_books'],
                    thumbnail: 'http://img.jpg',
                    title: 'Naruto',
                }),
                ok: true,
            });

            const controller = await setup();
            const button = document.createElement('button');
            await controller.performIsbnLookup('978-123', button);

            expect(document.querySelector('[data-comic-form-target="title"]').value).toBe('Naruto');
            expect(document.querySelector('[data-comic-form-target="publisher"]').value).toBe('Kana');
            expect(document.querySelector('[data-comic-form-target="description"]').value).toBe('Ninja manga');
            expect(document.querySelector('[data-comic-form-target="coverUrl"]').value).toBe('http://img.jpg');
        });

        it('affiche une erreur si l\'API retourne une erreur', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ error: 'ISBN introuvable' }),
                ok: false,
            });

            const controller = await setup();
            await controller.performIsbnLookup('000-000', null);

            const flash = document.querySelector('.api-lookup-flash');
            expect(flash).not.toBeNull();
            expect(flash.textContent).toContain('ISBN introuvable');
        });

        it('affiche une erreur de connexion', async () => {
            global.fetch = vi.fn().mockRejectedValue(new Error('network'));

            const controller = await setup();
            await controller.performIsbnLookup('978-123', null);

            const flash = document.querySelector('.api-lookup-flash');
            expect(flash).not.toBeNull();
            expect(flash.textContent).toContain('Erreur de connexion');
        });

        it('désactive et réactive le bouton', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ apiMessages: {}, sources: [], title: 'X' }),
                ok: true,
            });

            const controller = await setup();
            const button = document.createElement('button');
            button.disabled = false;

            const lookupPromise = controller.performIsbnLookup('978-123', button);
            expect(button.disabled).toBe(true);

            await lookupPromise;
            expect(button.disabled).toBe(false);
        });

        it('coche one-shot si détecté par l\'API', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    isOneShot: true,
                    sources: ['google_books'],
                    title: 'Solo',
                }),
                ok: true,
            });

            const controller = await setup();
            await controller.performIsbnLookup('978-123', null);

            const checkbox = document.querySelector('[data-comic-form-target="isOneShot"]');
            expect(checkbox.checked).toBe(true);
        });

        it('ne remplit PAS title/publishedDate/description depuis un tome', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    authors: 'Kishimoto',
                    description: 'Résumé du tome 1',
                    isOneShot: false,
                    publishedDate: '2024',
                    publisher: 'Kana',
                    sources: ['google_books'],
                    thumbnail: 'http://img.jpg',
                    title: 'Naruto Vol. 1',
                }),
                ok: true,
            });

            const controller = await setup();
            const button = document.createElement('button');
            await controller.performIsbnLookup('978-123', button, { fromTome: true });

            // Champs série : remplis
            expect(document.querySelector('[data-comic-form-target="publisher"]').value).toBe('Kana');
            expect(document.querySelector('[data-comic-form-target="coverUrl"]').value).toBe('http://img.jpg');

            // Champs volume-spécifiques : NON remplis
            expect(document.querySelector('[data-comic-form-target="title"]').value).toBe('');
            expect(document.querySelector('[data-comic-form-target="publishedDate"]').value).toBe('');
            expect(document.querySelector('[data-comic-form-target="description"]').value).toBe('');
        });

        it('remplit title/publishedDate/description en mode normal', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    authors: 'Kishimoto',
                    description: 'Ninja manga',
                    isOneShot: false,
                    publishedDate: '1999',
                    publisher: 'Kana',
                    sources: ['google_books'],
                    thumbnail: 'http://img.jpg',
                    title: 'Naruto',
                }),
                ok: true,
            });

            const controller = await setup();
            const button = document.createElement('button');
            await controller.performIsbnLookup('978-123', button);

            expect(document.querySelector('[data-comic-form-target="title"]').value).toBe('Naruto');
            expect(document.querySelector('[data-comic-form-target="publishedDate"]').value).toBe('1999');
            expect(document.querySelector('[data-comic-form-target="description"]').value).toBe('Ninja manga');
            expect(document.querySelector('[data-comic-form-target="publisher"]').value).toBe('Kana');
            expect(document.querySelector('[data-comic-form-target="coverUrl"]').value).toBe('http://img.jpg');
        });

        it('ne coche PAS isOneShot depuis un tome', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    isOneShot: true,
                    sources: ['google_books'],
                    title: 'Solo Vol. 1',
                }),
                ok: true,
            });

            const controller = await setup();
            await controller.performIsbnLookup('978-123', null, { fromTome: true });

            const checkbox = document.querySelector('[data-comic-form-target="isOneShot"]');
            expect(checkbox.checked).toBe(false);
        });

        it('inclut le type dans l\'URL de l\'API', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({ apiMessages: {}, sources: [] }),
                ok: true,
            });

            const controller = await setup();
            document.querySelector('[data-comic-form-target="type"]').value = 'manga';
            await controller.performIsbnLookup('978-123', null);

            expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('type=manga'));
        });
    });

    describe('lookupByTitle', () => {
        it('envoie le titre à l\'API', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    authors: 'Oda',
                    sources: ['google_books'],
                }),
                ok: true,
            });

            const controller = await setup();
            document.querySelector('[data-comic-form-target="title"]').value = 'One Piece';
            await controller.lookupByTitle();

            expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('title=One%20Piece'));
        });

        it('affiche une erreur si titre vide', async () => {
            const controller = await setup();
            await controller.lookupByTitle();

            const flash = document.querySelector('.api-lookup-flash');
            expect(flash).not.toBeNull();
            expect(flash.textContent).toContain('Veuillez saisir un titre');
        });

        it('pré-remplit l\'ISBN du tome si one-shot détecté', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    isbn: '978-2-1234',
                    isOneShot: true,
                    sources: ['google_books'],
                    title: 'Solo',
                }),
                ok: true,
            });

            const controller = await setup();
            document.querySelector('[data-comic-form-target="title"]').value = 'Solo';
            await controller.lookupByTitle();

            const isbnInput = document.querySelector('.tome-isbn-input');
            expect(isbnInput).not.toBeNull();
            expect(isbnInput.value).toBe('978-2-1234');
        });
    });

    describe('buildApiStatusHtml', () => {
        it('retourne une chaîne vide si pas de messages', async () => {
            const controller = await setup();
            expect(controller.buildApiStatusHtml(null)).toBe('');
            expect(controller.buildApiStatusHtml({})).toBe('');
        });

        it('génère des badges HTML pour chaque API', async () => {
            const controller = await setup();
            const html = controller.buildApiStatusHtml({
                anilist: { message: 'Not found', status: 'not_found' },
                google_books: { message: 'OK', status: 'success' },
            });

            expect(html).toContain('api-status-badge--success');
            expect(html).toContain('Google Books');
            expect(html).toContain('api-status-badge--not_found');
            expect(html).toContain('AniList');
        });

        it('utilise les labels lisibles des APIs', async () => {
            const controller = await setup();
            const html = controller.buildApiStatusHtml({
                open_library: { message: 'OK', status: 'success' },
            });

            expect(html).toContain('Open Library');
        });
    });

    describe('showFlashMessage', () => {
        it('crée un élément flash dans le DOM', async () => {
            const controller = await setup();
            controller.showFlashMessage('Test message', 'error');

            const flash = document.querySelector('.api-lookup-flash');
            expect(flash).not.toBeNull();
            expect(flash.classList.contains('api-lookup-flash--error')).toBe(true);
            expect(flash.textContent).toContain('Test message');
        });

        it('supprime le flash précédent', async () => {
            const controller = await setup();
            controller.showFlashMessage('First', 'info');
            controller.showFlashMessage('Second', 'error');

            const flashes = document.querySelectorAll('.api-lookup-flash');
            expect(flashes).toHaveLength(1);
            expect(flashes[0].textContent).toContain('Second');
        });

        it('auto-dismiss après 5 secondes', async () => {
            const controller = await setup();

            vi.useFakeTimers();

            controller.showFlashMessage('Auto dismiss', 'info');
            expect(document.querySelector('.api-lookup-flash')).not.toBeNull();

            vi.advanceTimersByTime(5000);
            expect(document.querySelector('.api-lookup-flash--hiding')).not.toBeNull();

            vi.advanceTimersByTime(300);
            expect(document.querySelector('.api-lookup-flash')).toBeNull();

            vi.useRealTimers();
        });

        it('peut être fermé manuellement via le bouton', async () => {
            const controller = await setup();

            vi.useFakeTimers();

            controller.showFlashMessage('Closeable', 'info');
            document.querySelector('.api-lookup-flash__close').click();

            vi.advanceTimersByTime(300);
            expect(document.querySelector('.api-lookup-flash')).toBeNull();

            vi.useRealTimers();
        });
    });

    describe('getSelectedType', () => {
        it('retourne la valeur du select', async () => {
            const controller = await setup();
            document.querySelector('[data-comic-form-target="type"]').value = 'manga';

            expect(controller.getSelectedType()).toBe('manga');
        });

        it('retourne null si aucun type sélectionné', async () => {
            const controller = await setup();
            expect(controller.getSelectedType()).toBeNull();
        });
    });

    describe('handleBarcodeScan', () => {
        it('remplit le champ ISBN one-shot et déclenche le lookup', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    sources: ['google_books'],
                    title: 'Naruto',
                }),
                ok: true,
            });

            const controller = await setup({ isOneShot: true, existingTome: true });

            // Simule un événement de scan avec contexte one-shot
            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });
            event.params = { context: 'oneshot' };
            controller.handleBarcodeScan(event);

            const oneShotIsbn = document.querySelector('[data-comic-form-target="oneShotIsbn"]');
            expect(oneShotIsbn.value).toBe('9782723456789');

            // Vérifie que le lookup a été appelé
            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    expect.stringContaining('isbn=9782723456789')
                );
            });
        });

        it('remplit le champ ISBN d\'un tome et déclenche le lookup', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    publisher: 'Kana',
                    sources: ['google_books'],
                }),
                ok: true,
            });

            const controller = await setup({ existingTome: true });

            const event = new CustomEvent('barcode-scanner:detected', {
                detail: { rawValue: '9782723456789', format: 'ean_13' },
            });
            event.params = { context: 'tome-0' };
            controller.handleBarcodeScan(event);

            const tomeIsbn = document.querySelector('.tome-isbn-input');
            expect(tomeIsbn.value).toBe('9782723456789');

            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    expect.stringContaining('isbn=9782723456789')
                );
            });
        });
    });

    describe('scan_isbn URL param', () => {
        it('déclenche le lookup automatique si scan_isbn est dans l\'URL', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    sources: ['google_books'],
                    title: 'One Piece',
                }),
                ok: true,
            });

            // Simule le paramètre URL
            const url = new URL(window.location.href);
            url.searchParams.set('scan_isbn', '9782723456789');
            window.history.replaceState({}, '', url.toString());

            await setup();

            // Vérifie que le lookup a été déclenché
            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    expect.stringContaining('isbn=9782723456789')
                );
            });

            // Nettoie l'URL
            window.history.replaceState({}, '', window.location.pathname);
        });

        it('pré-sélectionne le type si présent dans l\'URL', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    sources: ['google_books'],
                    title: 'Naruto',
                }),
                ok: true,
            });

            const url = new URL(window.location.href);
            url.searchParams.set('scan_isbn', '9782723456789');
            url.searchParams.set('type', 'manga');
            window.history.replaceState({}, '', url.toString());

            await setup();

            const typeSelect = document.querySelector('[data-comic-form-target="type"]');
            expect(typeSelect.value).toBe('manga');

            // Nettoie l'URL
            window.history.replaceState({}, '', window.location.pathname);
        });

        it('inclut le type dans l\'appel API du lookup automatique', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    sources: ['google_books'],
                    title: 'Naruto',
                }),
                ok: true,
            });

            const url = new URL(window.location.href);
            url.searchParams.set('scan_isbn', '9782723456789');
            url.searchParams.set('type', 'manga');
            window.history.replaceState({}, '', url.toString());

            await setup();

            await vi.waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    expect.stringContaining('type=manga')
                );
            });

            // Nettoie l'URL
            window.history.replaceState({}, '', window.location.pathname);
        });

        it('nettoie aussi le paramètre type de l\'URL', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    sources: [],
                }),
                ok: true,
            });

            const url = new URL(window.location.href);
            url.searchParams.set('scan_isbn', '9782723456789');
            url.searchParams.set('type', 'manga');
            window.history.replaceState({}, '', url.toString());

            await setup();

            expect(window.location.search).toBe('');

            // Nettoie l'URL
            window.history.replaceState({}, '', window.location.pathname);
        });

        it('remplit le champ ISBN avant le lookup', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                json: () => Promise.resolve({
                    apiMessages: {},
                    sources: [],
                }),
                ok: true,
            });

            const url = new URL(window.location.href);
            url.searchParams.set('scan_isbn', '9782723456789');
            window.history.replaceState({}, '', url.toString());

            await setup();

            const isbnInput = document.querySelector('[data-comic-form-target="isbn"]');
            expect(isbnInput.value).toBe('9782723456789');

            // Nettoie l'URL
            window.history.replaceState({}, '', window.location.pathname);
        });
    });
});
