// phpcs:ignoreFile -- JavaScript file; PHP-specific sniffs (uppercase TRUE/FALSE etc.) do not apply.
/**
 * @file
 * VideoJS player behavior for the player component.
 */
(function videojsMediablockIIFE(Drupal, once) {
  /**
   * Initialize VideoJS players with one-at-a-time playback behavior.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.videojsMediablockPlayer = {
    // Track all VideoJS players on the page
    allPlayers: [],

    /**
     * Pauses all other VideoJS players when one starts playing.
     *
     * @param {Object} currentPlayer - The current VideoJS player that is playing.
     */
    pauseOtherPlayers(currentPlayer) {
      this.allPlayers.forEach(function pausePlayerCallback(player) {
        // Only pause other players, not the one that's currently playing
        if (player !== currentPlayer && !player.paused()) {
          player.pause();
        }
      });
    },

    /**
     * Registers a VideoJS player in the global tracking list.
     * Also initializes the hotkeys plugin for the player.
     *
     * @param {Object} player - The VideoJS player instance to register.
     */
    registerPlayer(player) {
      if (
        player &&
        !player.el().hasAttribute('data-videojs-mediablock-processed')
      ) {
        // Add to tracked players list
        this.allPlayers.push(player);

        // Store 'this' for use in the event callback
        const self = this;

        // Add play event listener
        player.on('play', function playEventHandler() {
          self.pauseOtherPlayers(this);
        });

        // Initialize viewport visibility monitoring (only if enabled)
        const playerElement = player.el();
        if (playerElement.hasAttribute('data-enable-viewport-monitoring')) {
          this.initializeViewportMonitoring(player);
        }

        // Initialize the hotkeys plugin for this player
        try {
          // phpcs:disable Generic.PHP.UpperCaseConstant
          player.hotkeys({
            volumeStep: 0.1,
            seekStep: 5,
            enableModifiersForNumbers: false,
            enableVolumeScroll: false,
            enableHoverScroll: false,
          });
          // phpcs:enable Generic.PHP.UpperCaseConstant
        } catch (e) {
          console.error('Error initializing videojs-hotkeys plugin:', e);
        }

        // Initialize the mobile UI plugin
        try {
          if (player.mobileUi) {
            // phpcs:disable Generic.PHP.UpperCaseConstant
            player.mobileUi({
              fullscreen: {
                enterOnRotate: true,
                exitOnRotate: true,
                lockOnRotate: true,
              },
              touchControls: {
                seekSeconds: 10,
                tapTimeout: 300,
                disableOnEnd: false,
              },
            });
            // phpcs:enable Generic.PHP.UpperCaseConstant
          }
        } catch (e) {
          console.error('Error initializing videojs-mobile-ui plugin:', e);
        }

        // Override the mobile UI rotation handler to work regardless of play state
        // This fixes the issue where non-YouTube media types don't go fullscreen on rotation
        this.setupEnhancedRotationHandler(player);

        // Mark as processed
        player.el().setAttribute('data-videojs-mediablock-processed', 'true');
      }
    },

    /**
     * Sets up a simplified rotation handler that enters fullscreen on landscape
     * rotation only when the media is playing (not paused or stopped).
     * This properly extends the videojs-mobile-ui plugin without duplicating its logic.
     *
     * @param {Object} player - The VideoJS player instance.
     */
    setupEnhancedRotationHandler(player) {
      // Only add if we're on a mobile device
      if (!window.videojs || !(window.videojs.browser.IS_ANDROID || window.videojs.browser.IS_IOS)) {
        return;
      }

      const scr = window.screen;

      // We'll attach listeners in player.ready to ensure tech is initialized
      player.ready(() => {
        // Handlers
        const onScreenOrientationChange = () => {
          const isLandscape = !!(scr.orientation && scr.orientation.type && scr.orientation.type.indexOf('landscape') === 0);

          if (isLandscape) {
            // Only request fullscreen if playback started
            if (!player.paused() && !player.isFullscreen()) {
              try {
                const maybePromise = player.requestFullscreen();
                if (maybePromise && typeof maybePromise.catch === 'function') {
                  maybePromise.catch(err => console.warn('Fullscreen error:', err));
                }
              } catch (err) {
                console.warn('Fullscreen error:', err);
              }
            }
          } else if (player.isFullscreen()) {
            // Exit fullscreen when returning to portrait
            try {
              player.exitFullscreen();
            } catch (err) {
              // Non-fatal
            }
          }
        };

        const onWindowOrientationChange = () => {
          // Fallback for older iOS where screen.orientation is not supported
          const isLandscape = Math.abs(window.orientation || 0) === 90;
          if (isLandscape) {
            if (!player.paused() && !player.isFullscreen()) {
              try {
                player.requestFullscreen();
              } catch (err) {
                // ignore
              }
            }
          } else if (player.isFullscreen()) {
            try {
              player.exitFullscreen();
            } catch (err) {
              // ignore
            }
          }
        };

        // Attach the best available listeners without overriding existing ones
        if (scr && scr.orientation && typeof scr.orientation.addEventListener === 'function') {
          scr.orientation.addEventListener('change', onScreenOrientationChange);
        } else if ('onorientationchange' in window) {
          window.addEventListener('orientationchange', onWindowOrientationChange);
        }

        // Save references for cleanup
        const el = player.el();
        el.__vjsRotateHandlers = { onScreenOrientationChange, onWindowOrientationChange };

        // Clean up on player dispose to avoid leaks
        player.on('dispose', () => {
          if (el.__vjsRotateHandlers) {
            const h = el.__vjsRotateHandlers;
            if (scr && scr.orientation && typeof scr.orientation.removeEventListener === 'function') {
              try {
                scr.orientation.removeEventListener('change', h.onScreenOrientationChange);
              } catch (e) {
                /* ignore cleanup error */ void 0;
              }
            }
            try {
              window.removeEventListener('orientationchange', h.onWindowOrientationChange);
            } catch (e) {
              /* ignore cleanup error */ void 0;
            }
            delete el.__vjsRotateHandlers;
          }
        });
      });
    },

    /**
     * Initializes viewport visibility monitoring for a VideoJS player.
     * Automatically pauses the player when it scrolls out of view.
     *
     * @param {Object} player - The VideoJS player instance to monitor.
     */
    initializeViewportMonitoring(player) {
      // Check if Intersection Observer is supported
      if (!('IntersectionObserver' in window)) {
        console.warn('IntersectionObserver not supported - viewport monitoring disabled');
        return;
      }

      const playerElement = player.el();

      // Configuration for the intersection observer
      const observerOptions = {
        // Trigger when player is 25% visible/hidden
        threshold: 0.25,
        // Add some margin to prevent flickering on boundaries
        rootMargin: '10px',
      };

      // Create intersection observer
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          const targetPlayer = entry.target.player;

          if (targetPlayer && !targetPlayer.isDisposed()) {
            if (!entry.isIntersecting) {
              // Player scrolled out of view - pause if playing
              if (!targetPlayer.paused()) {
                targetPlayer.pause();
                console.log('VideoJS player paused: scrolled out of view');

                // Optional: Add visual indicator that player was auto-paused
                this.showAutoPauseIndicator(targetPlayer);
              }
            } else {
              // Player came back into view - remove any auto-pause indicators
              this.hideAutoPauseIndicator(targetPlayer);
            }
          }
        });
      }, observerOptions);

      // Start observing the player element
      observer.observe(playerElement);

      // Store observer reference for cleanup
      playerElement.setAttribute('data-viewport-observer', 'attached');

      // Clean up observer when player is disposed
      player.on('dispose', () => {
        if (observer) {
          observer.unobserve(playerElement);
          observer.disconnect();
        }
      });
    },

    /**
     * Shows a visual indicator that the player was auto-paused.
     *
     * @param {Object} player - The VideoJS player instance.
     */
    showAutoPauseIndicator(player) {
      const playerEl = player.el();
      let indicator = playerEl.querySelector('.videojs-auto-pause-indicator');

      if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'videojs-auto-pause-indicator';
        indicator.innerHTML = '<span>⏸️ Paused (out of view)</span>';
        // phpcs:disable Squiz.WhiteSpace.OperatorSpacing
        indicator.style.cssText = `
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          background: rgba(0, 0, 0, 0.8);
          color: white;
          padding: 8px 12px;
          border-radius: 4px;
          font-size: 14px;
          z-index: 1000;
          pointer-events: none;
          transition: opacity 0.3s ease;
        `;
        // phpcs:enable Squiz.WhiteSpace.OperatorSpacing
        playerEl.appendChild(indicator);
      }

      indicator.style.opacity = '1';

      // Auto-hide after 3 seconds
      setTimeout(() => {
        if (indicator && indicator.parentNode) {
          indicator.style.opacity = '0';
          setTimeout(() => {
            if (indicator && indicator.parentNode) {
              indicator.parentNode.removeChild(indicator);
            }
          }, 300);
        }
      }, 3000);
    },

    /**
     * Hides the auto-pause indicator.
     *
     * @param {Object} player - The VideoJS player instance.
     */
    hideAutoPauseIndicator(player) {
      const playerEl = player.el();
      const indicator = playerEl.querySelector('.videojs-auto-pause-indicator');

      if (indicator) {
        indicator.style.opacity = '0';
        setTimeout(() => {
          if (indicator && indicator.parentNode) {
            indicator.parentNode.removeChild(indicator);
          }
        }, 300);
      }
    },

    attach(context) {
      // Reset the players list when the behavior is first attached
      if (context === document) {
        this.allPlayers = [];
      }

      // Find all VideoJS players in the context
      const videoElements = context.querySelectorAll('.video-js');
      const self = this;

      videoElements.forEach(function processVideoElement(element) {
        // Check if this is a VideoJS player and not already processed
        if (
          element.player &&
          !element.hasAttribute('data-videojs-mediablock-processed')
        ) {
          self.registerPlayer(element.player);
        } else if (!element.hasAttribute('data-videojs-mediablock-processed')) {
          // Support for players that might initialize after this behavior runs
          // Mark for processing
          element.setAttribute('data-videojs-mediablock-waiting', 'true');

          // Use MutationObserver to detect when videojs enhances this element
          const observer = new MutationObserver(
            function mutationObserverCallback() {
              if (
                element.player &&
                element.hasAttribute('data-videojs-mediablock-waiting')
              ) {
                self.registerPlayer(element.player);

                // Mark as processed and clean up
                element.removeAttribute('data-videojs-mediablock-waiting');

                // Disconnect observer once processed
                observer.disconnect();
              }
            },
          );

          // Observe the element for changes
          // phpcs:disable Generic.PHP.UpperCaseConstant
          observer.observe(element, {
            attributes: true,
            childList: true,
            subtree: true,
          });
          // phpcs:enable Generic.PHP.UpperCaseConstant
        }
      });
    },
  };

  /**
   * Register callback for when a new VideoJS player is ready.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.videojsMediablockPlayerEvents = {
    attach(context) {
      once('videojs-player-events', 'body', context).forEach(
        function setupPlayerEvents() {
          // Using native event system (no jQuery dependency)
          document.addEventListener(
            'videojs-player-ready',
            function playerReadyHandler(e) {
              try {
                const player = e.detail;
                if (player) {
                  Drupal.behaviors.videojsMediablockPlayer.registerPlayer(
                    player,
                  );
                }
              } catch (err) {
                console.error(
                  'Error handling videojs-player-ready event:',
                  err,
                );
              }
            },
          );
        },
      );
    },
  };

  /**
   * Helper to initialize VideoJS players with SDC components.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.videojsMediablockPlayerInit = {
    attach(context) {
      // Only initialize if videojs is available
      if (typeof window.videojs === 'undefined') {
        return;
      }

      once('videojs-elements', 'video.video-js, audio.video-js', context).forEach(
        function initializeVideoElement(videoElement) {
          try {
            // Initialize VideoJS on the element
            window.videojs(videoElement, {
              // phpcs:disable Generic.PHP.UpperCaseConstant
              html5: {
                vhs: {
                  overrideNative: !window.videojs.browser.IS_SAFARI,
                  enableLowInitialPlaylist: true,
                  smoothQualityChange: true,
                  useBandwidthFromLocalStorage: true,
                },
              },
              // phpcs:enable Generic.PHP.UpperCaseConstant
            }, function videojsReadyCallback() {
              // Player is ready
              this.el().setAttribute('data-videojs-initialized', 'true');

              // Configure adaptive bitrate settings if HLS/DASH source is detected
              const sources = this.currentSources();
              const hasAdaptiveStream = sources.some(source =>
                source.type === 'application/vnd.apple.mpegurl' ||
                source.type === 'application/dash+xml',
              );

              if (hasAdaptiveStream) {
                // Enable quality menu if available
                if (this.qualityLevels) {
                  this.qualityLevels().on('addqualitylevel', (event) => {
                    console.log('Quality level added:', event.qualityLevel);
                  });
                }
              }

              // Trigger a native event (no jQuery dependency)
              try {
                const event = new CustomEvent('videojs-player-ready', {
                  detail: this,
                });
                document.dispatchEvent(event);
              } catch (err) {
                console.error(
                  'Error dispatching videojs-player-ready event:',
                  err,
                );
              }
            });
          } catch (e) {
            console.error('Error initializing VideoJS player:', e);
          }
        },
      );
    },
  };
})(Drupal, once);
