# Archive Views with Masonry Grid - Test Suite

This document describes how to run the comprehensive test suite for the Archive Views with Masonry Grid feature.

## Test Overview

The test suite includes:

1. **PHPUnit Kernel Test** - Tests Views configuration and behavior
2. **PHPUnit Functional Test** - Tests archive pages end-to-end with browser simulation
3. **Nightwatch.js Browser Test** - Tests Masonry layout in real browsers
4. **JavaScript Unit Test** - Tests the archive-masonry.js module in isolation

## Prerequisites

Before running tests, ensure DDEV is running:

```bash
ddev start
```

## PHPUnit Tests

### Kernel Test: ArchiveByDateViewTest

Tests the Views configuration without a full Drupal installation.

**Location**: `web/modules/custom/fns_archive/tests/src/Kernel/ArchiveByDateViewTest.php`

**What it tests**:
- View exists and is properly configured
- Contextual filter for taxonomy term
- Content type and moderation state filters
- Row plugin configuration (entity:node with thumbnail view mode)
- Sort order (timestamp descending)
- Access permissions
- Pager configuration
- Empty state handling
- Masonry row classes

**Run it**:
```bash
# Run all kernel tests in fns_archive module
ddev phpunit web/modules/custom/fns_archive/tests/src/Kernel/

# Run specific test
ddev phpunit web/modules/custom/fns_archive/tests/src/Kernel/ArchiveByDateViewTest.php

# Run with verbose output
ddev phpunit --verbose web/modules/custom/fns_archive/tests/src/Kernel/ArchiveByDateViewTest.php

# Run specific test method
ddev phpunit --filter testViewExists web/modules/custom/fns_archive/tests/src/Kernel/ArchiveByDateViewTest.php
```

### Functional Test: ArchiveByDatePageTest

Tests the archive pages with full Drupal installation and browser simulation.

**Location**: `web/modules/custom/fns_archive/tests/src/Functional/ArchiveByDatePageTest.php`

**What it tests**:
- Archive page accessibility
- Content display and filtering
- Masonry container and item classes
- Library attachment
- Empty state display
- Published and moderation state filters
- Sort order in rendered HTML
- Pagination functionality
- Term filtering (only content from specified term)
- Anonymous user access
- Thumbnail view mode rendering

**Run it**:
```bash
# Run all functional tests in fns_archive module
ddev phpunit web/modules/custom/fns_archive/tests/src/Functional/

# Run specific test
ddev phpunit web/modules/custom/fns_archive/tests/src/Functional/ArchiveByDatePageTest.php

# Run with verbose output
ddev phpunit --verbose web/modules/custom/fns_archive/tests/src/Functional/ArchiveByDatePageTest.php

# Run specific test method
ddev phpunit --filter testMasonryContainerClass web/modules/custom/fns_archive/tests/src/Functional/ArchiveByDatePageTest.php
```

### Run All PHPUnit Tests

```bash
# Run all fns_archive tests (Kernel + Functional)
ddev phpunit web/modules/custom/fns_archive/tests/

# Run with code coverage (requires xdebug)
ddev phpunit --coverage-html coverage web/modules/custom/fns_archive/tests/
```

## Nightwatch.js Browser Tests

Tests the Masonry layout in real browsers (Chrome, Firefox, etc.).

**Location**: `web/themes/custom/fridaynightskate/tests/nightwatch/archive-masonry.test.js`

**What it tests**:
- Archive page loads successfully
- Masonry grid initialization
- Masonry items are present and positioned
- Responsive breakpoints (xs, sm, md, lg, xl)
- Lazy loading images with IntersectionObserver
- Hover effects
- Metadata overlay icons
- Layout reflow on window resize
- Masonry and imagesLoaded libraries loaded
- Empty state display
- Pagination
- Drupal AJAX integration
- Masonry configuration (gutter, percentPosition, transition)

**Run it**:
```bash
# Run from theme directory
cd web/themes/custom/fridaynightskate

# Run all nightwatch tests
ddev yarn test:nightwatch

# Run specific test
ddev yarn test:nightwatch tests/nightwatch/archive-masonry.test.js

# Run with specific browser
ddev yarn test:nightwatch --env chrome tests/nightwatch/archive-masonry.test.js

# Run with verbose output
ddev yarn test:nightwatch --verbose tests/nightwatch/archive-masonry.test.js
```

**Note**: Ensure Nightwatch is configured in `package.json` and `nightwatch.conf.js` exists.

## JavaScript Unit Tests

Tests the archive-masonry.js module in isolation using Jest.

**Location**: `web/themes/custom/fridaynightskate/tests/js/archive-masonry.test.js`

**What it tests**:
- Drupal.behaviors.archiveMasonry is defined
- attach function exists and works
- Column count calculation for all breakpoints
- Masonry initialization with correct options
- IntersectionObserver setup for lazy loading
- Resize event debouncing (250ms)
- Drupal AJAX integration
- ImagesLoaded integration
- Grid configuration (selectors, gutter, transitions)
- Error handling (missing elements)
- Bootstrap 5 breakpoint alignment

**Run it**:
```bash
# Run from theme directory
cd web/themes/custom/fridaynightskate

# Run all Jest tests
ddev yarn test

# Run specific test file
ddev yarn test archive-masonry.test.js

# Run with coverage
ddev yarn test --coverage

# Run in watch mode
ddev yarn test --watch
```

**Note**: Ensure Jest is configured in `package.json`:
```json
{
  "scripts": {
    "test": "jest",
    "test:watch": "jest --watch",
    "test:coverage": "jest --coverage"
  },
  "devDependencies": {
    "jest": "^29.0.0",
    "@babel/preset-env": "^7.0.0",
    "babel-jest": "^29.0.0"
  }
}
```

## Test Groups

### Run All Tests

```bash
# PHPUnit tests
ddev phpunit web/modules/custom/fns_archive/tests/

# Nightwatch tests
cd web/themes/custom/fridaynightskate && ddev yarn test:nightwatch

# Jest tests
cd web/themes/custom/fridaynightskate && ddev yarn test
```

### Run Tests by Tag/Group

```bash
# Run only archive-related tests
ddev phpunit --group fns_archive

# Run only views tests
ddev phpunit --group views

# Run Nightwatch tests by tag
ddev yarn test:nightwatch --tag archive
```

## Continuous Integration

These tests can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions workflow
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup DDEV
        run: |
          ddev start
          ddev composer install
      - name: Run PHPUnit Tests
        run: ddev phpunit web/modules/custom/fns_archive/tests/

  nightwatch:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup DDEV
        run: |
          ddev start
          ddev composer install
      - name: Install Theme Dependencies
        run: cd web/themes/custom/fridaynightskate && ddev yarn install
      - name: Run Nightwatch Tests
        run: cd web/themes/custom/fridaynightskate && ddev yarn test:nightwatch

  jest:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Install Theme Dependencies
        run: cd web/themes/custom/fridaynightskate && ddev yarn install
      - name: Run Jest Tests
        run: cd web/themes/custom/fridaynightskate && ddev yarn test
```

## Test Data Setup

Some tests require test data. When Drush is available, you can set up test data:

```bash
# Create test taxonomy terms
ddev drush term:create skate_dates "January 2025"
ddev drush term:create skate_dates "February 2025"

# Create test archive media nodes
ddev drush node:create archive_media --title="Test Media 1" --field_skate_date=1 --moderation_state=published

# Clear cache
ddev drush cr
```

## Troubleshooting

### PHPUnit Tests Fail

1. Ensure DDEV is running: `ddev start`
2. Clear cache: `ddev drush cr`
3. Rebuild test database: `ddev drush sql:drop && ddev drush site:install`
4. Check module dependencies are installed

### Nightwatch Tests Fail

1. Ensure ChromeDriver/GeckoDriver is installed
2. Check Nightwatch configuration in `nightwatch.conf.js`
3. Verify test data exists on the site
4. Check browser version compatibility

### Jest Tests Fail

1. Ensure node_modules are installed: `ddev yarn install`
2. Check Jest configuration in `package.json`
3. Verify Babel is configured for ES6+ support
4. Clear Jest cache: `ddev yarn test --clearCache`

## Code Coverage

Generate code coverage reports:

```bash
# PHPUnit coverage (requires xdebug)
ddev phpunit --coverage-html coverage web/modules/custom/fns_archive/tests/
open coverage/index.html

# Jest coverage
cd web/themes/custom/fridaynightskate
ddev yarn test --coverage
open coverage/lcov-report/index.html
```

## Test Maintenance

When updating the Archive Views feature:

1. Update Kernel tests if Views configuration changes
2. Update Functional tests if page structure or behavior changes
3. Update Nightwatch tests if visual layout or interactions change
4. Update Jest tests if JavaScript logic changes
5. Run all tests to ensure no regressions
6. Update this documentation if test commands change

## References

- [Drupal Testing Documentation](https://www.drupal.org/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/)
- [Nightwatch.js Documentation](https://nightwatchjs.org/)
- [Jest Documentation](https://jestjs.io/)
- [ARCHIVE_VIEWS_ARCHITECTURE.md](../../../ARCHIVE_VIEWS_ARCHITECTURE.md)
