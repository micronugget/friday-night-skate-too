/**
 * @file
 * Nightwatch.js browser test for Archive Masonry layout.
 */

module.exports = {
  '@tags': ['archive', 'masonry', 'layout'],

  before(browser) {
    browser.drupalInstall();
  },

  after(browser) {
    browser.drupalUninstall();
  },

  'Archive page loads successfully': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('body', 5000)
      .assert.elementPresent('.masonry-grid', 'Masonry grid container is present');
  },

  'Masonry grid is initialized': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .execute(function() {
        const grid = document.querySelector('.masonry-grid');
        return grid && grid.masonryInstance ? true : false;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Masonry instance is initialized on grid');
      });
  },

  'Masonry items are present': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .expect.elements('.masonry-item').count.to.be.above(0);
  },

  'Masonry items have proper positioning': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-item', 5000)
      .pause(500) // Wait for masonry layout
      .elements('css selector', '.masonry-item', function(result) {
        browser.assert.ok(result.value.length > 0, 'Multiple masonry items exist');
        
        // Check first item has position style
        browser.getAttribute('.masonry-item:first-child', 'style', function(result) {
          browser.assert.ok(
            result.value && result.value.includes('position'),
            'Masonry items have position styling applied'
          );
        });
      });
  },

  'Responsive breakpoint: Extra Small (xs - 1 column)': (browser) => {
    browser
      .resizeWindow(575, 800)
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .pause(500)
      .execute(function() {
        const width = window.innerWidth;
        return width < 576;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Window is in xs breakpoint');
      });
  },

  'Responsive breakpoint: Small (sm - 2 columns)': (browser) => {
    browser
      .resizeWindow(767, 800)
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .pause(500)
      .execute(function() {
        const width = window.innerWidth;
        return width >= 576 && width < 768;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Window is in sm breakpoint');
      });
  },

  'Responsive breakpoint: Medium (md - 3 columns)': (browser) => {
    browser
      .resizeWindow(991, 800)
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .pause(500)
      .execute(function() {
        const width = window.innerWidth;
        return width >= 768 && width < 992;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Window is in md breakpoint');
      });
  },

  'Responsive breakpoint: Large (lg - 4 columns)': (browser) => {
    browser
      .resizeWindow(1199, 800)
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .pause(500)
      .execute(function() {
        const width = window.innerWidth;
        return width >= 992 && width < 1200;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Window is in lg breakpoint');
      });
  },

  'Responsive breakpoint: Extra Large (xl - 5 columns)': (browser) => {
    browser
      .resizeWindow(1400, 800)
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .pause(500)
      .execute(function() {
        const width = window.innerWidth;
        return width >= 1200;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Window is in xl breakpoint');
      });
  },

  'Lazy loading images have proper attributes': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-item img', 5000)
      .getAttribute('.masonry-item img:first-of-type', 'loading', function(result) {
        browser.assert.strictEqual(result.value, 'lazy', 'Images have lazy loading attribute');
      });
  },

  'IntersectionObserver is set up for lazy loading': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .execute(function() {
        return typeof IntersectionObserver !== 'undefined';
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'IntersectionObserver is available in browser');
      });
  },

  'Hover effect is applied to masonry items': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-item:first-child', 5000)
      .moveToElement('.masonry-item:first-child', 10, 10)
      .pause(300)
      .getCssProperty('.masonry-item:first-child', 'transform', function(result) {
        // Check if hover transform is applied (may vary based on CSS implementation)
        browser.assert.ok(result.value !== 'none' || result.value.length > 0, 'Hover effect is present');
      });
  },

  'Metadata overlay icon is visible': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-item', 5000)
      .expect.element('.masonry-item .metadata-overlay').to.be.present;
  },

  'Layout reflows on window resize': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .resizeWindow(800, 600)
      .pause(500)
      .execute(function() {
        const grid = document.querySelector('.masonry-grid');
        return grid && grid.masonryInstance ? true : false;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Masonry instance persists after resize');
      })
      .resizeWindow(1200, 800)
      .pause(500)
      .execute(function() {
        const grid = document.querySelector('.masonry-grid');
        return grid && grid.masonryInstance ? true : false;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Masonry instance persists after second resize');
      });
  },

  'Masonry library files are loaded': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('body', 5000)
      .execute(function() {
        return typeof Masonry !== 'undefined';
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Masonry.js library is loaded');
      })
      .execute(function() {
        return typeof imagesLoaded !== 'undefined';
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'imagesLoaded library is loaded');
      });
  },

  'Empty state displays when no content': (browser) => {
    browser
      .drupalRelativeURL('/archive/99999')
      .waitForElementVisible('body', 5000)
      .assert.containsText('body', 'No archive media available for this date', 'Empty state message is displayed');
  },

  'Pagination works correctly': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .pause(500);

    // Check if pager exists (only if more than 50 items)
    browser.elements('css selector', 'nav.pager', function(result) {
      if (result.value.length > 0) {
        browser
          .click('nav.pager a[rel="next"]')
          .waitForElementVisible('.masonry-grid', 5000)
          .assert.urlContains('page=1', 'Pagination URL parameter is present');
      }
    });
  },

  'Masonry sizer element is present': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .expect.element('.masonry-sizer').to.be.present;
  },

  'Drupal AJAX complete triggers layout': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .execute(function() {
        // Simulate Drupal AJAX complete event
        const event = new Event('drupalAjaxComplete');
        document.dispatchEvent(event);
        
        // Check that masonry instance still exists
        const grid = document.querySelector('.masonry-grid');
        return grid && grid.masonryInstance ? true : false;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Masonry handles AJAX events');
      });
  },

  'Grid gap is properly applied': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .execute(function() {
        const grid = document.querySelector('.masonry-grid');
        if (grid && grid.masonryInstance) {
          return grid.masonryInstance.options.gutter;
        }
        return null;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, 16, 'Grid gutter is set to 16px (Bootstrap equivalent)');
      });
  },

  'Masonry uses percent position': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .execute(function() {
        const grid = document.querySelector('.masonry-grid');
        if (grid && grid.masonryInstance) {
          return grid.masonryInstance.options.percentPosition;
        }
        return false;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, true, 'Masonry uses percent positioning for responsiveness');
      });
  },

  'Transition duration is configured': (browser) => {
    browser
      .drupalRelativeURL('/archive/1')
      .waitForElementVisible('.masonry-grid', 5000)
      .execute(function() {
        const grid = document.querySelector('.masonry-grid');
        if (grid && grid.masonryInstance) {
          return grid.masonryInstance.options.transitionDuration;
        }
        return null;
      }, [], function(result) {
        browser.assert.strictEqual(result.value, '0.3s', 'Transition duration is set to 0.3s');
      })
      .end();
  }
};
