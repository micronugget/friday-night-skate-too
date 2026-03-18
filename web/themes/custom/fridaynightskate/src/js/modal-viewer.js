/**
 * @file
 * Modal viewer with Swiper.js navigation for archive media.
 */

import Swiper from 'swiper';
import { Navigation, Keyboard, A11y } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/a11y';

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.modalViewer = {
    attach: function (context, settings) {
      // Initialize modal trigger on masonry items
      const items = once('modal-viewer-trigger', '.masonry-item', context);
      
      items.forEach((item, index) => {
        item.addEventListener('click', (e) => {
          e.preventDefault();
          openModal(index);
        });
        
        // Make items keyboard accessible
        item.setAttribute('tabindex', '0');
        item.setAttribute('role', 'button');
        item.setAttribute('aria-label', `View media item ${index + 1}`);
        
        // Handle Enter/Space key
        item.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openModal(index);
          }
        });
      });

      // Store reference to current VideoJS player
      let currentVideoPlayer = null;
      let swiper = null;
      let focusedElementBeforeModal = null;

      /**
       * Open modal at specific index
       */
      function openModal(startIndex) {
        focusedElementBeforeModal = document.activeElement;
        
        const modal = document.getElementById('mediaModal');
        if (!modal) {
          createModal();
          openModal(startIndex);
          return;
        }

        const bsModal = new bootstrap.Modal(modal);
        
        // Build slides from masonry items
        buildSlides();
        
        // Initialize or update Swiper
        if (!swiper) {
          initializeSwiper();
        }
        
        // Go to the clicked item
        if (swiper) {
          swiper.slideTo(startIndex, 0);
        }
        
        bsModal.show();
        
        // Trap focus in modal
        trapFocus(modal);
      }

      /**
       * Create modal HTML structure
       */
      function createModal() {
        const modalHTML = `
          <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen-sm-down modal-xl modal-dialog-centered">
              <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 position-relative">
                  <div class="swiper modal-swiper">
                    <div class="swiper-wrapper" id="swiperWrapper" role="list" aria-label="Media gallery">
                    </div>
                    
                    <!-- Navigation arrows -->
                    <div class="swiper-button-prev" aria-label="Previous media item"></div>
                    <div class="swiper-button-next" aria-label="Next media item"></div>
                  </div>
                  
                  <!-- Metadata toggle button -->
                  <button class="btn btn-light metadata-toggle position-absolute" 
                          type="button" 
                          aria-label="Toggle metadata panel"
                          aria-expanded="false"
                          aria-controls="metadataPanel">
                    <svg width="24" height="24" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
                      <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                      <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                    </svg>
                  </button>
                  
                  <!-- Metadata panel -->
                  <div id="metadataPanel" class="metadata-panel position-absolute bg-dark text-white p-3" aria-hidden="true">
                    <div class="metadata-content">
                      <!-- Content populated dynamically -->
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Setup metadata toggle
        const toggleBtn = document.querySelector('.metadata-toggle');
        const panel = document.getElementById('metadataPanel');
        
        toggleBtn.addEventListener('click', () => {
          const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
          toggleBtn.setAttribute('aria-expanded', !isExpanded);
          panel.setAttribute('aria-hidden', isExpanded);
          panel.classList.toggle('show');
        });
        
        // Handle modal cleanup
        const modal = document.getElementById('mediaModal');
        modal.addEventListener('hidden.bs.modal', () => {
          cleanupVideoPlayer();
          if (swiper) {
            swiper.destroy(true, true);
            swiper = null;
          }
          // Restore focus
          if (focusedElementBeforeModal) {
            focusedElementBeforeModal.focus();
          }
        });
      }

      /**
       * Build slides from masonry items
       */
      function buildSlides() {
        const wrapper = document.getElementById('swiperWrapper');
        if (!wrapper) return;
        
        wrapper.innerHTML = '';
        
        const items = document.querySelectorAll('.masonry-item');
        items.forEach((item, index) => {
          const slide = document.createElement('div');
          slide.className = 'swiper-slide';
          slide.setAttribute('role', 'listitem');
          slide.setAttribute('aria-label', `Media item ${index + 1} of ${items.length}`);
          
          // Extract data from item
          const img = item.querySelector('img');
          const isVideo = item.dataset.mediaType === 'video' || item.classList.contains('video-item');
          
          if (isVideo) {
            // Create video player container
            const videoId = item.dataset.videoId || `video-${index}`;
            const videoUrl = item.dataset.videoUrl || '';
            
            slide.innerHTML = `
              <div class="video-container d-flex align-items-center justify-content-center h-100">
                <video id="${videoId}" class="video-js vjs-default-skin" controls preload="none" 
                       data-setup='{"fluid": true, "aspectRatio": "16:9"}'>
                  <source src="${videoUrl}" type="video/mp4">
                </video>
              </div>
            `;
          } else {
            // Create image - prefer data-fullsize from parent, then from img
            const imgSrc = item.dataset.fullsize || img?.dataset.fullsize || img?.src || '';
            const imgAlt = img?.alt || 'Archive media';
            
            slide.innerHTML = `
              <div class="image-container d-flex align-items-center justify-content-center h-100">
                <img src="${imgSrc}" alt="${imgAlt}" class="img-fluid" loading="lazy">
              </div>
            `;
          }
          
          // Store metadata from item data attributes
          slide.dataset.metadata = JSON.stringify({
            date: item.dataset.date || '',
            location: item.dataset.location || '',
            gps: item.dataset.gps || '',
            uploader: item.dataset.uploader || '',
            title: img?.alt || item.dataset.title || ''
          });
          
          wrapper.appendChild(slide);
        });
      }

      /**
       * Initialize Swiper
       */
      function initializeSwiper() {
        swiper = new Swiper('.modal-swiper', {
          modules: [Navigation, Keyboard, A11y],
          
          // Navigation
          navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
          },
          
          // Keyboard control
          keyboard: {
            enabled: true,
            onlyInViewport: false,
          },
          
          // Lazy loading (built-in to Swiper v11+)
          preloadImages: false,
          lazy: true,
          
          // Accessibility
          a11y: {
            enabled: true,
            prevSlideMessage: 'Previous media item',
            nextSlideMessage: 'Next media item',
            firstSlideMessage: 'This is the first media item',
            lastSlideMessage: 'This is the last media item',
          },
          
          // Loop
          loop: true,
          
          // Speed
          speed: 400,
          
          // Allow touch move on mobile
          simulateTouch: true,
          touchRatio: 1,
          touchAngle: 45,
          
          // Events
          on: {
            slideChange: function () {
              cleanupVideoPlayer();
              updateMetadata(this.realIndex);
              
              // Initialize VideoJS for video slides
              const activeSlide = this.slides[this.activeIndex];
              const video = activeSlide?.querySelector('video');
              
              if (video && typeof videojs !== 'undefined') {
                setTimeout(() => {
                  currentVideoPlayer = videojs(video.id);
                }, 100);
              }
            },
            init: function () {
              updateMetadata(this.realIndex);
            }
          }
        });
      }

      /**
       * Update metadata panel
       */
      function updateMetadata(index) {
        const slides = document.querySelectorAll('.swiper-slide');
        const slide = slides[index];
        
        if (!slide) return;
        
        const metadata = JSON.parse(slide.dataset.metadata || '{}');
        const content = document.querySelector('.metadata-content');
        
        if (!content) return;
        
        let html = '<div class="metadata-items">';
        
        if (metadata.title) {
          html += `<div class="metadata-item"><strong>Title:</strong> ${metadata.title}</div>`;
        }
        if (metadata.date) {
          html += `<div class="metadata-item"><strong>Date:</strong> ${metadata.date}</div>`;
        }
        if (metadata.location) {
          html += `<div class="metadata-item"><strong>Location:</strong> ${metadata.location}</div>`;
        }
        if (metadata.gps) {
          html += `<div class="metadata-item"><strong>GPS:</strong> ${metadata.gps}</div>`;
        }
        if (metadata.uploader) {
          html += `<div class="metadata-item"><strong>Uploaded by:</strong> ${metadata.uploader}</div>`;
        }
        
        html += '</div>';
        content.innerHTML = html;
      }

      /**
       * Cleanup VideoJS player
       */
      function cleanupVideoPlayer() {
        if (currentVideoPlayer) {
          try {
            currentVideoPlayer.dispose();
          } catch (e) {
            console.warn('Error disposing video player:', e);
          }
          currentVideoPlayer = null;
        }
      }

      /**
       * Trap focus within modal for accessibility
       */
      function trapFocus(modal) {
        const focusableElements = modal.querySelectorAll(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];
        
        modal.addEventListener('keydown', (e) => {
          if (e.key === 'Tab') {
            if (e.shiftKey) {
              if (document.activeElement === firstFocusable) {
                lastFocusable.focus();
                e.preventDefault();
              }
            } else {
              if (document.activeElement === lastFocusable) {
                firstFocusable.focus();
                e.preventDefault();
              }
            }
          }
        });
        
        // Focus first element
        if (firstFocusable) {
          firstFocusable.focus();
        }
      }
    }
  };
})(Drupal, once);
