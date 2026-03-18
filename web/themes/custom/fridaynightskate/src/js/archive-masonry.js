/**
 * @file
 * Masonry grid layout for Archive views.
 */

import Masonry from 'masonry-layout';
import imagesLoaded from 'imagesloaded';

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.archiveMasonry = {
    attach: function (context, settings) {
      const grids = once('masonry-init', '.masonry-grid', context);

      grids.forEach(grid => {
        // Responsive column configuration based on Bootstrap 5 breakpoints
        const getColumnCount = () => {
          const width = window.innerWidth;
          if (width < 576) return 1;      // xs: 1 column
          if (width < 768) return 2;      // sm: 2 columns
          if (width < 992) return 3;      // md: 3 columns
          if (width < 1200) return 4;     // lg: 4 columns
          return 5;                       // xl: 5 columns
        };

        // Initialize ImagesLoaded first
        imagesLoaded(grid, function () {
          // Initialize Masonry
          const masonry = new Masonry(grid, {
            itemSelector: '.masonry-item',
            columnWidth: '.masonry-sizer',
            percentPosition: true,
            gutter: 16, // Bootstrap 5 gutter equivalent
            transitionDuration: '0.3s',
            initLayout: true
          });

          // Store instance for later access
          grid.masonryInstance = masonry;

          // Re-layout on image load (lazy loading)
          const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
              if (entry.isIntersecting) {
                const img = entry.target;
                img.addEventListener('load', () => {
                  masonry.layout();
                });
              }
            });
          });

          grid.querySelectorAll('img[loading="lazy"]').forEach(img => {
            observer.observe(img);
          });

          // Handle window resize (debounced)
          let resizeTimer;
          window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
              masonry.layout();
            }, 250);
          });

          // Re-layout after Drupal AJAX events
          document.addEventListener('drupalAjaxComplete', () => {
            masonry.layout();
          });
        });
      });
    }
  };
})(Drupal, once);
