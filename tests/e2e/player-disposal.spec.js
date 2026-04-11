// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * @file
 * E2E tests for VideoJS player disposal on modal close.
 *
 * Verifies that:
 * - After closing a modal, no VideoJS player instance remains active
 *   (data-videojs-initialized is removed / absent).
 * - Re-opening the same modal re-initializes the player correctly.
 * - Repeated open/close cycles produce no console errors.
 *
 * Prerequisites:
 *   - DDEV running (`ddev start`)
 *   - At least one archive_media node linked to a Skate Date taxonomy term
 *   - The archive_by_date view accessible at /archive/{tid}
 */

/**
 * Resolve the archive page URL dynamically.
 *
 * Visits the Drupal JSON:API taxonomy endpoint to find the first Skate Date
 * term, then returns /archive/{tid}. Falls back to /archive/3 if the API
 * is unavailable.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @returns {Promise<string>}
 */
async function getArchiveUrl(request) {
  try {
    const resp = await request.get('/jsonapi/taxonomy_term/skate_date', {
      params: { 'page[limit]': 1 },
    });
    if (resp.ok()) {
      const json = await resp.json();
      if (json.data && json.data.length > 0) {
        const tid = json.data[0].attributes.drupal_internal__tid;
        return `/archive/${tid}`;
      }
    }
  }
  catch {
    // Fall through to default.
  }
  return '/archive/3';
}

// ---------------------------------------------------------------------------
// Test suite: Player Disposal Lifecycle
// ---------------------------------------------------------------------------
test.describe('VideoJS Player Disposal on Modal Close', () => {

  test('player is not initialized before modal opens', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    // Wait for at least one masonry item to appear.
    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    // No player should be initialized before any modal is opened.
    const initializedPlayers = page.locator('[data-videojs-initialized]');
    await expect(initializedPlayers).toHaveCount(0);
  });

  test('opening a modal initializes the player', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    // Click the first masonry item to open the modal.
    await page.locator('.masonry-item').first().click();

    // Wait for the Bootstrap modal to be visible.
    const modal = page.locator('.modal.show').first();
    await expect(modal).toBeVisible({ timeout: 5_000 });

    // The player inside the modal should now be initialized.
    const playerInModal = modal.locator('[data-videojs-initialized]');
    await expect(playerInModal).toHaveCount(1, { timeout: 5_000 });
  });

  test('closing a modal disposes the player — data-videojs-initialized is removed', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    // Open modal.
    await page.locator('.masonry-item').first().click();
    const modal = page.locator('.modal.show').first();
    await expect(modal).toBeVisible({ timeout: 5_000 });

    // Wait for player to initialize.
    await expect(modal.locator('[data-videojs-initialized]')).toHaveCount(1, { timeout: 5_000 });

    // Close the modal via the close button.
    await modal.locator('[data-bs-dismiss="modal"], .btn-close').first().click();

    // Wait for modal to be hidden.
    await expect(modal).not.toBeVisible({ timeout: 5_000 });

    // After close, no initialized player should remain.
    const initializedPlayers = page.locator('[data-videojs-initialized]');
    await expect(initializedPlayers).toHaveCount(0);
  });

  test('re-opening the same modal re-initializes the player', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    const firstItem = page.locator('.masonry-item').first();

    // Open modal.
    await firstItem.click();
    const modal = page.locator('.modal.show').first();
    await expect(modal).toBeVisible({ timeout: 5_000 });
    await expect(modal.locator('[data-videojs-initialized]')).toHaveCount(1, { timeout: 5_000 });

    // Close modal.
    await modal.locator('[data-bs-dismiss="modal"], .btn-close').first().click();
    await expect(modal).not.toBeVisible({ timeout: 5_000 });

    // Re-open the same modal.
    await firstItem.click();
    await expect(modal).toBeVisible({ timeout: 5_000 });

    // Player must be re-initialized.
    await expect(modal.locator('[data-videojs-initialized]')).toHaveCount(1, { timeout: 5_000 });
  });

  test('repeated open/close cycles produce no console errors', async ({ page, request }) => {
    const url = await getArchiveUrl(request);

    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto(url);
    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    const firstItem = page.locator('.masonry-item').first();

    // Open and close the modal 5 times.
    for (let i = 0; i < 5; i++) {
      await firstItem.click();
      const modal = page.locator('.modal.show').first();
      await expect(modal).toBeVisible({ timeout: 5_000 });
      await expect(modal.locator('[data-videojs-initialized]')).toHaveCount(1, { timeout: 5_000 });

      await modal.locator('[data-bs-dismiss="modal"], .btn-close').first().click();
      await expect(modal).not.toBeVisible({ timeout: 5_000 });
    }

    // No console errors should have occurred during the cycles.
    expect(consoleErrors).toHaveLength(0);
  });

  test('no initialized players remain after closing all modals', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    // Open and close the first modal.
    await page.locator('.masonry-item').first().click();
    const modal = page.locator('.modal.show').first();
    await expect(modal).toBeVisible({ timeout: 5_000 });
    await modal.locator('[data-bs-dismiss="modal"], .btn-close').first().click();
    await expect(modal).not.toBeVisible({ timeout: 5_000 });

    // Globally, no initialized players should remain.
    await expect(page.locator('[data-videojs-initialized]')).toHaveCount(0);
  });

});
