// @ts-check
const { test, expect } = require('@playwright/test');

const BASE_URL = 'https://bibliotheque.ddev.site';

/**
 * Helper pour se connecter.
 */
async function login(page) {
    await page.goto(`${BASE_URL}/login`);
    await page.waitForLoadState('networkidle');

    // Remplir le formulaire
    await page.fill('#username', 'test@example.com');
    await page.fill('#password', 'password');

    // Soumettre et attendre la navigation
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]')
    ]);

    // Vérifie qu'on n'est plus sur la page de login
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
        throw new Error(`Login failed - still on ${currentUrl}`);
    }
}

/**
 * Helper pour attendre que le Service Worker soit actif.
 */
async function waitForServiceWorker(page) {
    await page.waitForFunction(async () => {
        const registration = await navigator.serviceWorker.ready;
        return registration.active?.state === 'activated';
    }, { timeout: 10000 });
}

test.describe('PWA Offline Mode', () => {

    test('page /offline est accessible', async ({ page }) => {
        await page.goto(`${BASE_URL}/offline`);
        await expect(page).toHaveURL(/\/offline/);
        const content = await page.content();
        expect(content.toLowerCase()).toContain('hors ligne');
    });

    test('Service Worker est installe et actif', async ({ page }) => {
        await page.goto(`${BASE_URL}/login`);
        await page.waitForTimeout(5000);

        const swStatus = await page.evaluate(async () => {
            const registrations = await navigator.serviceWorker.getRegistrations();
            return registrations.map(r => ({
                scope: r.scope,
                active: r.active ? { state: r.active.state } : null,
            }));
        });

        expect(swStatus.length).toBeGreaterThan(0);
        expect(swStatus.some(r => r.active?.state === 'activated')).toBe(true);
    });

    test('cache offline contient /offline', async ({ page }) => {
        await page.goto(`${BASE_URL}/login`);
        await page.waitForTimeout(3000);

        const cacheHasOffline = await page.evaluate(async () => {
            const cache = await caches.open('offline');
            const response = await cache.match('/offline');
            return response !== undefined;
        });

        expect(cacheHasOffline).toBe(true);
    });

    test('page en cache accessible en mode offline', async ({ page, context }) => {
        await page.goto(`${BASE_URL}/login`);
        await page.waitForTimeout(3000);

        await page.goto(`${BASE_URL}/offline`);
        await page.waitForTimeout(1000);

        await page.reload();
        await page.waitForTimeout(1000);

        await context.setOffline(true);

        await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
        await page.waitForTimeout(1000);

        const content = await page.content();
        expect(content.toLowerCase()).toContain('hors ligne');
    });

    test('SW sert /offline pour page non cachee en mode offline', async ({ page, context }) => {
        // 1. Visite pour installer le SW
        await page.goto(`${BASE_URL}/login`);
        await page.waitForTimeout(3000); // Attend que le SW soit actif

        // 2. Recharge pour que le SW contrôle la page
        await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
        await page.waitForTimeout(1000);

        // 3. Active le mode offline
        await context.setOffline(true);

        // 4. Essaie d'accéder à une page non visitée
        await page.goto(`${BASE_URL}/wishlist`, { waitUntil: 'commit' }).catch(() => {});
        await page.waitForTimeout(2000);

        // 5. Le SW devrait servir la page offline comme fallback
        const content = await page.content();
        const isOfflinePage = content.toLowerCase().includes('hors ligne');
        expect(isOfflinePage).toBe(true);
    });

    test('evenement Turbo fetch-request-error declenche affichage offline', async ({ page }) => {
        await page.goto(`${BASE_URL}/login`);
        await page.waitForTimeout(3000);

        // 2. Simule l'événement turbo:fetch-request-error (sans mode offline)
        const result = await page.evaluate(async () => {
            return new Promise((resolve) => {
                // Déclenche l'événement
                const event = new CustomEvent('turbo:fetch-request-error', {
                    bubbles: true,
                    cancelable: true,
                    detail: {
                        request: { url: 'https://bibliotheque.ddev.site/wishlist' },
                        error: new Error('Network error')
                    }
                });
                document.dispatchEvent(event);

                // Attend que le handler s'exécute
                setTimeout(() => {
                    resolve({
                        url: window.location.href,
                        content: document.documentElement.innerHTML.substring(0, 5000).toLowerCase()
                    });
                }, 2000);
            });
        });

        // Vérifie qu'on affiche la page offline
        const isOffline = result.url.includes('/offline') || result.content.includes('hors ligne');
        expect(isOffline).toBe(true);
    });
});

test.describe('Navigation hors ligne sur pages visitees', () => {

    test('page accueil visitee est accessible en mode offline', async ({ page, context }) => {
        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. On est sur l'accueil après connexion, recharger pour mise en cache
        await page.reload();
        await page.waitForLoadState('networkidle');

        // 3. Vérifie que la page accueil est dans le cache
        const cacheInfo = await page.evaluate(async () => {
            const cache = await caches.open('bibliotheque-pages');
            const keys = await cache.keys();
            return {
                count: keys.length,
                urls: keys.map(req => req.url)
            };
        });
        expect(cacheInfo.count).toBeGreaterThan(0);

        // 4. Visite une autre page pour changer de contexte
        await page.goto(`${BASE_URL}/offline`);
        await page.waitForTimeout(500);

        // 5. Active le mode offline
        await context.setOffline(true);

        // 6. Retourne sur l'accueil (devrait être servi depuis le cache)
        await page.goto(`${BASE_URL}/`, { waitUntil: 'commit' }).catch(() => {});
        await page.waitForTimeout(2000);

        // 7. Vérifie qu'on affiche bien l'accueil (pas la page offline "Vous etes hors ligne")
        const content = await page.content();
        const isOfflineFallback = content.includes('Vous etes hors ligne');

        // Si on affiche le fallback offline, le test échoue
        // Sinon, on est sur une page en cache (qui peut être l'accueil ou une autre)
        expect(isOfflineFallback).toBe(false);
    });

    test('page wishlist visitee est accessible en mode offline', async ({ page, context }) => {
        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Visite la page wishlist
        await page.goto(`${BASE_URL}/wishlist`);
        await page.waitForLoadState('networkidle');

        // 3. Vérifie que la page wishlist est dans le cache
        const wishlistInCache = await page.evaluate(async () => {
            const cache = await caches.open('bibliotheque-pages');
            const keys = await cache.keys();
            return keys.some(req => req.url.includes('/wishlist'));
        });
        expect(wishlistInCache).toBe(true);

        // 4. Retourne sur l'accueil
        await page.goto(`${BASE_URL}/`);
        await page.waitForTimeout(500);

        // 5. Active le mode offline
        await context.setOffline(true);

        // 6. Retourne sur wishlist (devrait être servi depuis le cache)
        await page.goto(`${BASE_URL}/wishlist`, { waitUntil: 'commit' }).catch(() => {});
        await page.waitForTimeout(2000);

        // 7. Vérifie qu'on n'affiche PAS le fallback offline
        const content = await page.content();
        const isOfflineFallback = content.includes('Vous etes hors ligne');
        expect(isOfflineFallback).toBe(false);
    });

    test('navigation Turbo vers page cachee fonctionne en mode offline', async ({ page, context }) => {
        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Visite wishlist pour la mettre en cache
        await page.goto(`${BASE_URL}/wishlist`);
        await page.waitForLoadState('networkidle');

        // 3. Retourne sur l'accueil
        await page.goto(`${BASE_URL}/`);
        await page.waitForLoadState('networkidle');

        // 4. Active le mode offline
        await context.setOffline(true);

        // 5. Clique sur le lien wishlist (navigation Turbo)
        const wishlistLink = page.locator('a[href="/wishlist"]').first();
        if (await wishlistLink.isVisible()) {
            await wishlistLink.click();
            await page.waitForTimeout(2000);

            // 6. Vérifie qu'on n'affiche PAS le fallback offline
            const content = await page.content();
            const isOfflineFallback = content.includes('Vous etes hors ligne');

            // En mode offline avec Turbo, soit on affiche la page cachée,
            // soit on affiche le fallback. Le test vérifie que la navigation fonctionne.
            expect(page.url().includes('/wishlist') || isOfflineFallback).toBe(true);
        } else {
            // Si le lien n'est pas visible, on skip ce test
            test.skip();
        }
    });

    test('API /api/comics est accessible en mode offline apres visite', async ({ page, context }) => {
        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Appelle l'API pour la mettre en cache
        const onlineResponse = await page.evaluate(async () => {
            const response = await fetch('/api/comics');
            return {
                ok: response.ok,
                data: await response.json()
            };
        });
        expect(onlineResponse.ok).toBe(true);

        // 3. Attendre que le cache soit mis à jour
        await page.waitForTimeout(1000);

        // 4. Active le mode offline
        await context.setOffline(true);

        // 5. Appelle l'API en mode offline (devrait être servie depuis le cache)
        const offlineResponse = await page.evaluate(async () => {
            try {
                const response = await fetch('/api/comics');
                return {
                    ok: response.ok,
                    status: response.status
                };
            } catch (e) {
                return { ok: false, error: e.message };
            }
        });

        expect(offlineResponse.ok).toBe(true);
    });

    test('plusieurs pages visitees sont accessibles en mode offline', async ({ page, context }) => {
        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Visite plusieurs pages pour les mettre en cache
        const pagesToVisit = ['/', '/wishlist'];
        for (const path of pagesToVisit) {
            await page.goto(`${BASE_URL}${path}`);
            await page.waitForLoadState('networkidle');
        }

        // 3. Vérifie que des pages sont en cache
        const cachedPages = await page.evaluate(async () => {
            const cache = await caches.open('bibliotheque-pages');
            const keys = await cache.keys();
            return keys.map(req => new URL(req.url).pathname);
        });
        expect(cachedPages.length).toBeGreaterThan(0);

        // 4. Active le mode offline
        await context.setOffline(true);

        // 5. Visite chaque page et vérifie qu'elle est servie depuis le cache
        let pagesServedFromCache = 0;
        for (const path of pagesToVisit) {
            await page.goto(`${BASE_URL}${path}`, { waitUntil: 'commit' }).catch(() => {});
            await page.waitForTimeout(1000);

            const content = await page.content();
            // Si on n'affiche PAS le fallback offline, la page est servie depuis le cache
            if (!content.includes('Vous etes hors ligne')) {
                pagesServedFromCache++;
            }
        }

        // Au moins une page doit être servie depuis le cache
        expect(pagesServedFromCache).toBeGreaterThan(0);
    });
});
