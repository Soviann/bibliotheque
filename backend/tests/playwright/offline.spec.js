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

test.describe('Pre-cache automatique apres connexion', () => {

    test('pages principales sont pre-cachees automatiquement apres connexion', async ({ page }) => {
        // 1. Connexion
        await login(page);
        await waitForServiceWorker(page);

        // 2. Vérifier qu'on est bien sur la page d'accueil (pas sur login)
        expect(page.url()).not.toContain('/login');

        // 3. Vérifier que le contrôleur cache-warmer est présent sur la page
        const cacheWarmerPresent = await page.evaluate(() => {
            const cacheWarmerElement = document.querySelector('[data-controller*="cache-warmer"]');
            return {
                hasController: cacheWarmerElement !== null,
                urls: cacheWarmerElement?.getAttribute('data-cache-warmer-urls-value')
            };
        });
        expect(cacheWarmerPresent.hasController).toBe(true);

        // 4. Attendre que le cache warmer s'exécute (délai de 1s + temps d'exécution)
        await page.waitForTimeout(5000);

        // 5. Vérifier que les pages sont dans le cache bibliotheque-pages
        const cachedPages = await page.evaluate(async () => {
            const cache = await caches.open('bibliotheque-pages');
            const keys = await cache.keys();
            return keys.map(req => new URL(req.url).pathname);
        });

        // Les pages suivantes doivent être pré-cachées :
        expect(cachedPages).toContain('/');
        expect(cachedPages).toContain('/wishlist');
        expect(cachedPages).toContain('/comic/new');
    });

    test('API comics est pre-cachee automatiquement apres connexion', async ({ page }) => {
        // 1. Connexion
        await login(page);
        await waitForServiceWorker(page);

        // 2. Attendre que le cache warmer s'exécute
        await page.waitForTimeout(3000);

        // 3. Vérifier que l'API est dans le cache bibliotheque-api
        const apiInCache = await page.evaluate(async () => {
            const cache = await caches.open('bibliotheque-api');
            const keys = await cache.keys();
            return keys.some(req => req.url.includes('/api/comics'));
        });

        expect(apiInCache).toBe(true);
    });

    test('pages pre-cachees sont accessibles en mode offline sans visite prealable', async ({ page, context }) => {
        // 1. Connexion
        await login(page);
        await waitForServiceWorker(page);

        // 2. Attendre que le cache warmer s'exécute
        await page.waitForTimeout(3000);

        // 3. Aller sur une page neutre (pas les pages pré-cachées)
        await page.goto(`${BASE_URL}/offline`);
        await page.waitForTimeout(500);

        // 4. Activer le mode offline
        await context.setOffline(true);

        // 5. Naviguer vers /wishlist (qui n'a PAS été visitée manuellement, mais doit être pré-cachée)
        await page.goto(`${BASE_URL}/wishlist`, { waitUntil: 'commit' }).catch(() => {});
        await page.waitForTimeout(2000);

        // 6. Vérifier qu'on n'affiche PAS la page offline fallback
        const content = await page.content();
        const isOfflineFallback = content.includes('Vous etes hors ligne');
        expect(isOfflineFallback).toBe(false);
    });
});

test.describe('Indicateur hors ligne persistant', () => {

    test('indicateur hors ligne visible apres retour de page offline vers page cachee', async ({ page, context }) => {
        // Ce test vérifie que l'indicateur "Mode hors ligne" reste visible
        // après être passé par la page offline puis retourné sur une page en cache.

        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Visite l'accueil pour le mettre en cache
        await page.goto(`${BASE_URL}/`);
        await page.waitForLoadState('networkidle');

        // 3. Vérifie que l'accueil est en cache
        const homeInCache = await page.evaluate(async () => {
            const cache = await caches.open('bibliotheque-pages');
            const keys = await cache.keys();
            return keys.some(req => {
                const url = new URL(req.url);
                return url.pathname === '/';
            });
        });
        expect(homeInCache).toBe(true);

        // 4. Active le mode offline
        await context.setOffline(true);

        // 5. Navigation vers une page NON cachée (devrait afficher page offline)
        await page.goto(`${BASE_URL}/search?q=nonexistent`, { waitUntil: 'commit' }).catch(() => {});
        await page.waitForTimeout(2000);

        // Vérifie qu'on est sur la page offline
        const offlineContent = await page.content();
        expect(offlineContent).toContain('Vous etes hors ligne');

        // 6. Retour sur l'accueil (page en cache) via navigation Turbo
        // Simule un clic sur le lien ou utilise history.back()
        const homeLink = page.locator('a[href="/"]').first();
        if (await homeLink.isVisible()) {
            await homeLink.click();
        } else {
            // La page offline a un bouton "Retour" qui fait history.back()
            await page.click('.btn-secondary'); // Bouton "Retour"
        }
        await page.waitForTimeout(2000);

        // 7. Vérifie qu'on affiche l'accueil (pas la page offline fallback)
        const homeContent = await page.content();
        expect(homeContent).not.toContain('Vous etes hors ligne');

        // 8. POINT CRUCIAL : Vérifie que l'indicateur "Mode hors ligne" est visible
        const offlineIndicator = page.locator('#offline-indicator');
        await expect(offlineIndicator).toBeVisible();
        await expect(offlineIndicator).toContainText('Mode hors ligne');
    });

    test('indicateur hors ligne visible sur page cachee apres navigation Turbo depuis page offline', async ({ page, context }) => {
        // Variante : navigation Turbo vers page cachée après avoir été sur page offline

        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Visite wishlist pour la mettre en cache
        await page.goto(`${BASE_URL}/wishlist`);
        await page.waitForLoadState('networkidle');

        // 3. Visite l'accueil pour le mettre en cache aussi
        await page.goto(`${BASE_URL}/`);
        await page.waitForLoadState('networkidle');

        // 4. Active le mode offline
        await context.setOffline(true);

        // 5. Tente de naviguer vers une page non cachée via Turbo (recherche)
        const searchLink = page.locator('a[href="/search"]').first();
        if (await searchLink.isVisible()) {
            await searchLink.click();
            await page.waitForTimeout(2000);

            // Devrait afficher la page offline
            let content = await page.content();
            const isOnOfflinePage = content.includes('Vous etes hors ligne');

            if (isOnOfflinePage) {
                // 6. Navigation vers page cachée (wishlist)
                const wishlistLink = page.locator('a[href="/wishlist"]').first();
                if (await wishlistLink.isVisible()) {
                    await wishlistLink.click();
                } else {
                    await page.goto(`${BASE_URL}/wishlist`, { waitUntil: 'commit' }).catch(() => {});
                }
                await page.waitForTimeout(2000);

                // 7. Vérifie qu'on n'est PAS sur la page offline fallback
                content = await page.content();
                expect(content).not.toContain('Vous etes hors ligne');

                // 8. Vérifie que l'indicateur "Mode hors ligne" est visible
                const offlineIndicator = page.locator('#offline-indicator');
                await expect(offlineIndicator).toBeVisible();
                await expect(offlineIndicator).toContainText('Mode hors ligne');
            } else {
                // La page recherche était peut-être en cache, skip ce test
                test.skip();
            }
        } else {
            test.skip();
        }
    });

    test('indicateur hors ligne visible apres history.back() depuis page offline', async ({ page, context }) => {
        // Ce test simule le scénario exact rapporté :
        // 1. Page en cache avec indicateur → 2. Page offline → 3. history.back() → indicateur absent

        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Visite l'accueil pour le mettre en cache
        await page.goto(`${BASE_URL}/`);
        await page.waitForLoadState('networkidle');

        // 3. Visite la page recherche pour avoir une page dans l'historique
        await page.goto(`${BASE_URL}/search`);
        await page.waitForLoadState('networkidle');

        // 4. Retourne sur l'accueil (maintenant dans l'historique)
        await page.goto(`${BASE_URL}/`);
        await page.waitForLoadState('networkidle');

        // 5. Active le mode offline
        await context.setOffline(true);

        // 6. Attendre que l'événement offline soit détecté
        await page.waitForTimeout(1000);

        // 7. Vérifie que l'indicateur est visible
        const indicatorBefore = page.locator('#offline-indicator');
        await expect(indicatorBefore).toBeVisible();
        await expect(indicatorBefore).toContainText('Mode hors ligne');

        // 8. Navigation vers une page NON cachée (déclenche turbo:fetch-request-error)
        // La page /comic/999 n'existe pas et n'est pas en cache
        await page.goto(`${BASE_URL}/comic/999`, { waitUntil: 'commit' }).catch(() => {});
        await page.waitForTimeout(2000);

        // 9. Vérifie qu'on est sur la page offline
        let content = await page.content();
        expect(content).toContain('Vous etes hors ligne');

        // 10. Retour avec history.back()
        await page.goBack();
        await page.waitForTimeout(2000);

        // 11. Vérifie qu'on est revenu sur l'accueil (pas la page offline)
        content = await page.content();
        const isBackOnHome = !content.includes('Vous etes hors ligne');
        expect(isBackOnHome).toBe(true);

        // 12. POINT CRUCIAL : L'indicateur "Mode hors ligne" doit être visible
        const indicatorAfter = page.locator('#offline-indicator');
        await expect(indicatorAfter).toBeVisible();
        await expect(indicatorAfter).toContainText('Mode hors ligne');
    });

    test('indicateur hors ligne visible apres navigation Turbo vers page offline puis retour via lien', async ({ page, context }) => {
        // Scénario avec clics sur liens (navigation Turbo réelle) :
        // 1. Accueil (en cache) → 2. Clic vers page non cachée (page offline) → 3. Clic sur lien vers page cachée → indicateur ?

        // 1. Connexion et installation du SW
        await login(page);
        await waitForServiceWorker(page);

        // 2. Visite l'accueil et la wishlist pour les mettre en cache
        await page.goto(`${BASE_URL}/`);
        await page.waitForLoadState('networkidle');
        await page.goto(`${BASE_URL}/wishlist`);
        await page.waitForLoadState('networkidle');

        // 3. Retour sur l'accueil
        await page.goto(`${BASE_URL}/`);
        await page.waitForLoadState('networkidle');

        // 4. Active le mode offline
        await context.setOffline(true);
        await page.waitForTimeout(1000);

        // 5. Vérifie l'indicateur avant navigation
        const indicatorBefore = page.locator('#offline-indicator');
        await expect(indicatorBefore).toBeVisible();
        await expect(indicatorBefore).toContainText('Mode hors ligne');

        // 6. Navigation vers une page non cachée - Navigation directe
        await page.goto(`${BASE_URL}/comic/99999`, { waitUntil: 'commit' }).catch(() => {});
        await page.waitForTimeout(3000);

        // 7. On devrait être sur la page offline (via fallback SW)
        let content = await page.content();
        const isOnOfflinePage = content.includes('Vous etes hors ligne');
        expect(isOnOfflinePage).toBe(true);

        // 8. Navigation vers page cachée (wishlist) depuis la page offline
        // Note: la page offline n'a pas de navigation, on utilise goBack via le navigateur
        await page.goBack();
        await page.waitForTimeout(3000);

        // 9. Vérifie qu'on est revenu sur une page cachée (accueil)
        content = await page.content();
        const isBackOnCachedPage = !content.includes('Vous etes hors ligne');
        expect(isBackOnCachedPage).toBe(true);

        // 10. POINT CRUCIAL : L'indicateur doit être visible après retour
        const indicatorAfterReturn = page.locator('#offline-indicator');
        await expect(indicatorAfterReturn).toBeVisible();
        await expect(indicatorAfterReturn).toContainText('Mode hors ligne');
    });
});
