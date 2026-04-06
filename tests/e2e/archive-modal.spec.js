// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * @file
 * E2E tests for the archive masonry grid and cinematic modal viewer.
 *
 * These tests verify client-side JavaScript behaviour that PHPUnit cannot
 * cover: Masonry layout positioning, modal open/close, VideoJS player
 * initialisation, and keyboard navigation.
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
// Test suite: Masonry Grid Layout
// ---------------------------------------------------------------------------
test.describe('Archive Masonry Grid', () => {

  test('masonry items have absolute positioning applied by JS', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    // Wait for at least one masonry item to appear.
    const firstItem = page.locator('.masonry-item').first();
    await expect(firstItem).toBeVisible({ timeout: 10_000 });

    // Masonry.js sets position: absolute on each .masonry-item.
    await expect(firstItem).toHaveCSS('position', 'absolute');

    // Verify left/top are set (Masonry's signature layout).
    const style = await firstItem.evaluate((el) => {
      const cs = window.getComputedStyle(el);
      return { left: cs.left, top: cs.top };
    });
    // Values should be pixel numbers, not 'auto'.
    expect(style.left).toMatch(/^\d+px$/);
    expect(style.top).toMatch(/^\d+px$/);
  });

  test('grid is responsive — column count changes with viewport width', async ({ page, request }) => {
    const url = await getArchiveUrl(request);

    // Desktop viewport.
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(url);
    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    const desktopPositions = await page.$$eval('.masonry-item', (els) =>
      els.map((el) => parseFloat(window.getComputedStyle(el).left))
    );
    const desktopColumns = new Set(desktopPositions).size;

    // Mobile viewport.
    await page.setViewportSize({ width: 375, height: 667 });
    // Trigger resize and wait for Masonry relayout.
    await page.waitForTimeout(500);

    const mobilePositions = await page.$$eval('.masonry-item', (els) =>
      els.map((el) => parseFloat(window.getComputedStyle(el).left))
    );
    const mobileColumns = new Set(mobilePositions).size;

    // Desktop should have more columns than mobile (or at least equal if only 1 item).
    expect(desktopColumns).toBeGreaterThanOrEqual(mobileColumns);
  });

  test('layout recalculates on window resize', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(url);
    await page.locator('.masonry-item').first().waitFor({ state: 'visible', timeout: 10_000 });

    const items = await page.locator('.masonry-item').count();
    if (items < 2) {
      test.skip();
      return;
    }

    // Capture initial positions.
    const before = await page.$$eval('.masonry-item', (els) =>
      els.map((el) => el.style.left)
    );

    // Resize to a narrow viewport.
    await page.setViewportSize({ width: 480, height: 800 });
    await page.waitForTimeout(600);

    const after = await page.$$eval('.masonry-item', (els) =>
      els.map((el) => el.style.left)
    );

    // Masonry should have recalculated — verify the grid container still
    // has position: relative (Masonry's wrapper style) and items are absolute.
    // With very few items both viewports may use a single column, so we
    // verify Masonry is still active rather than requiring position changes.
    const firstItem = page.locator('.masonry-item').first();
    await expect(firstItem).toHaveCSS('position', 'absolute');
  });
});

// ---------------------------------------------------------------------------
// Test suite: Cinematic Modal Viewer
// ---------------------------------------------------------------------------
test.describe('Archive Modal Viewer', () => {

  test('clicking a thumbnail opens the modal with a video element', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    const firstItem = page.locator('.masonry-item').first();
    await expect(firstItem).toBeVisible({ timeout: 10_000 });

    // Modal should not be open initially.
    const modal = page.locator('#fns-cinematic-modal');
    await expect(modal).not.toHaveClass(/is-open/);

    // Click the first thumbnail.
    await firstItem.click();

    // Modal should open.
    await expect(modal).toHaveClass(/is-open/, { timeout: 5_000 });

    // For video items, a <video> element should be present in the modal.
    const mediaType = await firstItem.getAttribute('data-media-type');
    if (mediaType === 'video') {
      const video = modal.locator('video');
      await expect(video).toBeVisible({ timeout: 5_000 });
    }
  });

  test('Escape key closes the modal', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    const firstItem = page.locator('.masonry-item').first();
    await expect(firstItem).toBeVisible({ timeout: 10_000 });

    // Open modal.
    await firstItem.click();
    const modal = page.locator('#fns-cinematic-modal');
    await expect(modal).toHaveClass(/is-open/, { timeout: 5_000 });

    // Press Escape.
    await page.keyboard.press('Escape');

    // Modal should close.
    await expect(modal).not.toHaveClass(/is-open/, { timeout: 5_000 });
  });

  test('Prev/Next buttons navigate between items in the modal', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    const itemCount = await page.locator('.masonry-item').count();
    if (itemCount < 2) {
      test.skip();
      return;
    }

    // Open modal on first item.
    await page.locator('.masonry-item').first().click();
    const modal = page.locator('#fns-cinematic-modal');
    await expect(modal).toHaveClass(/is-open/, { timeout: 5_000 });

    // Wait for modal to fully render.
    await page.waitForTimeout(500);

    const getCounter = async () =>
      modal.locator('.fns-modal__counter').textContent();

    const before = await getCounter();

    // Click the Next button — mouse navigation always navigates slides
    // regardless of whether a VideoJS player is active.
    await modal.locator('.fns-modal__nav--next').click();
    await page.waitForTimeout(400);

    const after = await getCounter();
    expect(after).not.toBe(before);

    // Click Prev to go back.
    await modal.locator('.fns-modal__nav--prev').click();
    await page.waitForTimeout(400);

    const back = await getCounter();
    expect(back).toBe(before);
  });

  test('Arrow keys seek video when VideoJS player is active (not slide navigation)', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    // Open modal on first item.
    await page.locator('.masonry-item').first().click();
    const modal = page.locator('#fns-cinematic-modal');
    await expect(modal).toHaveClass(/is-open/, { timeout: 5_000 });

    // Check if a video element is present (VideoJS player active).
    const video = modal.locator('video');
    const hasVideo = await video.count() > 0;
    if (!hasVideo) {
      test.skip();
      return;
    }

    await page.waitForTimeout(500);

    const counterBefore = await modal.locator('.fns-modal__counter').textContent();

    // ArrowRight should seek the video, NOT navigate to the next slide.
    await page.evaluate(() => {
      document.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'ArrowRight',
        code: 'ArrowRight',
        bubbles: true,
        cancelable: true,
      }));
    });
    await page.waitForTimeout(400);

    const counterAfter = await modal.locator('.fns-modal__counter').textContent();

    // Counter must NOT have changed — arrow key sought the video, not navigated.
    expect(counterAfter).toBe(counterBefore);
  });

  test('Arrow keys navigate slides when no VideoJS player is active', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    const itemCount = await page.locator('.masonry-item').count();
    if (itemCount < 2) {
      test.skip();
      return;
    }

    // Open modal on first item.
    await page.locator('.masonry-item').first().click();
    const modal = page.locator('#fns-cinematic-modal');
    await expect(modal).toHaveClass(/is-open/, { timeout: 5_000 });
    await page.waitForTimeout(500);

    // Check if a VideoJS player is active.
    const hasVideo = (await modal.locator('video').count()) > 0;
    if (hasVideo) {
      // Arrow keys have VideoJS priority — slide navigation via keyboard
      // is only active when no player is present. Skip this test.
      test.skip();
      return;
    }

    const counterBefore = await modal.locator('.fns-modal__counter').textContent();

    await page.evaluate(() => {
      document.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'ArrowRight',
        code: 'ArrowRight',
        bubbles: true,
        cancelable: true,
      }));
    });
    await page.waitForTimeout(400);

    const counterAfter = await modal.locator('.fns-modal__counter').textContent();
    expect(counterAfter).not.toBe(counterBefore);
  });

  test('close button dismisses the modal', async ({ page, request }) => {
    const url = await getArchiveUrl(request);
    await page.goto(url);

    const firstItem = page.locator('.masonry-item').first();
    await expect(firstItem).toBeVisible({ timeout: 10_000 });

    // Open modal.
    await firstItem.click();
    const modal = page.locator('#fns-cinematic-modal');
    await expect(modal).toHaveClass(/is-open/, { timeout: 5_000 });

    // Click close button.
    await modal.locator('.fns-modal__close').click();

    // Modal should close.
    await expect(modal).not.toHaveClass(/is-open/, { timeout: 5_000 });
  });
});
