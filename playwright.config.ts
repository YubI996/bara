import { defineConfig, devices } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 8123);

/**
 * E2E + aksesibilitas (axe-core). Server Laravel memakai database terpisah (bara_e2e)
 * yang di-reset oleh global setup. Lihat tests/e2e/README.md.
 */
export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        locale: 'id-ID',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts/,
            use: {
                launchOptions: process.env.PW_CHROMIUM_PATH
                    ? { executablePath: process.env.PW_CHROMIUM_PATH }
                    : {},
            },
        },
        {
            name: 'chromium',
            dependencies: ['setup'],
            use: {
                ...devices['Desktop Chrome'],
                launchOptions: process.env.PW_CHROMIUM_PATH
                    ? { executablePath: process.env.PW_CHROMIUM_PATH }
                    : {},
            },
        },
        {
            name: 'mobile',
            dependencies: ['setup'],
            use: {
                ...devices['Pixel 7'],
                launchOptions: process.env.PW_CHROMIUM_PATH
                    ? { executablePath: process.env.PW_CHROMIUM_PATH }
                    : {},
            },
        },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: !process.env.CI,
        env: { DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'bara_e2e' },
        timeout: 60_000,
    },
});
