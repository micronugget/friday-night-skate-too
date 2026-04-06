# Issues — Archive Modal & Masonry Fixes ✅ COMPLETE

> Planning document created 2026-04-06.
> All sub-issues resolved — epic #62 closed 2026-04-06.

---

## Current State

| Feature | Status | Detail |
|---------|--------|--------|
| Masonry grid HTML | ✅ Working | `.masonry-grid`, `.masonry-sizer`, `.masonry-item` all render correctly |
| Masonry JS layout | ✅ Fixed | Masonry initialises correctly; items tile in responsive grid (#64) |
| Modal open/close | ✅ Working | Clicking a thumbnail opens the cinematic lightbox; Esc/close button dismiss it |
| VideoJS in modal | ✅ Fixed | `videojs_media/videojs-player` library loaded on archive pages; VideoJS available in modal (#63) |
| Teaser thumbnails | ✅ Working | Poster images with play-icon overlay render via `videojs-media--teaser.html.twig` |
| Keyboard nav in modal | ✅ Working | Verified with working VideoJS playback (#61, #65) |
| Data attributes | ✅ Working | `data-media-type`, `data-video-url`, `data-video-id`, `data-fullsize`, `data-date`, `data-title`, `data-uploader` all present |

### Why Tests Passed Despite Broken Features

The existing `MasonryDataAttributesTest` is a **Functional** (BrowserTestBase) test that asserts HTML markup and data attributes — it does **not** execute JavaScript. Masonry layout and VideoJS playback are purely client-side JS behaviours that PHPUnit cannot verify. The unit tests for `ModerationNotifier` and `videojs_media` test PHP logic only.

---

## Root Causes

### RC-1: VideoJS library not loaded on archive page

The `videojs_media:player` SDC component declares VideoJS JS/CSS in its `libraryOverrides` (from `node_modules` inside the module). The **teaser** view mode template (`videojs-media--teaser.html.twig`) intentionally does **not** include the player component — it only renders a poster image. This is correct for the grid thumbnails.

However, `modal-viewer.js` needs VideoJS available when the user clicks a thumbnail to open the lightbox. Currently the `modal-viewer` library in `fridaynightskate.libraries.yml` has no dependency on VideoJS, so `typeof videojs` is always `undefined` and video playback silently fails — only the poster image is shown in the modal.

### RC-2: Masonry JS layout not applying

`archive-masonry.js` is bundled (webpack) and loaded on the page. The Masonry library initialises on `.masonry-grid` with `itemSelector: '.masonry-item'` and `columnWidth: '.masonry-sizer'`. Possible causes:
1. **CSS conflict**: The `.masonry-sizer` element may have zero width if the masonry SCSS isn't applying correctly, causing Masonry to calculate 0-width columns.
2. **imagesLoaded timing**: Masonry waits for `imagesLoaded(grid)` — if lazy-loaded images haven't triggered, layout may not fire.
3. **JS error upstream**: If another JS file errors before `archive-masonry.js` runs, Drupal behaviors may not attach.

---

## Epic 1: VideoJS Playback in Modal Viewer

> **Goal**: When a user clicks a video thumbnail in the archive masonry grid, the modal opens and the video plays using the VideoJS Media player with full controls.

### Issue 1.1 — Load VideoJS library on archive pages ([#63](https://github.com/micronugget/friday-night-skate-too/issues/63)) ✅ DONE

**Problem**: The `modal-viewer` theme library has no dependency on VideoJS JS/CSS.

**Solution options** (pick one):
- **A) Add a `videojs_media.libraries.yml`** file to the `videojs_media` module that exposes a `videojs_media/videojs-player` library pointing to the same `node_modules` assets as the SDC. Then add `videojs_media/videojs-player` as a dependency of `fridaynightskate/modal-viewer` in `fridaynightskate.libraries.yml`.
- **B) Dynamic loading in `modal-viewer.js`** — lazy-load VideoJS JS/CSS from a CDN or module path when the modal opens for the first time. More complex but avoids loading VideoJS on every archive page load.
- **C) Use the SDC include** — add `{% include "videojs_media:player" %}` inside the modal DOM built by JS. Not feasible since the modal is built in JS, not Twig.

**Recommended**: Option A — simplest, most Drupal-idiomatic.

**Acceptance criteria**:
- [x] `typeof videojs` is defined when `modal-viewer.js` runs on `/archive/{term}`
- [x] VideoJS CSS (video-js.css) is loaded on the page
- [x] No JS console errors related to VideoJS

### Issue 1.2 — Initialise VideoJS player correctly in modal ([#65](https://github.com/micronugget/friday-night-skate-too/issues/65)) ✅ DONE

**Problem**: `modal-viewer.js` `renderVideo()` creates a `<video>` element and calls `videojs(uid, {...})` but may have issues with:
- YouTube videos needing `videojs-youtube` plugin (loaded by SDC but not by modal)
- Source type detection (`video/youtube` vs `video/mp4`)
- Player disposal on modal close / navigation

**Acceptance criteria**:
- [x] Local `.mp4` videos play in the modal with controls
- [x] Remote `.mp4` videos play in the modal
- [x] YouTube videos play in the modal (if `videojs-youtube` plugin is loaded)
- [x] Navigating between slides disposes the previous player cleanly
- [x] Closing the modal disposes the player

### Issue 1.3 — VideoJS theming in modal matches Starry Night aesthetic ([#66](https://github.com/micronugget/friday-night-skate-too/issues/66)) ✅ DONE

**Problem**: The SDC player component (`player.js`) registers players, sets up hotkeys, and applies one-at-a-time playback. The modal creates its own VideoJS instance outside this flow.

**Acceptance criteria**:
- [x] Modal VideoJS player uses `vjs-big-play-centered` skin
- [x] Player respects the Starry Night colour scheme (gold accents, dark background)
- [x] Hotkeys work inside the modal (space = play/pause, ←/→ = seek, not conflict with modal nav)

---

## Epic 2: Masonry Grid Layout

> **Goal**: Archive thumbnails tile in a responsive masonry grid (not a single vertical column).

### Issue 2.1 — Debug and fix Masonry JS initialisation ([#64](https://github.com/micronugget/friday-night-skate-too/issues/64)) ✅ DONE

**Problem**: Masonry JS is loaded but layout effect is not visible.

**Steps to diagnose**:
1. Check browser console for JS errors that prevent `Drupal.behaviors.archiveMasonry` from running
2. Verify `.masonry-sizer` has a non-zero computed width
3. Verify `imagesLoaded` callback fires
4. Check if Masonry instance is stored on `grid.masonryInstance`

**Acceptance criteria**:
- [x] `.masonry-item` elements are positioned with `position: absolute` and `left`/`top` values (Masonry's signature)
- [x] Grid is responsive: 1 col on mobile, 2–5 cols on wider screens
- [x] Layout recalculates on window resize

### Issue 2.2 — Ensure masonry CSS grid fallback ([#67](https://github.com/micronugget/friday-night-skate-too/issues/67)) ✅ DONE

**Problem**: If JS fails, items should still display in a reasonable CSS grid layout.

**Acceptance criteria**:
- [x] Without JS, items display in a CSS grid/flexbox fallback (not overlapping or invisible)
- [x] With JS, Masonry overrides the CSS layout

---

## Epic 3: Test Coverage for Client-Side Behaviour

> **Goal**: Prevent regressions — tests should catch when JS-dependent features break.

### Issue 3.1 — Add Nightwatch.js or Cypress E2E tests for archive modal ([#68](https://github.com/micronugget/friday-night-skate-too/issues/68)) ✅ DONE

**Problem**: PHPUnit Functional tests cannot execute JavaScript. The current `MasonryDataAttributesTest` only verifies HTML attributes, not JS behaviour.

**Options**:
- **Nightwatch.js** — Drupal core ships with Nightwatch support
- **Cypress** — more modern, better DX
- **Playwright** — lightweight, fast

**Acceptance criteria**:
- [x] E2E test visits `/archive/{term}` and verifies Masonry layout applied (items have absolute positioning)
- [x] E2E test clicks a thumbnail and verifies modal opens with video element
- [x] E2E test verifies keyboard navigation (←/→ seek VideoJS when player active; Prev/Next buttons navigate slides; Esc closes modal)
- [x] Tests run locally with DDEV (`ddev exec "cd tests/e2e && npx playwright test"`)

### Issue 3.2 — Improve PHPUnit coverage for VideoJS library attachment ([#69](https://github.com/micronugget/friday-night-skate-too/issues/69)) ✅ DONE

**Problem**: No test verifies that VideoJS JS/CSS assets are actually attached to the archive page response.

**Acceptance criteria**:
- [x] Functional test asserts that the `videojs_media/videojs-player` library (or equivalent) is in the page's `drupalSettings` or `<script>` tags on `/archive/{term}`

---

## Priority Order

| Priority | Issue | Effort | Impact |
|----------|-------|--------|--------|
| P0 | 1.1 — Load VideoJS library ✅ | Small | Unblocks all video playback |
| P0 | 2.1 — Fix Masonry JS ✅ | Small–Medium | Core visual feature |
| P1 | 1.2 — VideoJS init in modal ✅ | Medium | Correct playback for all bundles |
| P2 | 1.3 — VideoJS theming ✅ | Small | Polish |
| P2 | 2.2 — CSS grid fallback ✅ | Small | Graceful degradation |
| P3 | 3.1 — E2E tests ✅ | Large | Long-term regression prevention |
| P3 | 3.2 — PHPUnit library test ✅ | Small | Quick win for CI |

---

## Dependency Graph

```
1.1 (load VideoJS) ──► 1.2 (init in modal) ──► 1.3 (theming)
                                                      │
2.1 (fix Masonry) ──► 2.2 (CSS fallback)             │
                                                      ▼
                                              3.1 (E2E tests)
                                              3.2 (PHPUnit library test)
```
