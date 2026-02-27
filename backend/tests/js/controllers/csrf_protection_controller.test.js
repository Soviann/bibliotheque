import { generateCsrfHeaders, generateCsrfToken, removeCsrfToken } from '../../../assets/controllers/csrf_protection_controller.js';

/**
 * Le contrôleur CSRF utilise deux regex :
 * - nameCheck : /^[-_a-zA-Z0-9]{4,22}$/ — valide le cookie name (token ID initial)
 * - tokenCheck : /^[-_/+a-zA-Z0-9]{24,}$/ — valide la valeur du token généré (base64)
 *
 * Workflow : la valeur initiale du champ est le "csrf name" (ex: "_csrf_token_12345").
 * Si elle matche nameCheck, elle est déplacée dans data-csrf-protection-cookie-value
 * et remplacée par un token base64 aléatoire qui matche tokenCheck.
 */

/**
 * Crée un formulaire avec un champ CSRF pour les tests.
 * tokenValue doit matcher nameCheck pour déclencher la génération.
 */
function createFormWithCsrf(tokenValue = 'csrf_token_abcdef', cookieAttr = null) {
    const form = document.createElement('form');
    const input = document.createElement('input');
    input.setAttribute('data-controller', 'csrf-protection');
    input.value = tokenValue;
    if (cookieAttr) {
        input.setAttribute('data-csrf-protection-cookie-value', cookieAttr);
    }
    form.appendChild(input);
    document.body.appendChild(form);
    return form;
}

describe('csrf_protection_controller', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        // Nettoyer les cookies
        document.cookie.split(';').forEach((cookie) => {
            const name = cookie.split('=')[0].trim();
            if (name) {
                document.cookie = `${name}=; max-age=0; path=/`;
            }
        });
    });

    describe('generateCsrfToken', () => {
        it('ne fait rien si pas de champ CSRF', () => {
            const form = document.createElement('form');
            document.body.appendChild(form);

            expect(() => generateCsrfToken(form)).not.toThrow();
        });

        it('déplace la valeur initiale dans le cookie attribute', () => {
            // Token initial matchant nameCheck: ^[-_a-zA-Z0-9]{4,22}$
            const form = createFormWithCsrf('my_token_name');

            generateCsrfToken(form);

            const csrfField = form.querySelector('input[data-controller="csrf-protection"]');
            expect(csrfField.getAttribute('data-csrf-protection-cookie-value')).toBe('my_token_name');
        });

        it('remplace le defaultValue du champ par un token base64', () => {
            const form = createFormWithCsrf('my_token_name');

            generateCsrfToken(form);

            const csrfField = form.querySelector('input[data-controller="csrf-protection"]');
            // Le defaultValue (soumis avec le formulaire) est le nouveau token base64
            expect(csrfField.defaultValue).not.toBe('my_token_name');
            expect(csrfField.defaultValue.length).toBeGreaterThanOrEqual(24);
        });

        it('définit un cookie avec le format name_token=name', () => {
            const form = createFormWithCsrf('my_token_name');

            generateCsrfToken(form);

            const csrfField = form.querySelector('input[data-controller="csrf-protection"]');
            // Le cookie utilise le defaultValue (token base64) comme partie du nom
            const expectedPrefix = `my_token_name_${csrfField.defaultValue}=my_token_name`;
            expect(document.cookie).toContain(expectedPrefix);
        });

        it('ne préfixe pas __Host- en HTTP (jsdom)', () => {
            // jsdom utilise http par défaut
            const form = createFormWithCsrf('my_token_name');

            generateCsrfToken(form);

            expect(document.cookie).not.toContain('__Host-');
        });

        it('dispatch un événement change sur le champ', () => {
            const form = createFormWithCsrf('my_token_name');
            const csrfField = form.querySelector('input[data-controller="csrf-protection"]');
            const changeSpy = vi.fn();
            csrfField.addEventListener('change', changeSpy);

            generateCsrfToken(form);

            expect(changeSpy).toHaveBeenCalled();
        });

        it('fonctionne avec input name="_csrf_token"', () => {
            const form = document.createElement('form');
            const input = document.createElement('input');
            input.name = '_csrf_token';
            input.value = 'alt_csrf_name';
            form.appendChild(input);
            document.body.appendChild(form);

            generateCsrfToken(form);

            expect(input.getAttribute('data-csrf-protection-cookie-value')).toBe('alt_csrf_name');
        });

        it('ne re-génère pas si cookie attribute déjà défini', () => {
            const form = createFormWithCsrf('my_token_name');

            // Première génération
            generateCsrfToken(form);
            const csrfField = form.querySelector('input[data-controller="csrf-protection"]');
            const firstToken = csrfField.value;

            // Deuxième appel — le cookie attribute est déjà défini,
            // donc la valeur initiale ne matche plus nameCheck
            generateCsrfToken(form);
            expect(csrfField.value).toBe(firstToken);
        });
    });

    describe('generateCsrfHeaders', () => {
        it('retourne un objet vide sans champ CSRF', () => {
            const form = document.createElement('form');

            expect(generateCsrfHeaders(form)).toEqual({});
        });

        it('retourne les headers quand le token est valide', () => {
            // Simuler un formulaire avec cookie attribute déjà défini et valeur matchant tokenCheck
            const form = document.createElement('form');
            const input = document.createElement('input');
            input.setAttribute('data-controller', 'csrf-protection');
            input.setAttribute('data-csrf-protection-cookie-value', 'my_token_name');
            // Valeur assez longue pour matcher tokenCheck: ^[-_/+a-zA-Z0-9]{24,}$
            input.value = 'abcdefghijklmnopqrstuvwxyz';
            form.appendChild(input);
            document.body.appendChild(form);

            const headers = generateCsrfHeaders(form);

            expect(headers).toHaveProperty('my_token_name');
            expect(headers['my_token_name']).toBe('abcdefghijklmnopqrstuvwxyz');
        });

        it('retourne un objet vide si cookie name invalide', () => {
            const form = document.createElement('form');
            const input = document.createElement('input');
            input.setAttribute('data-controller', 'csrf-protection');
            // Cookie name trop long (> 22 chars) → ne matche pas nameCheck
            input.setAttribute('data-csrf-protection-cookie-value', 'this_name_is_way_too_long_for_name_check');
            input.value = 'abcdefghijklmnopqrstuvwxyz';
            form.appendChild(input);

            const headers = generateCsrfHeaders(form);

            expect(headers).toEqual({});
        });
    });

    describe('removeCsrfToken', () => {
        it('ne fait rien sans champ CSRF', () => {
            const form = document.createElement('form');

            expect(() => removeCsrfToken(form)).not.toThrow();
        });

        it('supprime le cookie CSRF', () => {
            // Simuler un état post-generateCsrfToken
            const form = document.createElement('form');
            const input = document.createElement('input');
            input.setAttribute('data-controller', 'csrf-protection');
            input.setAttribute('data-csrf-protection-cookie-value', 'my_token_name');
            const tokenValue = 'abcdefghijklmnopqrstuvwxyz';
            input.value = tokenValue;
            form.appendChild(input);
            document.body.appendChild(form);

            // Définir le cookie manuellement
            document.cookie = `my_token_name_${tokenValue}=my_token_name; path=/; samesite=strict`;
            expect(document.cookie).toContain(`my_token_name_${tokenValue}`);

            removeCsrfToken(form);

            // Le cookie devrait être supprimé (max-age=0)
            expect(document.cookie).not.toContain(`my_token_name_${tokenValue}=my_token_name`);
        });
    });

    describe('event listeners', () => {
        it('génère un token lors du submit', () => {
            const form = createFormWithCsrf('submit_test_token');
            const csrfField = form.querySelector('input[data-controller="csrf-protection"]');

            form.dispatchEvent(new Event('submit', { bubbles: true }));

            expect(csrfField.getAttribute('data-csrf-protection-cookie-value')).toBe('submit_test_token');
        });
    });
});
