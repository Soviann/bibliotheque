// @ts-check
const { test, expect } = require('@playwright/test');

const BASE_URL = 'https://bibliotheque.ddev.site';

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
