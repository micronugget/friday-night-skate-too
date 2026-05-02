/******/ (function() { // webpackBootstrap
/*!********************************!*\
  !*** ./src/js/modal-viewer.js ***!
  \********************************/
/**
 * @file
 * Cinematic modal viewer — Starry Night Edition.
 *
 * Clicking a .masonry-item thumbnail opens a full-screen cinematic lightbox.
 * The active item expands to fill the viewport; the grid dims behind it.
 * Navigation arrows and keyboard (←/→/Esc) cycle through all items.
 * VideoJS is initialised inside the modal for video items.
 */

(function (Drupal, once) {
  'use strict';

  // ─── Constants ────────────────────────────────────────────────────────────
  var MODAL_ID = 'fns-cinematic-modal';
  var ANIM_MS = 320; // transition duration in ms

  // ─── State ────────────────────────────────────────────────────────────────

  var items = []; // NodeList snapshot of .masonry-item elements
  var currentIndex = 0;
  var vjsPlayer = null; // active VideoJS instance
  var focusOrigin = null; // element focused before modal opened
  var modalEl = null;
  var overlayEl = null;
  var mediaWrapEl = null;
  var titleEl = null;
  var metaEl = null;
  var prevBtn = null;
  var nextBtn = null;
  var closeBtn = null;
  var counterEl = null;

  // ─── Drupal behaviour ─────────────────────────────────────────────────────

  Drupal.behaviors.modalViewer = {
    attach: function attach(context) {
      var triggers = once('modal-viewer-trigger', '.masonry-item', context);
      if (!triggers.length) return;

      // Snapshot all items for navigation (whole document, not just context).
      items = Array.from(document.querySelectorAll('.masonry-item'));
      triggers.forEach(function (item) {
        // Make the whole card keyboard-accessible.
        item.setAttribute('tabindex', '0');
        item.setAttribute('role', 'button');
        item.setAttribute('aria-label', "".concat(Drupal.t('Open'), " ").concat(item.dataset.title || Drupal.t('media item')));
        item.addEventListener('click', handleItemClick);
        item.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handleItemClick.call(item, e);
          }
        });
      });
      ensureModal();
    }
  };

  // ─── Event handlers ───────────────────────────────────────────────────────

  function handleItemClick(e) {
    e.preventDefault();
    var idx = items.indexOf(this);
    openModal(idx >= 0 ? idx : 0);
  }
  function handleKeydown(e) {
    if (!modalEl || !modalEl.classList.contains('is-open')) return;

    // When a VideoJS player is active, let videojs-hotkeys handle playback
    // keys (space, arrows, numbers, etc.). We only intercept Escape and
    // slide-navigation arrows when there is NO active player.
    if (vjsPlayer) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closeModal();
      }
      // All other keys are left to videojs-hotkeys / videojs-mobile-ui.
      return;
    }
    switch (e.key) {
      case 'ArrowLeft':
        e.preventDefault();
        navigate(-1);
        break;
      case 'ArrowRight':
        e.preventDefault();
        navigate(1);
        break;
      case 'Escape':
        e.preventDefault();
        closeModal();
        break;
    }
  }

  // ─── Modal DOM ────────────────────────────────────────────────────────────

  function ensureModal() {
    if (document.getElementById(MODAL_ID)) {
      cacheModalRefs();
      return;
    }

    // Backdrop overlay
    overlayEl = document.createElement('div');
    overlayEl.className = 'fns-modal-overlay';
    overlayEl.setAttribute('aria-hidden', 'true');
    overlayEl.addEventListener('click', closeModal);

    // Modal shell
    modalEl = document.createElement('div');
    modalEl.id = MODAL_ID;
    modalEl.className = 'fns-modal';
    modalEl.setAttribute('role', 'dialog');
    modalEl.setAttribute('aria-modal', 'true');
    modalEl.setAttribute('aria-labelledby', 'fns-modal-title');
    modalEl.setAttribute('tabindex', '-1');

    // Close button
    closeBtn = document.createElement('button');
    closeBtn.className = 'fns-modal__close';
    closeBtn.setAttribute('aria-label', Drupal.t('Close'));
    closeBtn.innerHTML = "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\"><line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"/><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"/></svg>";
    closeBtn.addEventListener('click', closeModal);

    // Counter
    counterEl = document.createElement('div');
    counterEl.className = 'fns-modal__counter';
    counterEl.setAttribute('aria-live', 'polite');

    // Media wrapper
    mediaWrapEl = document.createElement('div');
    mediaWrapEl.className = 'fns-modal__media';

    // Prev / Next
    prevBtn = document.createElement('button');
    prevBtn.className = 'fns-modal__nav fns-modal__nav--prev';
    prevBtn.setAttribute('aria-label', Drupal.t('Previous'));
    prevBtn.innerHTML = "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"15 18 9 12 15 6\"/></svg>";
    prevBtn.addEventListener('click', function () {
      return navigate(-1);
    });
    nextBtn = document.createElement('button');
    nextBtn.className = 'fns-modal__nav fns-modal__nav--next';
    nextBtn.setAttribute('aria-label', Drupal.t('Next'));
    nextBtn.innerHTML = "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"9 18 15 12 9 6\"/></svg>";
    nextBtn.addEventListener('click', function () {
      return navigate(1);
    });

    // Info bar (title + metadata)
    var infoBar = document.createElement('div');
    infoBar.className = 'fns-modal__info';
    titleEl = document.createElement('h2');
    titleEl.id = 'fns-modal-title';
    titleEl.className = 'fns-modal__title';
    metaEl = document.createElement('div');
    metaEl.className = 'fns-modal__meta';
    infoBar.appendChild(titleEl);
    infoBar.appendChild(metaEl);

    // Assemble
    modalEl.appendChild(closeBtn);
    modalEl.appendChild(counterEl);
    modalEl.appendChild(prevBtn);
    modalEl.appendChild(mediaWrapEl);
    modalEl.appendChild(nextBtn);
    modalEl.appendChild(infoBar);
    document.body.appendChild(overlayEl);
    document.body.appendChild(modalEl);
    document.addEventListener('keydown', handleKeydown);

    // Touch swipe support: swipe left → next, swipe right → prev.
    attachSwipeListeners(modalEl);
  }
  function cacheModalRefs() {
    modalEl = document.getElementById(MODAL_ID);
    overlayEl = document.querySelector('.fns-modal-overlay');
    mediaWrapEl = modalEl.querySelector('.fns-modal__media');
    titleEl = modalEl.querySelector('.fns-modal__title');
    metaEl = modalEl.querySelector('.fns-modal__meta');
    prevBtn = modalEl.querySelector('.fns-modal__nav--prev');
    nextBtn = modalEl.querySelector('.fns-modal__nav--next');
    closeBtn = modalEl.querySelector('.fns-modal__close');
    counterEl = modalEl.querySelector('.fns-modal__counter');
  }

  // ─── Open / Close ─────────────────────────────────────────────────────────

  function openModal(index) {
    currentIndex = index;
    focusOrigin = document.activeElement;
    renderSlide(currentIndex);
    overlayEl.removeAttribute('aria-hidden');
    overlayEl.classList.add('is-open');
    modalEl.classList.add('is-open');
    document.documentElement.classList.add('fns-modal-open');
    document.body.classList.add('fns-modal-open');

    // Dim the grid
    document.querySelectorAll('.masonry-item').forEach(function (el, i) {
      el.classList.toggle('is-modal-bg', i !== currentIndex);
    });

    // Focus the modal after transition
    setTimeout(function () {
      return modalEl.focus();
    }, ANIM_MS);
  }
  function closeModal() {
    destroyVjs();
    overlayEl.classList.remove('is-open');
    overlayEl.setAttribute('aria-hidden', 'true');
    modalEl.classList.remove('is-open');
    document.documentElement.classList.remove('fns-modal-open');
    document.body.classList.remove('fns-modal-open');
    document.querySelectorAll('.masonry-item').forEach(function (el) {
      el.classList.remove('is-modal-bg');
    });
    if (focusOrigin) focusOrigin.focus();
  }
  function navigate(delta) {
    if (!items.length) return;
    destroyVjs();
    currentIndex = (currentIndex + delta + items.length) % items.length;
    renderSlide(currentIndex);

    // Update dim state
    document.querySelectorAll('.masonry-item').forEach(function (el, i) {
      el.classList.toggle('is-modal-bg', i !== currentIndex);
    });
  }

  // ─── Slide rendering ──────────────────────────────────────────────────────

  function renderSlide(index) {
    var item = items[index];
    if (!item) return;
    var _item$dataset = item.dataset,
      mediaType = _item$dataset.mediaType,
      videoUrl = _item$dataset.videoUrl,
      videoId = _item$dataset.videoId,
      fullsize = _item$dataset.fullsize,
      date = _item$dataset.date,
      title = _item$dataset.title,
      uploader = _item$dataset.uploader;

    // Counter
    counterEl.textContent = "".concat(index + 1, " / ").concat(items.length);

    // Title
    titleEl.textContent = title || '';

    // Metadata
    metaEl.innerHTML = '';
    if (date) appendMeta(metaEl, Drupal.t('Date'), date);
    if (uploader) appendMeta(metaEl, Drupal.t('By'), uploader);

    // Nav visibility
    prevBtn.style.display = items.length > 1 ? '' : 'none';
    nextBtn.style.display = items.length > 1 ? '' : 'none';

    // Media content
    mediaWrapEl.innerHTML = '';
    if (mediaType === 'video' && videoUrl) {
      // Prefer the responsive <picture> markup if the view provided it.
      var posterPicture = item.dataset.posterPicture || '';
      renderVideo(videoUrl, videoId, fullsize, posterPicture);
    } else if (fullsize) {
      renderImage(fullsize, title || '');
    } else {
      // Fallback: clone the poster img from the thumbnail
      var thumbImg = item.querySelector('img');
      if (thumbImg) {
        renderImage(thumbImg.src, thumbImg.alt || title || '');
      }
    }
  }
  function renderVideo(src, videoId, poster, posterPictureHtml) {
    // Always use a unique element ID to avoid VideoJS conflicts when the same
    // video is opened more than once in a session.
    var uid = "fns-modal-video-".concat(Date.now());
    var isYoutube = src.includes('youtube.com') || src.includes('youtu.be');
    var wrapper = document.createElement('div');
    wrapper.className = 'fns-modal__video-wrap';
    var video = document.createElement('video');
    video.id = uid;
    video.className = 'video-js vjs-default-skin vjs-big-play-centered';
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');

    // Poster strategy:
    //   1. If a responsive <picture> was provided by the view, do NOT set
    //      `video.poster` (which only takes a single URL and triggers a full-
    //      res download). Instead, inject the <picture> as an overlay so the
    //      browser resolves the srcset natively.
    //   2. Otherwise, fall back to the single-URL `poster` attribute.
    if (!posterPictureHtml && poster) {
      video.poster = poster;
    }
    var source = document.createElement('source');
    source.src = src;
    source.type = isYoutube ? 'video/youtube' : 'video/mp4';
    video.appendChild(source);
    wrapper.appendChild(video);
    if (posterPictureHtml) {
      var posterOverlay = document.createElement('div');
      posterOverlay.className = 'fns-modal__video-poster';
      posterOverlay.setAttribute('aria-hidden', 'true');
      posterOverlay.innerHTML = posterPictureHtml;
      // The overlay sits over the video until VideoJS shows its own UI.
      // VideoJS will paint its own internal poster on top once the player
      // initialises; until then this <picture> is what the user sees.
      wrapper.appendChild(posterOverlay);
    }
    mediaWrapEl.appendChild(wrapper);

    // Initialise VideoJS if available.
    if (typeof videojs !== 'undefined') {
      setTimeout(function () {
        var options = {
          fluid: true,
          aspectRatio: '16:9',
          controls: true,
          preload: 'auto'
        };

        // The videojs-youtube plugin registers itself as 'Youtube' (capital Y).
        // For YouTube sources it must be first in techOrder; for plain mp4 we
        // only need html5.
        if (isYoutube) {
          options.techOrder = ['youtube', 'html5'];
          // youtube tech requires the src on the options object, not just the
          // <source> element, to initialise correctly.
          options.sources = [{
            src: src,
            type: 'video/youtube'
          }];
        } else {
          options.techOrder = ['html5'];
        }
        vjsPlayer = videojs(uid, options);

        // Register with the videojs_media behavior so mobile-ui and
        // one-at-a-time playback are initialised on this player instance.
        vjsPlayer.ready(function () {
          if (Drupal.behaviors.videojsMediablockPlayer) {
            Drupal.behaviors.videojsMediablockPlayer.registerPlayer(vjsPlayer);
          }
          // Re-initialise hotkeys with alwaysCaptureHotkeys so they work
          // inside the modal regardless of which element holds focus.
          // This overrides the default hotkeys setup from registerPlayer.
          try {
            vjsPlayer.hotkeys({
              volumeStep: 0.1,
              seekStep: 5,
              enableModifiersForNumbers: false,
              enableVolumeScroll: false,
              enableHoverScroll: false,
              alwaysCaptureHotkeys: true,
              captureDocumentHotkeys: true,
              documentHotkeysFocusElementFilter: function documentHotkeysFocusElementFilter() {
                // Accept hotkeys when any element inside the modal has focus.
                return modalEl && modalEl.classList.contains('is-open');
              }
            });
          } catch (e) {
            // Fallback: hotkeys plugin not available.
          }
        });
      }, 50);
    }
  }
  function renderImage(src, alt) {
    var wrapper = document.createElement('div');
    wrapper.className = 'fns-modal__image-wrap';
    var img = document.createElement('img');
    img.src = src;
    img.alt = alt;
    img.className = 'fns-modal__image';
    img.loading = 'eager';
    wrapper.appendChild(img);
    mediaWrapEl.appendChild(wrapper);
  }
  function appendMeta(container, label, value) {
    var span = document.createElement('span');
    span.className = 'fns-modal__meta-item';
    var strong = document.createElement('strong');
    strong.textContent = label + ': ';
    span.appendChild(strong);
    span.appendChild(document.createTextNode(value));
    container.appendChild(span);
  }

  // ─── Touch swipe ──────────────────────────────────────────────────────────

  function attachSwipeListeners(el) {
    var touchStartX = 0;
    var touchStartY = 0;
    el.addEventListener('touchstart', function (e) {
      touchStartX = e.changedTouches[0].clientX;
      touchStartY = e.changedTouches[0].clientY;
    }, {
      passive: true
    });
    el.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - touchStartX;
      var dy = e.changedTouches[0].clientY - touchStartY;

      // Only treat as a horizontal swipe if horizontal movement dominates.
      if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;
      if (dx < 0) {
        navigate(1); // swipe left → next
      } else {
        navigate(-1); // swipe right → prev
      }
    }, {
      passive: true
    });
  }

  // ─── VideoJS cleanup ──────────────────────────────────────────────────────

  function destroyVjs() {
    if (vjsPlayer) {
      try {
        vjsPlayer.pause();
      } catch (_) {/* ignore */}
      try {
        vjsPlayer.dispose();
      } catch (_) {/* ignore */}
      vjsPlayer = null;
    }
  }
})(Drupal, once);
/******/ })()
;
//# sourceMappingURL=modal-viewer.js.map