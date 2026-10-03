import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

test.beforeEach(async ({ page }) => {
    await page.route('https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css', (route) => route.fulfill({
        path: resolve('node_modules/bootstrap/dist/css/bootstrap.min.css'),
        contentType: 'text/css',
    }));
    await page.route('https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js', (route) => route.fulfill({
        path: resolve('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'),
        contentType: 'text/javascript',
    }));
});

for (const path of ['/', '/login']) {
    test(`${path} loads without horizontal overflow or unnamed controls`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        const response = await page.goto(path);

        expect(response?.ok()).toBeTruthy();
        await expect(page.locator('main')).toHaveCount(1);
        await expect(page.locator('h1')).toHaveCount(1);

        const audit = await page.evaluate(() => ({
            documentWidth: document.documentElement.scrollWidth,
            viewportWidth: window.innerWidth,
            overflowingElements: [...document.querySelectorAll('body *')]
                .map((element) => ({
                    tag: element.tagName.toLowerCase(),
                    className: typeof element.className === 'string' ? element.className : '',
                    right: Math.round(element.getBoundingClientRect().right),
                    left: Math.round(element.getBoundingClientRect().left),
                }))
                .filter((element) => element.right > document.documentElement.clientWidth + 1 || element.left < -1)
                .slice(0, 8),
            imagesWithoutAlt: [...document.images].filter((image) => !image.hasAttribute('alt')).length,
            controlsWithoutLabels: [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')]
                .filter((control) => !control.labels?.length && !control.getAttribute('aria-label') && !control.getAttribute('aria-labelledby')).length,
            buttonsWithoutNames: [...document.querySelectorAll('button')]
                .filter((button) => !(button.innerText.trim() || button.getAttribute('aria-label') || button.getAttribute('title'))).length,
        }));

        expect(audit.documentWidth, JSON.stringify(audit.overflowingElements)).toBeLessThanOrEqual(audit.viewportWidth);
        expect(audit.imagesWithoutAlt).toBe(0);
        expect(audit.controlsWithoutLabels).toBe(0);
        expect(audit.buttonsWithoutNames).toBe(0);
        expect(errors).toEqual([]);
    });
}

test('skip link receives keyboard focus before page navigation', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
});

test('application health endpoint responds successfully', async ({ request }) => {
    const response = await request.get('/up');
    expect(response.ok()).toBeTruthy();
});
