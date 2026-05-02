# Issues — Archive View / Masonry CPU Optimization
> Planning document created 2026-05-02.
> Addresses high CPU usage when simply loading the `/archive/{tid}` Views page,
> even with only a handful of items rendered.

---

## Problem Statement

Loading `/archive/3` (the Masonry archive view) produces sustained high CPU
load and audible fan noise with as few as **6 items** on the page. This is far
below any reasonable per-page item count and indicates that work is being done
**per item** that is disproportionate to the user-visible result.

Inspection of the rendered HTML for `/archive/3` shows the smoking gun:

```html
<style class="vjs-styles-dimensions">
  .fns-modal-video-1777685342755-dimensions {
    width: 3840px;
    height: 2160px;
  }
  ...
</style>
```

That `<style class="vjs-styles-dimensions">` block is injected by VideoJS when
a player instance is **fully initialised** and reads its source's native
dimensions. Seeing it on the archive page means VideoJS is initialising a
**full player per teaser**, even though the teasers exist only to be opened
later in the modal.

This is unacceptable for a module we want to publish on drupal.org. The
performance characteristics need to look like an industry-standard masonry
gallery (Pinterest/Etsy/Tumblr) — not like a 4K video editor.

### Root Causes Identified

| # | Cause | Impact |
|---|-------|--------|
| 1 | **`IntersectionObserver` initialises a full VideoJS player on every facade in or near the viewport** (`rootMargin: '200px'`). On a 6-item masonry grid that all fits on screen, all 6 players initialise simultaneously. | **Critical** — each init parses video source, registers hotkeys + mobileUi, creates ~50 DOM nodes, reads native video dimensions (4K → injects `<style>` block). 6× = CPU spike + fan. |
| 2 | **Teaser facades initialise VideoJS at all.** In the masonry context the teaser player is *never* used — the modal creates a fresh player on click. The teaser only needs a poster + play button. | **Critical** — pure waste. Pinterest/Etsy thumbnails are plain `<img>` with zero JS attached per item. |
| 3 | **`data-poster-picture` attribute holds full rendered `<picture>` HTML (~1–2 KB) per row.** Stored as a string, escaped, then re-parsed by JS on modal open. | **Medium** — bloats initial HTML payload, costs render time on parse. |
| 4 | **Masonry.js layout cost** is paid even when CSS Grid / CSS multi-column would render visually identically. Every resize triggers a recalculation of all items. | **Low** at 6 items, **Medium** as the archive grows. |
| 5 | **The `videojs-styles-dimensions` `<style>` block leaks dimensions of the underlying source** — useful for the player, but evidence of metadata reads happening at page-load time. | **Diagnostic signal** — confirms #1 and #2. |
| 6 | **No automated performance regression test.** There is nothing preventing this from happening again after a future refactor. | **Process gap** — needed before drupal.org release. |

---

## Epic

### Epic: Make the Masonry Archive View CPU-Cheap Enough to Publish

**Priority:** P1
**Labels:** `epic`, `module`, `theme`, `performance`
**Goal:** Bring the steady-state CPU cost of loading `/archive/{tid}` down to
the level of a static image gallery. Zero VideoJS instances on the archive
page until the user clicks a teaser.

**Acceptance Criteria:**
- Loading `/archive/3` with 6 items causes **zero** VideoJS player instances to be created.
- No `<style class="vjs-styles-dimensions">` blocks appear in the rendered archive HTML.
- The archive page reaches `loadEventEnd` with the main thread idle within 1 s on a mid-range laptop.
- Clicking a teaser still opens the modal and plays the video correctly (no regression).
- A documented performance baseline is captured (Lighthouse + Chrome Perf trace) and a regression test guards it.
- The module is in a state we are willing to publish on drupal.org without embarrassment.

---

## Sub-Issues

### Sub-1: Stop initialising VideoJS on teaser/masonry facades entirely

**Priority:** P1
**Labels:** `module`, `performance`
**Depends On:** —

**Problem:**
`player.js` `Drupal.behaviors.videojsMediablockPlayerInit` runs an
`IntersectionObserver` over every `.videojs-lazy-facade` not inside a `.modal`
and calls `initPlayerFromFacade()` once it intersects. In the archive view,
the teaser facade is never the player the user actually uses — the modal
creates its own player on click. So this work is 100 % wasted.

**Solution:**
- Add an opt-out signal on the teaser facade. Two options:
  1. A `data-lazy-player-click-only="true"` attribute on the facade element, set by the teaser template (or by the theme's `view.theme` for masonry rows).
  2. A CSS-class check (`.masonry-item .videojs-lazy-facade`) inside `player.js`.
- Option 1 is preferred — it is explicit and template-driven.
- In `player.js`, when the facade has `data-lazy-player-click-only`, skip the IntersectionObserver path entirely. Wire only the click/keyboard handler. The click handler should still call `initPlayerFromFacade()` so the canonical entity page (where the teaser *is* the player) keeps working — or, better, in the masonry context the click is intercepted by `modal-viewer.js` and the facade player is never created.
- For the masonry case specifically, the cleanest outcome is: the teaser is a pure `<picture>` + `<button>`, and `modal-viewer.js` is the **only** thing that creates a VideoJS instance.

**Acceptance Criteria:**
- `/archive/3` HTML contains zero `<style class="vjs-styles-dimensions">` blocks.
- DevTools shows zero `videojs(...)` calls on archive page load.
- Clicking a teaser still opens the modal and plays the video.
- Direct entity page `/videojs-media/{id}` still initialises a player via the click handler.

---

### Sub-2: Reduce `IntersectionObserver` `rootMargin` and threshold for the canonical entity page

**Priority:** P2
**Labels:** `module`, `performance`
**Depends On:** Sub-1

**Problem:**
Even on the canonical entity page, `rootMargin: '200px'` is too aggressive —
it triggers initialisation before the user has any chance to scroll. With
Sub-1 in place, this only affects pages where a videojs_media entity is
embedded directly, but it is still wasteful.

**Solution:**
- Reduce `rootMargin` to `'0px'` (or remove entirely) and set `threshold: 0.1`.
- Document the trade-off in `player.js` comments.

**Acceptance Criteria:**
- Canonical entity page does not initialise the player until the facade is at least 10 % visible.

---

### Sub-3: Replace `data-poster-picture` HTML payload with a server-rendered `<template>` per row

**Priority:** P2
**Labels:** `theme`, `performance`
**Depends On:** —

**Problem:**
`view.theme` renders a full `<picture>` element with srcset for each row and
stuffs the escaped HTML into a `data-poster-picture` attribute. The browser
parses it once as an attribute string, the JS then reads `innerHTML = ...` to
re-parse it as DOM. Two parse passes per row.

**Solution:**
- Render the `<picture>` inside a `<template id="poster-picture-{nid}">` sibling to each `.masonry-item`.
- `modal-viewer.js` reads `template.content.cloneNode(true)` instead of `innerHTML = data-poster-picture`.
- Native one-pass parsing, smaller HTML, less escaping.

**Acceptance Criteria:**
- `data-poster-picture` attribute removed from HTML.
- Modal still shows the responsive `<picture>` overlay.
- Page weight drops by ~1–2 KB × N rows.

---

### Sub-4: Evaluate replacing Masonry.js with CSS columns / CSS Grid

**Priority:** P3
**Labels:** `theme`, `performance`
**Depends On:** —

**Problem:**
Masonry.js performs JS-driven layout on every resize and on every item
addition. CSS `column-count` / `column-width` and CSS Grid `masonry`
(behind a flag in some browsers, polyfilled with `grid-template-rows: masonry` future API) achieve the same visual result with **zero JS layout cost**.

**Solution:**
- Spike: rebuild the archive grid using CSS `column-count` with `break-inside: avoid` on items.
- Compare: visual fidelity, ordering (CSS columns flow top-to-bottom in each column, Masonry packs by height), accessibility (DOM order vs visual order).
- If acceptable, remove Masonry.js dependency entirely.

**Acceptance Criteria:**
- Decision documented (keep / replace / hybrid).
- If replaced, Masonry.js bundle removed from theme build.

---

### Sub-5: Establish a performance baseline + regression guard

**Priority:** P1
**Labels:** `module`, `theme`, `performance`, `test`
**Depends On:** Sub-1

**Problem:**
We have no objective measurement of the archive page's performance, so we
cannot prove the optimisations work or detect regressions before publishing
to drupal.org.

**Solution:**
- Capture a Lighthouse report (mobile + desktop) for `/archive/3` at three points:
  1. Pre-Sub-1 (current state — baseline of shame).
  2. Post-Sub-1 (target state).
  3. Post-Sub-3 + Sub-4 (final).
- Store the JSON reports under `tests/performance/archive-baseline-{date}.json`.
- Add a Playwright/Puppeteer test that loads `/archive/3` and asserts:
  - Zero `<style class="vjs-styles-dimensions">` elements in `<head>`.
  - Zero `.video-js.vjs-paused` elements (confirms no VideoJS init).
  - DOM `loadEventEnd` < a documented threshold.

**Acceptance Criteria:**
- Three Lighthouse reports committed.
- Regression test in CI passes on `master` and fails on a deliberately re-broken branch.

---

### Sub-6: Pre-publication checklist for drupal.org

**Priority:** P2
**Labels:** `module`, `documentation`
**Depends On:** Sub-1, Sub-5

**Problem:**
Publishing to drupal.org has its own quality bar (composer.json, hook_help,
schema, CS, automated tests, README screenshots). The performance story is
necessary but not sufficient.

**Solution:**
- Audit against [Drupal.org module review checklist](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/security-advisory-policy-coverage-policy).
- Ensure `phpcs --standard=Drupal,DrupalPractice` passes on the whole module.
- Ensure `phpstan` at level 5 passes.
- Ensure all tests run inside DDEV via `ddev phpunit`.
- README documents: what it is, how it differs from `media_entity_video`/`video_embed_field`, install steps, usage screenshot.
- CHANGELOG entry summarising the optimisation work above.

**Acceptance Criteria:**
- Checklist completed and committed.
- Module ready to submit as a sandbox project on drupal.org.

