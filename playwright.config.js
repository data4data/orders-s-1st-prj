import { defineConfig } from '@playwright/test';

/**
 * Browser smoke tests for the UI standards (docs/diagrams/pages.html) against the dev app with the
 * demo data loaded (bin/console foundry:load-fixtures main). Uses the installed Google Chrome.
 *
 * The app is served by PHP's built-in server on 127.0.0.1:8123, and Chrome maps every *.shop.test
 * host to it, so the tests do not depend on Herd. Run: npx playwright test
 */
export default defineConfig({
    testDir: 'tests/e2e',
    globalSetup: './tests/e2e/global-setup.js',
    timeout: 30_000,
    fullyParallel: false,
    workers: 1,
    reporter: [['list']],
    use: {
        channel: 'chrome',
        headless: true,
        launchOptions: { args: ['--host-resolver-rules=MAP *.shop.test 127.0.0.1'] },
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'php -S 127.0.0.1:8123 -t public',
        url: 'http://127.0.0.1:8123/build/.vite/manifest.json',
        reuseExistingServer: true,
        timeout: 30_000,
    },
});
