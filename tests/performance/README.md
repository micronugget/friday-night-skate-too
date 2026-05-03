# Archive Page Performance Baselines

Lighthouse JSON reports for `/archive/3` captured at three milestone points
defined in issue [#110](https://github.com/leej3/fridaynightskate2/issues/110).

## Capture Points

| Label | Description |
|-------|-------------|
| `pre-sub1` | Baseline of shame — current state before any optimisation sub-tasks. |
| `post-sub1` | After Sub-1 (lazy VideoJS init / intersection-observer guard). |
| `post-sub3-sub4` | After Sub-3 + Sub-4 (final optimised state). |

## Running a Capture

Requires Node 20+ and Chrome on the host machine.

```bash
# From project root
CHROME_PATH=/usr/bin/google-chrome \
~/.nvm/versions/node/v22.17.0/bin/node \
  tests/performance/capture-lighthouse.mjs [label]
```

`label` defaults to `pre-sub1`. Reports are written to this directory as:

```
archive-baseline-{label}-{YYYY-MM-DD}-{mobile|desktop}.json
```

## Regression Guard

The Playwright spec at `tests/e2e/archive-performance-regression.spec.js`
asserts three conditions on every CI run:

1. **No `vjs-styles-dimensions`** `<style>` in `<head>` — VideoJS must not
   initialise on page load.
2. **No `.video-js.vjs-paused`** elements — no VideoJS player instances on
   initial load.
3. **`loadEventEnd` < 15 000 ms** — guards against gross load-time regressions.

Run the spec:

```bash
cd tests/e2e && npx playwright test archive-performance-regression.spec.js
```
