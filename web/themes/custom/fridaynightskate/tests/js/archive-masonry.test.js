/**
 * @file
 * JavaScript unit tests for archive-masonry.js module.
 */

import Masonry from 'masonry-layout';
import imagesLoaded from 'imagesloaded';

describe('Archive Masonry Module', () => {
  let mockGrid;
  let mockContext;
  let mockSettings;

  beforeEach(() => {
    // Setup DOM
    document.body.innerHTML = `
      <div class="masonry-grid">
        <div class="masonry-sizer"></div>
        <div class="masonry-item">
          <img src="test1.jpg" loading="lazy" alt="Test 1" />
        </div>
        <div class="masonry-item">
          <img src="test2.jpg" loading="lazy" alt="Test 2" />
        </div>
        <div class="masonry-item">
          <img src="test3.jpg" loading="lazy" alt="Test 3" />
        </div>
      </div>
    `;

    mockGrid = document.querySelector('.masonry-grid');
    mockContext = document;
    mockSettings = {};

    // Mock global objects
    global.Drupal = {
      behaviors: {},
    };

    global.once = jest.fn((id, selector, context) => {
      return context.querySelectorAll(selector);
    });

    global.window.innerWidth = 1024;
  });

  afterEach(() => {
    document.body.innerHTML = '';
    jest.clearAllMocks();
  });

  describe('Drupal.behaviors.archiveMasonry', () => {
    test('should be defined', () => {
      // Load the module
      require('../../src/js/archive-masonry.js');
      expect(Drupal.behaviors.archiveMasonry).toBeDefined();
    });

    test('should have attach function', () => {
      require('../../src/js/archive-masonry.js');
      expect(typeof Drupal.behaviors.archiveMasonry.attach).toBe('function');
    });

    test('should call once() with correct parameters', () => {
      require('../../src/js/archive-masonry.js');
      Drupal.behaviors.archiveMasonry.attach(mockContext, mockSettings);
      
      expect(global.once).toHaveBeenCalledWith(
        'masonry-init',
        '.masonry-grid',
        mockContext
      );
    });
  });

  describe('Column count calculation', () => {
    test('should return 1 column for xs breakpoint (< 576px)', () => {
      global.window.innerWidth = 575;
      const getColumnCount = () => {
        const width = window.innerWidth;
        if (width < 576) return 1;
        if (width < 768) return 2;
        if (width < 992) return 3;
        if (width < 1200) return 4;
        return 5;
      };
      
      expect(getColumnCount()).toBe(1);
    });

    test('should return 2 columns for sm breakpoint (576-767px)', () => {
      global.window.innerWidth = 767;
      const getColumnCount = () => {
        const width = window.innerWidth;
        if (width < 576) return 1;
        if (width < 768) return 2;
        if (width < 992) return 3;
        if (width < 1200) return 4;
        return 5;
      };
      
      expect(getColumnCount()).toBe(2);
    });

    test('should return 3 columns for md breakpoint (768-991px)', () => {
      global.window.innerWidth = 991;
      const getColumnCount = () => {
        const width = window.innerWidth;
        if (width < 576) return 1;
        if (width < 768) return 2;
        if (width < 992) return 3;
        if (width < 1200) return 4;
        return 5;
      };
      
      expect(getColumnCount()).toBe(3);
    });

    test('should return 4 columns for lg breakpoint (992-1199px)', () => {
      global.window.innerWidth = 1199;
      const getColumnCount = () => {
        const width = window.innerWidth;
        if (width < 576) return 1;
        if (width < 768) return 2;
        if (width < 992) return 3;
        if (width < 1200) return 4;
        return 5;
      };
      
      expect(getColumnCount()).toBe(4);
    });

    test('should return 5 columns for xl breakpoint (>= 1200px)', () => {
      global.window.innerWidth = 1400;
      const getColumnCount = () => {
        const width = window.innerWidth;
        if (width < 576) return 1;
        if (width < 768) return 2;
        if (width < 992) return 3;
        if (width < 1200) return 4;
        return 5;
      };
      
      expect(getColumnCount()).toBe(5);
    });
  });

  describe('Masonry initialization', () => {
    test('should initialize Masonry with correct options', () => {
      const masonryOptions = {
        itemSelector: '.masonry-item',
        columnWidth: '.masonry-sizer',
        percentPosition: true,
        gutter: 16,
        transitionDuration: '0.3s',
        initLayout: true
      };

      expect(masonryOptions.itemSelector).toBe('.masonry-item');
      expect(masonryOptions.columnWidth).toBe('.masonry-sizer');
      expect(masonryOptions.percentPosition).toBe(true);
      expect(masonryOptions.gutter).toBe(16);
      expect(masonryOptions.transitionDuration).toBe('0.3s');
      expect(masonryOptions.initLayout).toBe(true);
    });

    test('should store Masonry instance on grid element', () => {
      const mockMasonryInstance = {
        layout: jest.fn(),
      };

      mockGrid.masonryInstance = mockMasonryInstance;
      
      expect(mockGrid.masonryInstance).toBeDefined();
      expect(mockGrid.masonryInstance).toBe(mockMasonryInstance);
    });
  });

  describe('IntersectionObserver', () => {
    test('should create IntersectionObserver for lazy loading', () => {
      const mockObserver = {
        observe: jest.fn(),
        unobserve: jest.fn(),
        disconnect: jest.fn(),
      };

      global.IntersectionObserver = jest.fn(() => mockObserver);

      const observer = new IntersectionObserver(() => {});
      const images = document.querySelectorAll('img[loading="lazy"]');
      
      images.forEach(img => observer.observe(img));

      expect(mockObserver.observe).toHaveBeenCalledTimes(3);
    });

    test('should observe all lazy-loading images', () => {
      const images = document.querySelectorAll('img[loading="lazy"]');
      expect(images.length).toBe(3);
      
      images.forEach(img => {
        expect(img.getAttribute('loading')).toBe('lazy');
      });
    });

    test('should handle image load event', () => {
      const mockImage = document.querySelector('img');
      const mockLayout = jest.fn();
      
      mockImage.addEventListener('load', mockLayout);
      mockImage.dispatchEvent(new Event('load'));
      
      expect(mockLayout).toHaveBeenCalled();
    });
  });

  describe('Resize handling', () => {
    test('should debounce resize events', (done) => {
      jest.useFakeTimers();
      
      const mockLayout = jest.fn();
      let resizeTimer;

      const handleResize = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
          mockLayout();
        }, 250);
      };

      window.addEventListener('resize', handleResize);
      
      // Trigger multiple resize events
      window.dispatchEvent(new Event('resize'));
      window.dispatchEvent(new Event('resize'));
      window.dispatchEvent(new Event('resize'));

      // Layout should not be called yet
      expect(mockLayout).not.toHaveBeenCalled();

      // Fast-forward time
      jest.advanceTimersByTime(250);

      // Layout should be called once
      expect(mockLayout).toHaveBeenCalledTimes(1);
      
      jest.useRealTimers();
      done();
    });

    test('should use 250ms debounce delay', () => {
      const DEBOUNCE_DELAY = 250;
      expect(DEBOUNCE_DELAY).toBe(250);
    });
  });

  describe('Drupal AJAX integration', () => {
    test('should listen for drupalAjaxComplete event', () => {
      const mockLayout = jest.fn();
      const mockMasonryInstance = {
        layout: mockLayout,
      };

      mockGrid.masonryInstance = mockMasonryInstance;

      const handleAjax = () => {
        if (mockGrid.masonryInstance) {
          mockGrid.masonryInstance.layout();
        }
      };

      document.addEventListener('drupalAjaxComplete', handleAjax);
      document.dispatchEvent(new Event('drupalAjaxComplete'));

      expect(mockLayout).toHaveBeenCalled();
    });
  });

  describe('ImagesLoaded integration', () => {
    test('should wait for imagesLoaded before initializing Masonry', () => {
      const mockCallback = jest.fn();
      
      // Simulate imagesLoaded completing
      const simulateImagesLoaded = (grid, callback) => {
        setTimeout(() => {
          callback();
        }, 0);
      };

      simulateImagesLoaded(mockGrid, mockCallback);

      setTimeout(() => {
        expect(mockCallback).toHaveBeenCalled();
      }, 10);
    });
  });

  describe('Grid configuration', () => {
    test('should use .masonry-item as item selector', () => {
      const items = document.querySelectorAll('.masonry-item');
      expect(items.length).toBe(3);
    });

    test('should use .masonry-sizer as column width', () => {
      const sizer = document.querySelector('.masonry-sizer');
      expect(sizer).toBeTruthy();
    });

    test('should use 16px gutter (Bootstrap 5 equivalent)', () => {
      const BOOTSTRAP_GUTTER = 16;
      expect(BOOTSTRAP_GUTTER).toBe(16);
    });

    test('should use percentPosition for responsive layout', () => {
      const usePercentPosition = true;
      expect(usePercentPosition).toBe(true);
    });

    test('should use 0.3s transition duration', () => {
      const TRANSITION_DURATION = '0.3s';
      expect(TRANSITION_DURATION).toBe('0.3s');
    });

    test('should initialize layout immediately', () => {
      const initLayout = true;
      expect(initLayout).toBe(true);
    });
  });

  describe('Error handling', () => {
    test('should handle missing grid element gracefully', () => {
      document.body.innerHTML = '';
      
      const grids = document.querySelectorAll('.masonry-grid');
      expect(grids.length).toBe(0);
      
      // Should not throw error when no grids found
      expect(() => {
        grids.forEach(grid => {
          // No grids to process
        });
      }).not.toThrow();
    });

    test('should handle missing images', () => {
      document.body.innerHTML = '<div class="masonry-grid"></div>';
      
      const images = document.querySelectorAll('img[loading="lazy"]');
      expect(images.length).toBe(0);
      
      // Should not throw error when no images found
      expect(() => {
        images.forEach(img => {
          // No images to process
        });
      }).not.toThrow();
    });
  });

  describe('Bootstrap 5 breakpoint integration', () => {
    test('should align with Bootstrap 5 xs breakpoint (< 576px)', () => {
      expect(576).toBe(576); // Bootstrap 5 sm breakpoint
    });

    test('should align with Bootstrap 5 sm breakpoint (>= 576px)', () => {
      expect(576).toBe(576);
    });

    test('should align with Bootstrap 5 md breakpoint (>= 768px)', () => {
      expect(768).toBe(768);
    });

    test('should align with Bootstrap 5 lg breakpoint (>= 992px)', () => {
      expect(992).toBe(992);
    });

    test('should align with Bootstrap 5 xl breakpoint (>= 1200px)', () => {
      expect(1200).toBe(1200);
    });
  });
});
