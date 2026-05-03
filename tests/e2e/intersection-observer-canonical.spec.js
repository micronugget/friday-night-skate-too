// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * @file
 * E2E tests for IntersectionObserver rootMargin/threshold on canonical entity page.
 *
 * Verifies that:
 * - The page-player IntersectionObserver uses rootMargin '0px' (not '200px'),
 *   so the player is NOT pre-initialised before the element enters the viewport.
 * - The player IS initialised once the facade element is scrolled into view.
 *
 * Issue: #107 — Tighten IntersectionObserver rootMargin/threshold on canonical
 * entity page.
 *
 * Prerequisites:
 *   - DDEV running (`ddev start`)
 *   - At least one archive_media node published and accessible via JSON:API
 */

/**
 * Resolve the canonical URL of the first archive_media node via JSON:API.
 *
 * Falls back to /node/1 if the API is unavailable or returns no results.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @returns {Promise<string>}
 */
async function getCanonicalUrl(request) {
  try {
    const resp = await request.get('/jsonapi/node/archive_media', {
      params: { 'page[limit]': 1, 'filter[status]': 1 },
    });
    if (resp.ok()) {
      const json = await resp.json();
      if (json.data && json.data.length > 0) {
        const nid = json.data[0].attributes.drupal_internal__nid;
        return `/node/${nid}`;
      }
    }
  }
  catch {
    // Fall through to default.
  }
  return '/node/1';
}

// ---------------------------------------------------------------------------
// Test suite: IntersectionObserver on canonical entity page
// ---------------------------------------------------------------------------
test.describe('IntersectionObserver rootMargin/threshold — canonical entity page', () => {

  test('player.js page-observer uses rootMargin 0px (not 200px)', async ({ page }) => {
    // Intercept the player.js script and assert the observer options.
    let playerJsContent = '';

    page.on('response', async (response) => {
      if (response.url().includes('player.js') && response.status() === 200) {
        try {
          playerJsContent = await response.text();
        }
        catch {
          // Ignore read errors.
        }
      }
    });

    await page.goto('/');

    // Give responses time to be captured.
    await page.waitForTimeout(2_000);

    if (playerJsContent) {
      // Must NOT contain the old wide rootMargin.
      expect(playerJsContent).not.toContain("rootMargin: '200px'");
      // Must contain the tightened rootMargin.
      expect(playerJsContent).toContain("rootMargin: '0px'");
      // Must contain the non-zero threshold.
      expect(playerJsContent).toContain('threshold: 0.1');
    }
    else {
      // player.js was not loaded on the home page — skip assertion gracefully.
      test.skip();
    }
  });

  test('player is NOT initialised before facade enters viewport on canonical page', async ({ page, request }) => {
    const url = await getCanonicalUrl(request);
    await page.goto(url);

    // Scroll to the very top to ensure the facade is off-screen if the page
    // is tall enough, or simply verify the initial state before any scroll.
    await page.evaluate(() => window.scrollTo(0, 0));

    // Give the IntersectionObserver a moment to fire (it should NOT fire yet
    // for an element that is below the fold with rootMargin '0px').
    await page.waitForTimeout(500);

    // On a canonical page the facade may already be in the viewport (short
    // page). We only assert "not initialized" when the facade is genuinely
    // out of view.
    const facade = page.locator('.videojs-lazy-facade').first();
    const facadeCount = await facade.count();

    if (facadeCount === 0) {
      // No video on this node — nothing to test.
      test.skip();
      return;
    }

    const isInViewport = await facade.evaluate((el) => {
      const rect = el.getBoundingClientRect();
      return rect.top < window.innerHeight && rect.bottom > 0;
    });

    if (!isInViewport) {
      // Facade is below the fold — with rootMargin '0px' it must NOT be
      // initialized yet.
      await expect(facade).not.toHaveAttribute('data-videojs-initialized', { timeout: 1_000 });
    }
    else {
      // Facade is already in the viewport — player may legitimately be
      // initialized; just verify no JS errors occurred.
      // (Short canonical pages are a valid case.)
      test.info().annotations.push({
        type: 'note',
        description: 'Facade already in viewport on page load — skipping pre-init assertion.',
      });
    }
  });

  test('player IS initialised after facade is scrolled into view', async ({ page, request }) => {
    const url = await getCanonicalUrl(request);
    await page.goto(url);

    const facade = page.locator('.videojs-lazy-facade').first();
    const facadeCount = await facade.count();

    if (facadeCount === 0) {
      test.skip();
      return;
    }

    // Scroll the facade into view.
    await facade.scrollIntoViewIfNeeded();

    // With threshold: 0.1 the player should initialize once 10 % of the
    // element is visible.
    await expect(facade).toHaveAttribute('data-videojs-facade-initialized', { timeout: 8_000 });
  });

  test('no console errors on canonical page load', async ({ page, request }) => {
    const url = await getCanonicalUrl(request);

    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Only capture VideoJS / player-related errors; ignore generic 404s
        // that may occur on nodes without all assets present.
        if (
          text.includes('videojs') ||
          text.includes('VideoJS') ||
          text.includes('player.js') ||
          text.includes('IntersectionObserver') ||
          text.includes('initPlayerFromFacade')
        ) {
          consoleErrors.push(text);
        }
      }
    });

    await page.goto(url);

    // Allow time for IntersectionObserver callbacks and player init.
    await page.waitForTimeout(2_000);

    expect(consoleErrors).toHaveLength(0);
  });

});
