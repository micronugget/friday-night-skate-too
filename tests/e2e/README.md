# E2E Tests — Archive Modal & Masonry

End-to-end browser tests for the archive masonry grid and cinematic modal viewer, using [Playwright](https://playwright.dev/).

These tests verify **client-side JavaScript behaviour** that PHPUnit cannot cover:

- Masonry layout applies `position: absolute` to grid items
- Responsive column count changes with viewport width
- Layout recalculates on window resize
- Clicking a thumbnail opens the cinematic modal with a video element
- Escape key and close button dismiss the modal
- Arrow keys (←/→) navigate between items in the modal

## Prerequisites

- **DDEV running** — `ddev start` from the project root
- **Archive content** — at least one `archive_media` node linked to a Skate Date taxonomy term
- **Node.js** — available inside DDEV (ships with DDEV by default)

## Setup (one-time)

```bash
# Install Playwright and its dependencies inside DDEV:
ddev exec "cd tests/e2e && npm install"
ddev exec "cd tests/e2e && npx playwright install --with-deps chromium"
```

## Running Tests

### Inside DDEV (recommended)

```bash
# Run all E2E tests:
ddev exec "cd tests/e2e && npx playwright test"

# Run with verbose output:
ddev exec "cd tests/e2e && npx playwright test --reporter=line"

# Run a specific test file:
ddev exec "cd tests/e2e && npx playwright test archive-modal.spec.js"
```

### From the host machine

If you have Playwright browsers installed locally:

```bash
cd tests/e2e
npm install
npx playwright install chromium
npx playwright test
```

### Custom base URL

Override the default DDEV URL with an environment variable:

```bash
PLAYWRIGHT_BASE_URL=https://my-site.example.com npx playwright test
```

## Test Structure

```
tests/e2e/
├── README.md                  # This file
├── package.json               # Playwright dependency
├── playwright.config.js       # Playwright configuration
└── archive-modal.spec.js      # E2E test suite
```

## CI Integration

To run in CI, ensure:

1. DDEV (or equivalent) is running and accessible
2. Playwright browsers are installed: `npx playwright install --with-deps chromium`
3. Set `PLAYWRIGHT_BASE_URL` to the CI site URL
4. Run: `cd tests/e2e && npx playwright test`
