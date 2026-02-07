import { renderCard } from '../../../assets/utils/card-renderer.js';

/** Comic de base pour les tests */
function makeComic(overrides = {}) {
    return {
        coverUrl: null,
        currentIssue: 5,
        currentIssueComplete: false,
        deleteToken: 'del-token',
        hasNasTome: false,
        id: 42,
        isOneShot: false,
        lastBought: 4,
        lastBoughtComplete: false,
        lastDownloaded: null,
        lastDownloadedComplete: false,
        latestPublishedIssue: 10,
        latestPublishedIssueComplete: false,
        missingTomesNumbers: [6, 7],
        ownedTomesNumbers: [1, 2, 3, 4, 5],
        status: 'buying',
        title: 'Naruto',
        toLibraryToken: null,
        type: 'manga',
        ...overrides,
    };
}

describe('renderCard', () => {
    it('génère le HTML avec titre et badges', () => {
        const html = renderCard(makeComic());

        expect(html).toContain('Naruto');
        expect(html).toContain('status-buying');
        expect(html).toContain("En cours d'achat");
        expect(html).toContain('type-badge-manga');
        expect(html).toContain('Manga');
    });

    it('affiche la couverture si coverUrl est fournie', () => {
        const html = renderCard(makeComic({ coverUrl: '/img/naruto.jpg' }));

        expect(html).toContain('<img src="/img/naruto.jpg"');
        expect(html).toContain('Couverture de Naruto');
    });

    it("n'affiche pas de couverture si coverUrl est null", () => {
        const html = renderCard(makeComic({ coverUrl: null }));

        expect(html).not.toContain('comic-card-cover');
    });

    it('échappe le titre contre XSS', () => {
        const html = renderCard(makeComic({ title: '<script>alert("xss")</script>' }));

        expect(html).toContain('&lt;script&gt;');
        expect(html).not.toContain('<script>alert');
    });

    it('affiche "Tome unique" pour les one-shots', () => {
        const html = renderCard(makeComic({ isOneShot: true }));

        expect(html).toContain('Tome unique');
        // Ne devrait pas afficher les détails de tomes
        expect(html).not.toContain('Possede');
    });

    it('affiche le badge NAS si hasNasTome est vrai', () => {
        const html = renderCard(makeComic({ hasNasTome: true }));

        expect(html).toContain('type-badge-nas');
        expect(html).toContain('NAS');
    });

    it("n'affiche pas le badge NAS si hasNasTome est faux", () => {
        const html = renderCard(makeComic({ hasNasTome: false }));

        expect(html).not.toContain('type-badge-nas');
    });

    it('affiche les tomes manquants avec la classe warning', () => {
        const html = renderCard(makeComic({ missingTomesNumbers: [3, 5] }));

        expect(html).toContain('Manquants');
        expect(html).toContain('warning');
        expect(html).toContain('3, 5');
    });

    it('affiche les tomes possédés', () => {
        const html = renderCard(makeComic({ ownedTomesNumbers: [1, 2, 3] }));

        expect(html).toContain('Tomes');
        expect(html).toContain('1, 2, 3');
    });

    it('affiche le bouton Modifier et Supprimer', () => {
        const html = renderCard(makeComic());

        expect(html).toContain('/comic/42/edit');
        expect(html).toContain('Modifier');
        expect(html).toContain('/comic/42/delete');
        expect(html).toContain('Supprimer');
    });

    it("n'affiche pas le bouton Ajouter par défaut", () => {
        const html = renderCard(makeComic());

        expect(html).not.toContain('to-library');
        expect(html).not.toContain('Ajouter');
    });

    it('affiche le bouton Ajouter si showAddButton est vrai', () => {
        const html = renderCard(makeComic({ toLibraryToken: 'lib-token' }), { showAddButton: true });

        expect(html).toContain('/comic/42/to-library');
        expect(html).toContain('Ajouter');
        expect(html).toContain('lib-token');
    });

    it("affiche 'Complet' quand currentIssueComplete est vrai", () => {
        const html = renderCard(makeComic({ currentIssue: 10, currentIssueComplete: true }));

        expect(html).toContain('Complet');
    });

    it('affiche les infos de dernière publication avec terminé', () => {
        const html = renderCard(makeComic({
            latestPublishedIssue: 72,
            latestPublishedIssueComplete: true,
        }));

        expect(html).toContain('Parus');
        expect(html).toContain('72 (termine)');
    });

    it('utilise les labels personnalisés', () => {
        const html = renderCard(makeComic(), {
            statusLabels: { buying: 'Achat en cours' },
            typeLabels: { manga: 'マンガ' },
        });

        expect(html).toContain('Achat en cours');
        expect(html).toContain('マンガ');
    });

    it('utilise statusLabel/typeLabel du comic si fournis', () => {
        const html = renderCard(makeComic({ statusLabel: 'Custom Status', typeLabel: 'Custom Type' }));

        expect(html).toContain('Custom Status');
        expect(html).toContain('Custom Type');
    });

    it('affiche le lien vers la page de détail', () => {
        const html = renderCard(makeComic({ id: 99 }));

        expect(html).toContain('href="/comic/99"');
    });

    it("affiche 'Complet' pour lastBoughtComplete", () => {
        const html = renderCard(makeComic({ lastBought: 5, lastBoughtComplete: true }));

        expect(html).toContain('Dernier achat');
        expect(html).toContain('Complet');
    });

    it("affiche 'Complet' pour lastDownloadedComplete", () => {
        const html = renderCard(makeComic({ lastDownloaded: 3, lastDownloadedComplete: true }));

        expect(html).toContain('Telecharge');
        expect(html).toContain('Complet');
    });
});
