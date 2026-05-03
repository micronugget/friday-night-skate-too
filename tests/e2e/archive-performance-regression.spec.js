// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * @file
 * Performance regression guard for the archive page (/archive/3).
 *
 * Asserts that the optimisations introduced in issue #105 sub-tasks hold:
 *   1. No `<style class="vjs-styles-dimensions">` injected into <head>
 *      (confirms VideoJS is NOT initialised on page load).
 *   2. No `.video-js.vjs-paused` elements in the DOM
 *      (confirms no VideoJS player instances are created on load).
 *   3. DOM `loadEventEnd` is below a documented threshold
 *      (guards against load-time regressions).
 *
 * Lighthouse baseline reports live in tests/performance/.
 *
 * To deliberately break this test (CI regression check), initialise VideoJS
 * eagerly on page load and confirm the assertions fail.
 */

/** Maximum allowed loadEventEnd in milliseconds (generous for CI/DDEV). */
const LOAD_EVENT_END_THRESHOLD_MS = 15_000;

/**
 * Resolve the archive page URL dynamically via JSON:API.
 * Falls back to /archive/3 if the API is unavailable.
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
  } catch (_) {
    // Fall through to default.
  }
  return '/archive/3';
}

test.describe('Archive page — performance regression guard', () => {
  /** @type {string} */
  let archiveUrl;

  test.beforeAll(async ({ request }) => {
    archiveUrl = await getArchiveUrl(request);
  });

  test('no vjs-styles-dimensions <style> injected into <head>', async ({ page }) => {
    await page.goto(archiveUrl, { waitUntil: 'domcontentloaded' });

    const vjsStyleCount = await page.evaluate(() => {
      return document.head.querySelectorAll('style.vjs-styles-dimensions').length;
    });

    expect(
      vjsStyleCount,
      'VideoJS injected vjs-styles-dimensions into <head> — VideoJS is being initialised on page load'
    ).toBe(0);
  });

  test('no .video-js.vjs-paused elements present on load', async ({ page }) => {
    await page.goto(archiveUrl, { waitUntil: 'domcontentloaded' });

    const vjsPlayerCount = await page.evaluate(() => {
      return document.querySelectorAll('.video-js.vjs-paused').length;
    });

    expect(
      vjsPlayerCount,
      '.video-js.vjs-paused elements found — VideoJS players are being initialised on page load'
    ).toBe(0);
  });

  test(`DOM loadEventEnd is below ${LOAD_EVENT_END_THRESHOLD_MS} ms`, async ({ page }) => {
    await page.goto(archiveUrl, { waitUntil: 'load' });

    const loadEventEnd = await page.evaluate(() => {
      const [entry] = performance.getEntriesByType('navigation');
      return entry ? entry.loadEventEnd : performance.timing.loadEventEnd - performance.timing.navigationStart;
    });

    expect(
      loadEventEnd,
      `loadEventEnd (${Math.round(loadEventEnd)} ms) exceeded threshold of ${LOAD_EVENT_END_THRESHOLD_MS} ms`
    ).toBeLessThan(LOAD_EVENT_END_THRESHOLD_MS);
  });
});
