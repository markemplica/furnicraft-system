import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: true,
    reporter: 'list',
    use: {
        ...devices['Desktop Chrome'],
        channel: 'msedge',
        baseURL: 'http://127.0.0.1:8001',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop-edge', use: { ...devices['Desktop Chrome'], channel: 'msedge' } },
        { name: 'mobile-edge', use: { ...devices['Desktop Chrome'], channel: 'msedge', viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true } },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        url: 'http://127.0.0.1:8001/up',
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
