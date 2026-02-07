import { escapeHtml, normalizeString } from '../../../assets/utils/string-utils.js';

describe('normalizeString', () => {
    it('convertit en minuscules', () => {
        expect(normalizeString('ABC')).toBe('abc');
    });

    it('supprime les accents', () => {
        expect(normalizeString('éàüôç')).toBe('eauoc');
    });

    it('combine minuscules et accents', () => {
        expect(normalizeString('Château FÉLIX')).toBe('chateau felix');
    });

    it('retourne une chaîne vide inchangée', () => {
        expect(normalizeString('')).toBe('');
    });

    it('gère les caractères spéciaux sans accent', () => {
        expect(normalizeString('one-shot #1')).toBe('one-shot #1');
    });
});

describe('escapeHtml', () => {
    it('échappe les chevrons', () => {
        expect(escapeHtml('<script>alert("xss")</script>')).toBe(
            '&lt;script&gt;alert("xss")&lt;/script&gt;'
        );
    });

    it('échappe les esperluettes', () => {
        expect(escapeHtml('a & b')).toBe('a &amp; b');
    });

    it('retourne une chaîne vide pour null', () => {
        expect(escapeHtml(null)).toBe('');
    });

    it('retourne une chaîne vide pour undefined', () => {
        expect(escapeHtml(undefined)).toBe('');
    });

    it('retourne une chaîne vide pour une chaîne vide', () => {
        expect(escapeHtml('')).toBe('');
    });

    it('laisse le texte normal inchangé', () => {
        expect(escapeHtml('Naruto Vol. 1')).toBe('Naruto Vol. 1');
    });
});
