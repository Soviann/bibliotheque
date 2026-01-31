// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/playwright',
    timeout: 30000,
    expect: {
        timeout: 5000
    },
    use: {
        baseURL: 'https://bibliotheque.ddev.site',
        ignoreHTTPSErrors: true,
        trace: 'on-first-retry',
        // Accepte les certificats auto-signés
        launchOptions: {
            args: [
                '--ignore-certificate-errors',
                '--ignore-certificate-errors-spki-list',
                '--allow-insecure-localhost',
            ],
        },
    },
    reporter: 'list',
});
