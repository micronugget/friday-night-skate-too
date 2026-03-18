/**
 * @file
 * Starry Night - Particle effects and dynamic animations
 * Creates celestial atmosphere with CSS-based stars
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize Starry Night particle effects
   */
  Drupal.behaviors.starryNight = {
    attach: function (context, settings) {
      // Only run once on page load
      once('starry-night-init', 'body', context).forEach(function (element) {
        initStarryParticles();
        initScrollReveal();
        initAnimationStagger();
      });
    }
  };

  /**
   * Create starry particle container with CSS-based stars
   */
  function initStarryParticles() {
    // Check if user prefers reduced motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    // Check if mobile (skip particles for performance)
    if (window.innerWidth < 768) {
      return;
    }

    // Check if container already exists
    if (document.querySelector('.starry-container')) {
      return;
    }

    const container = document.createElement('div');
    container.className = 'starry-container';
    container.setAttribute('aria-hidden', 'true');

    // Generate stars with varied sizes
    const starCount = 50;
    for (let i = 0; i < starCount; i++) {
      const star = document.createElement('div');
      const sizeClass = getStarSize();
      star.className = `star ${sizeClass}`;
      container.appendChild(star);
    }

    // Insert at the beginning of body
    document.body.insertBefore(container, document.body.firstChild);
  }

  /**
   * Get random star size class
   */
  function getStarSize() {
    const rand = Math.random();
    if (rand < 0.1) {
      return 'star-large';
    } else if (rand < 0.4) {
      return 'star-small';
    }
    return '';
  }

  /**
   * Initialize Intersection Observer for scroll reveal animations
   */
  function initScrollReveal() {
    // Check if IntersectionObserver is supported
    if (!('IntersectionObserver' in window)) {
      return;
    }

    // Check if user prefers reduced motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    const observerOptions = {
      root: null,
      rootMargin: '0px 0px -100px 0px',
      threshold: 0.1
    };

    const observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          // Optionally unobserve after animation
          // observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    // Observe elements with scroll-reveal classes
    const revealElements = document.querySelectorAll(
      '.scroll-reveal, .scroll-reveal-left, .scroll-reveal-right, .scroll-reveal-scale'
    );

    revealElements.forEach(function(element) {
      observer.observe(element);
    });
  }

  /**
   * Add staggered animation delays to child elements
   */
  function initAnimationStagger() {
    const staggerContainers = document.querySelectorAll('[data-stagger-children]');

    staggerContainers.forEach(function(container) {
      const children = container.children;
      const delay = parseFloat(container.dataset.staggerDelay) || 0.1;

      Array.from(children).forEach(function(child, index) {
        child.style.animationDelay = `${index * delay}s`;
      });
    });
  }

  /**
   * Add floating animation to elements on hover (optional enhancement)
   */
  Drupal.behaviors.starryHoverEffects = {
    attach: function (context, settings) {
      once('starry-hover', '.hover-float', context).forEach(function (element) {
        element.addEventListener('mouseenter', function() {
          this.style.animationPlayState = 'running';
        });

        element.addEventListener('mouseleave', function() {
          this.style.animationPlayState = 'paused';
        });
      });
    }
  };

  /**
   * Performance optimization: Pause animations when page is not visible
   */
  document.addEventListener('visibilitychange', function() {
    const starryContainer = document.querySelector('.starry-container');
    if (!starryContainer) return;

    if (document.hidden) {
      starryContainer.style.animationPlayState = 'paused';
    } else {
      starryContainer.style.animationPlayState = 'running';
    }
  });

  /**
   * Add smooth scroll behavior for better UX
   */
  Drupal.behaviors.smoothScroll = {
    attach: function (context, settings) {
      // Check if user prefers reduced motion
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
      }

      once('smooth-scroll', 'a[href^="#"]', context).forEach(function (element) {
        element.addEventListener('click', function(e) {
          const href = this.getAttribute('href');
          if (href === '#' || href === '#main-content') {
            return; // Let default behavior handle these
          }

          const target = document.querySelector(href);
          if (target) {
            e.preventDefault();
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });

            // Update URL without jumping
            if (history.pushState) {
              history.pushState(null, null, href);
            }
          }
        });
      });
    }
  };

})(Drupal, once);
