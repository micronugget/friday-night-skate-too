# Issues — VideoJS Media CPU Optimization

> Planning document created 2026-04-11.
> Addresses high CPU usage when playing video via the VideoJS Media module.

---

## Problem Statement

Playing a video through the VideoJS Media player causes sustained high CPU usage
(visible as CPU fan spin-up). Pausing the video does **not** bring relief, indicating
background processing continues even when playback is idle. This affects user experience
on desktops and accelerates battery drain on laptops/tablets.

### Root Causes Identified

| # | Cause | Impact |
|---|-------|--------|
| 1 | **`@videojs/http-streaming` (VHS) always loaded** | ~250 KB parsed JS + WASM worker threads run continuously, even for simple MP4 or YouTube sources that don't need adaptive streaming |
| 2 | **Duplicate player initialization** | `data-setup` attribute on the `<video>` tag triggers Video.js auto-init, then `videojsMediablockPlayerInit` behavior initializes the *same* element again via `window.videojs()` — double config merge, double VHS probe |
| 3 | **VHS keeps polling/buffering when paused** | http-streaming continues segment fetching and bandwidth estimation even in paused state; no idle/suspend hook |
| 4 | **No lazy initialization** | All players on the page initialize immediately on `attach()`, even if off-screen or inside a closed modal |
| 5 | **No player disposal on modal close** | When the archive modal is dismissed, the VideoJS instance stays alive in memory with all event listeners, timers, and VHS workers running |
| 6 | **MutationObserver with `subtree: true`** | Fallback observer watches the entire subtree of each `<video>` element for attribute/child changes — expensive on complex DOMs |
| 7 | **No `preload="none"` on video elements** | Browser begins downloading/buffering video data immediately on element creation |

---

## Epic

### Epic: Reduce VideoJS Media CPU Footprint

**Priority:** P2
**Labels:** `module`, `performance`
**Goal:** Bring idle (paused) CPU usage to near-zero and reduce active playback overhead by eliminating unnecessary library loading, duplicate initialization, and background processing.

**Acceptance Criteria:**
- Pausing a video returns CPU to baseline within 2 seconds
- YouTube and simple MP4 sources do not load the http-streaming library
- Players inside modals are created on open and disposed on close
- No duplicate Video.js initialization occurs
- `preload="none"` is the default for all video elements

---

## Sub-Issues

### Sub-1: Conditionally load `@videojs/http-streaming` only for adaptive streams

**Priority:** P2
**Labels:** `module`, `performance`
**Depends On:** —

**Problem:**
`videojs_media.libraries.yml` unconditionally includes `videojs-http-streaming.js`
(~250 KB). This library spawns Web Workers for segment parsing and bandwidth estimation.
For YouTube embeds and direct MP4/WebM files, it is entirely unnecessary but still
initializes and runs background threads.

**Solution:**
1. Split the `videojs-player` library into a base library (`videojs-player`) and an
   adaptive-streaming add-on library (`videojs-player-hls`).
2. In the Twig template or preprocess, attach `videojs-player-hls` **only** when the
   source type is `application/vnd.apple.mpegurl` or `application/dash+xml`.
3. Remove `@videojs/http-streaming` from the base `videojs-player` library definition.

**Acceptance Criteria:**
- YouTube and MP4 playback does not load `videojs-http-streaming.js`
- HLS/DASH sources still work correctly with adaptive streaming
- No JavaScript errors in console for any media type

---

### Sub-2: Eliminate duplicate player initialization (remove `data-setup` or JS init)

**Priority:** P2
**Labels:** `module`, `performance`
**Depends On:** —

**Problem:**
The `player.twig` template outputs a `data-setup='…'` attribute on the `<video>` tag.
Video.js auto-detects this attribute and initializes the player. Then
`Drupal.behaviors.videojsMediablockPlayerInit` calls `window.videojs(videoElement, {…})`
on the same element, causing a second initialization pass. This results in duplicate
config merging, double VHS probing, and wasted CPU cycles.

**Solution:**
Remove the `data-setup` attribute from `player.twig`. Let the Drupal behavior be the
single initialization path. Move any config from `data-setup` into the JS `videojs()`
options object in `player.js`.

**Acceptance Criteria:**
- Only one `videojs()` initialization call per player element
- All player options (techOrder, fluid, controls, youtube config) still applied correctly
- Console shows no "Player already initialized" warnings

---

### Sub-3: Suspend VHS when player is paused

**Priority:** P2
**Labels:** `module`, `performance`
**Depends On:** Sub-1

**Problem:**
When http-streaming (VHS) is active and the user pauses the video, VHS continues
background segment fetching, bandwidth estimation, and buffer management. This keeps
Web Workers busy and CPU elevated even though no frames are being rendered.

**Solution:**
Add `pause` and `play` event handlers that call the VHS API to suspend/resume:
```js
player.on('pause', () => {
  const vhs = player.tech(true)?.vhs;
  if (vhs && vhs.masterPlaylistController_) {
    vhs.masterPlaylistController_.mainSegmentLoader_.pause();
  }
});
player.on('play', () => {
  const vhs = player.tech(true)?.vhs;
  if (vhs && vhs.masterPlaylistController_) {
    vhs.masterPlaylistController_.mainSegmentLoader_.load();
  }
});
```

**Acceptance Criteria:**
- CPU drops to baseline within 2 seconds of pausing an HLS/DASH stream
- Resuming playback works without stutter or re-buffering delay > 1 s
- No effect on YouTube or direct MP4 sources

---

### Sub-4: Lazy-initialize players (defer until visible or modal open)

**Priority:** P2
**Labels:** `module`, `performance`
**Depends On:** Sub-2

**Problem:**
`videojsMediablockPlayerInit` initializes every `<video>` element on page load via
`once()`. On archive pages with multiple thumbnails, this creates multiple full VideoJS
instances (each with plugins, event listeners, and potentially VHS workers) even though
only one will be viewed at a time.

**Solution:**
1. For players inside modals: do **not** initialize on page load. Initialize when the
   modal opens (listen for the modal `show` event), passing the video element to
   `window.videojs()` at that point.
2. For players on the main page: use `IntersectionObserver` to defer initialization
   until the element is within the viewport (or about to enter it with a generous
   `rootMargin`).
3. Replace the `<video>` tag with a static poster image + play button overlay until
   initialization is triggered (facade pattern).

**Acceptance Criteria:**
- Page load creates zero VideoJS instances on archive listing pages
- Clicking a thumbnail / opening a modal initializes exactly one player
- Scrolling a player into view on a non-modal page triggers initialization
- No visible delay > 300 ms between user action and playback start

---

### Sub-5: Dispose player on modal close

**Priority:** P2
**Labels:** `module`, `performance`
**Depends On:** Sub-4

**Problem:**
When the archive modal (Swiper lightbox) is closed, the VideoJS player instance
remains alive. Its event listeners, timers, VHS workers, and DOM nodes persist. If the
user opens several modals in a session, multiple orphaned players accumulate.

**Solution:**
Listen for the modal `hide`/`close` event. On close:
1. Call `player.pause()` then `player.dispose()`.
2. Remove the player from `Drupal.behaviors.videojsMediablockPlayer.allPlayers`.
3. Restore the original `<video>` element markup so re-opening the modal can
   re-initialize a fresh player (per Sub-4).

**Acceptance Criteria:**
- After closing a modal, no VideoJS player instances remain for that modal
- `allPlayers` array length matches the number of currently open/visible players
- Memory usage does not grow with repeated open/close cycles (verify via DevTools)

---

### Sub-6: Add `preload="none"` to video elements

**Priority:** P3
**Labels:** `module`, `performance`
**Depends On:** —

**Problem:**
The `<video>` element in `player.twig` has no `preload` attribute, so browsers default
to `preload="metadata"` or even `preload="auto"`, triggering immediate network requests
and decode work for every video on the page.

**Solution:**
Add `preload="none"` to the `<video>` tag in `player.twig`. This prevents any data
fetching until the user explicitly plays or until JS initialization requests it.

**Acceptance Criteria:**
- Network tab shows zero video data requests on page load (before user interaction)
- Playback still starts promptly when user clicks play
- Poster image still displays correctly

---

### Sub-7: Scope MutationObserver and add timeout guard

**Priority:** P3
**Labels:** `module`, `performance`
**Depends On:** Sub-2, Sub-4

**Problem:**
The fallback `MutationObserver` in `attach()` uses `{ attributes: true, childList: true,
subtree: true }` which fires on every DOM mutation within the video element's subtree.
Video.js itself makes many DOM changes during initialization, causing a cascade of
observer callbacks.

**Solution:**
1. Narrow the observer to `{ attributes: true, attributeFilter: ['class'] }` — the
   only signal needed is Video.js adding its classes to the element.
2. Add a `setTimeout` guard (e.g., 5 seconds) that disconnects the observer if the
   player hasn't initialized, preventing indefinite observation.
3. With lazy init (Sub-4), this observer may become unnecessary — evaluate removal.

**Acceptance Criteria:**
- Observer fires ≤ 5 times per player element during initialization
- Observer auto-disconnects after timeout if player never initializes
- No regression in late-initializing player detection

---

## Implementation Order

```
Sub-6 (preload=none)          — quick win, no dependencies
Sub-2 (remove data-setup)     — eliminates double init
Sub-1 (conditional VHS)       — biggest CPU win
Sub-3 (suspend VHS on pause)  — depends on Sub-1
Sub-4 (lazy init)             — depends on Sub-2
Sub-5 (dispose on close)      — depends on Sub-4
Sub-7 (scope observer)        — depends on Sub-2, Sub-4
```

---

## Validation

After all sub-issues are resolved, validate with:

1. **Chrome DevTools → Performance tab:** Record a session of open thumbnail → play 5 s → pause → wait 10 s → close modal. CPU flame chart should show near-zero activity during pause and after close.
2. **Chrome Task Manager (Shift+Esc):** GPU Process and tab CPU should drop below 5% when paused.
3. **`about:tracing` (advanced):** Confirm no Web Worker activity during pause for MP4/YouTube sources.
4. **Memory:** Heap snapshot before and after 10 open/close cycles should show no significant growth.
