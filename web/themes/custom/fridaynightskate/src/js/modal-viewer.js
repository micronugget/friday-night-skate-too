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

  const MODAL_ID = 'fns-cinematic-modal';
  const ANIM_MS  = 320; // transition duration in ms

  // ─── State ────────────────────────────────────────────────────────────────

  let items            = [];   // NodeList snapshot of .masonry-item elements
  let currentIndex     = 0;
  let vjsPlayer        = null; // active VideoJS instance
  let focusOrigin      = null; // element focused before modal opened
  let modalEl          = null;
  let overlayEl        = null;
  let mediaWrapEl      = null;
  let titleEl          = null;
  let metaEl           = null;
  let prevBtn          = null;
  let nextBtn          = null;
  let closeBtn         = null;
  let counterEl        = null;

  // ─── Drupal behaviour ─────────────────────────────────────────────────────

  Drupal.behaviors.modalViewer = {
    attach(context) {
      const triggers = once('modal-viewer-trigger', '.masonry-item', context);
      if (!triggers.length) return;

      // Snapshot all items for navigation (whole document, not just context).
      items = Array.from(document.querySelectorAll('.masonry-item'));

      triggers.forEach((item) => {
        // Make the whole card keyboard-accessible.
        item.setAttribute('tabindex', '0');
        item.setAttribute('role', 'button');
        item.setAttribute('aria-label', `${Drupal.t('Open')} ${item.dataset.title || Drupal.t('media item')}`);

        item.addEventListener('click', handleItemClick);
        item.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handleItemClick.call(item, e);
          }
        });
      });

      ensureModal();
    },
  };

  // ─── Event handlers ───────────────────────────────────────────────────────

  function handleItemClick(e) {
    e.preventDefault();
    const idx = items.indexOf(this);
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
      case 'ArrowLeft':  e.preventDefault(); navigate(-1); break;
      case 'ArrowRight': e.preventDefault(); navigate(1);  break;
      case 'Escape':     e.preventDefault(); closeModal();  break;
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
    closeBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
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
    prevBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>`;
    prevBtn.addEventListener('click', () => navigate(-1));

    nextBtn = document.createElement('button');
    nextBtn.className = 'fns-modal__nav fns-modal__nav--next';
    nextBtn.setAttribute('aria-label', Drupal.t('Next'));
    nextBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`;
    nextBtn.addEventListener('click', () => navigate(1));

    // Info bar (title + metadata)
    const infoBar = document.createElement('div');
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
    modalEl     = document.getElementById(MODAL_ID);
    overlayEl   = document.querySelector('.fns-modal-overlay');
    mediaWrapEl = modalEl.querySelector('.fns-modal__media');
    titleEl     = modalEl.querySelector('.fns-modal__title');
    metaEl      = modalEl.querySelector('.fns-modal__meta');
    prevBtn     = modalEl.querySelector('.fns-modal__nav--prev');
    nextBtn     = modalEl.querySelector('.fns-modal__nav--next');
    closeBtn    = modalEl.querySelector('.fns-modal__close');
    counterEl   = modalEl.querySelector('.fns-modal__counter');
  }

  // ─── Open / Close ─────────────────────────────────────────────────────────

  function openModal(index) {
    currentIndex = index;
    focusOrigin  = document.activeElement;

    renderSlide(currentIndex);

    overlayEl.removeAttribute('aria-hidden');
    overlayEl.classList.add('is-open');
    modalEl.classList.add('is-open');
    document.documentElement.classList.add('fns-modal-open');
    document.body.classList.add('fns-modal-open');

    // Dim the grid
    document.querySelectorAll('.masonry-item').forEach((el, i) => {
      el.classList.toggle('is-modal-bg', i !== currentIndex);
    });

    // Focus the modal after transition
    setTimeout(() => modalEl.focus(), ANIM_MS);
  }

  function closeModal() {
    destroyVjs();

    overlayEl.classList.remove('is-open');
    overlayEl.setAttribute('aria-hidden', 'true');
    modalEl.classList.remove('is-open');
    document.documentElement.classList.remove('fns-modal-open');
    document.body.classList.remove('fns-modal-open');

    document.querySelectorAll('.masonry-item').forEach((el) => {
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
    document.querySelectorAll('.masonry-item').forEach((el, i) => {
      el.classList.toggle('is-modal-bg', i !== currentIndex);
    });
  }

  // ─── Slide rendering ──────────────────────────────────────────────────────

  function renderSlide(index) {
    const item = items[index];
    if (!item) return;

    const { mediaType, videoUrl, videoId, fullsize, date, title, uploader } = item.dataset;

    // Counter
    counterEl.textContent = `${index + 1} / ${items.length}`;

    // Title
    titleEl.textContent = title || '';

    // Metadata
    metaEl.innerHTML = '';
    if (date)     appendMeta(metaEl, Drupal.t('Date'), date);
    if (uploader) appendMeta(metaEl, Drupal.t('By'), uploader);

    // Nav visibility
    prevBtn.style.display = items.length > 1 ? '' : 'none';
    nextBtn.style.display = items.length > 1 ? '' : 'none';

    // Media content
    mediaWrapEl.innerHTML = '';

    if (mediaType === 'video' && videoUrl) {
      // Prefer the responsive <picture> from the <template> sibling element.
      // The view renders a <template id="poster-picture-{videoId}"> next to
      // each masonry item; cloneNode(true) gives native one-pass parsing with
      // no attribute escaping overhead.
      let posterFragment = null;
      if (videoId) {
        const tmpl = document.getElementById('poster-picture-' + videoId);
        if (tmpl) {
          posterFragment = tmpl.content.cloneNode(true);
        }
      }
      renderVideo(videoUrl, videoId, fullsize, posterFragment);
    } else if (videoId) {
      // Still image: prefer the responsive <picture> from the <template> sibling.
      const tmpl = document.getElementById('poster-picture-' + videoId);
      if (tmpl) {
        const wrapper = document.createElement('div');
        wrapper.className = 'fns-modal__image-wrap';
        wrapper.appendChild(tmpl.content.cloneNode(true));
        mediaWrapEl.appendChild(wrapper);
      } else if (fullsize) {
        renderImage(fullsize, title || '');
      } else {
        const thumbImg = item.querySelector('img');
        if (thumbImg) {
          renderImage(thumbImg.src, thumbImg.alt || title || '');
        }
      }
    } else if (fullsize) {
      renderImage(fullsize, title || '');
    } else {
      // Fallback: clone the poster img from the thumbnail
      const thumbImg = item.querySelector('img');
      if (thumbImg) {
        renderImage(thumbImg.src, thumbImg.alt || title || '');
      }
    }
  }

  function renderVideo(src, videoId, poster, posterFragment) {
    const isYoutube = src.includes('youtube.com') || src.includes('youtu.be');

    // Create the VideoJS Media SDC-style facade.
    // This allows the videojs_media module's behavior to handle initialization.
    const facade = document.createElement('div');
    facade.className = 'videojs-lazy-facade';
    facade.setAttribute('data-lazy-player', 'true');
    facade.setAttribute('data-lazy-player-click-only', 'true'); // Don't auto-init via IntersectionObserver

    // Poster
    if (posterFragment) {
      const posterWrapper = document.createElement('div');
      posterWrapper.className = 'videojs-lazy-facade__poster';
      posterWrapper.setAttribute('aria-hidden', 'true');
      posterWrapper.appendChild(posterFragment);
      facade.appendChild(posterWrapper);
    } else if (poster) {
      const posterImg = document.createElement('img');
      posterImg.className = 'videojs-lazy-facade__poster';
      posterImg.src = poster;
      posterImg.setAttribute('aria-hidden', 'true');
      facade.appendChild(posterImg);
    }

    // Play button - the "look" will be handled by SCSS to match thumbnails
    const playBtn = document.createElement('button');
    playBtn.className = 'videojs-lazy-facade__play-btn';
    playBtn.type = 'button';
    playBtn.setAttribute('aria-label', Drupal.t('Play video'));
    playBtn.innerHTML = `<span class="videojs-lazy-facade__play-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>`;
    facade.appendChild(playBtn);

    // Hidden video element
    const video = document.createElement('video');
    video.className = 'video-js videojs-lazy-target vjs-default-skin vjs-big-play-centered';
    video.setAttribute('preload', 'none');
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');

    const source = document.createElement('source');
    source.src = src;
    source.type = isYoutube ? 'video/youtube' : 'video/mp4';
    video.appendChild(source);
    facade.appendChild(video);

    const wrapper = document.createElement('div');
    wrapper.className = 'fns-modal__video-wrap';
    wrapper.appendChild(facade);
    mediaWrapEl.appendChild(wrapper);

    // Initialise via the VideoJS Media behavior
    if (Drupal.behaviors.videojsMediablockPlayerInit) {
      // We don't autoplay immediately because we want the user to see the play icon
      // and click it, as requested in the issue.
      Drupal.behaviors.videojsMediablockPlayerInit.initPlayerFromFacade(facade, false);

      // Grab the player instance once initialized
      const vjsEl = facade.querySelector('.videojs-lazy-target');
      if (vjsEl && vjsEl.player) {
        vjsPlayer = vjsEl.player;
        setupModalPlayerHotkeys();
      } else {
        // If it's not ready immediately, wait for the event
        const onPlayerReady = (e) => {
          if (e.detail.el() === vjsEl) {
            vjsPlayer = e.detail;
            setupModalPlayerHotkeys();
            document.removeEventListener('videojs-player-ready', onPlayerReady);
          }
        };
        document.addEventListener('videojs-player-ready', onPlayerReady);
      }
    }
  }

  function setupModalPlayerHotkeys() {
    if (!vjsPlayer) return;
    try {
      vjsPlayer.hotkeys({
        volumeStep: 0.1,
        seekStep: 5,
        enableModifiersForNumbers: false,
        enableVolumeScroll: false,
        enableHoverScroll: false,
        alwaysCaptureHotkeys: true,
        captureDocumentHotkeys: true,
        documentHotkeysFocusElementFilter: function () {
          return modalEl && modalEl.classList.contains('is-open');
        },
      });
    } catch (e) {
      /* ignore */
    }
  }

  function renderImage(src, alt) {
    const wrapper = document.createElement('div');
    wrapper.className = 'fns-modal__image-wrap';

    const img = document.createElement('img');
    img.src     = src;
    img.alt     = alt;
    img.className = 'fns-modal__image';
    img.loading = 'lazy';
    img.decoding = 'async';

    wrapper.appendChild(img);
    mediaWrapEl.appendChild(wrapper);
  }

  function appendMeta(container, label, value) {
    const span = document.createElement('span');
    span.className = 'fns-modal__meta-item';
    const strong = document.createElement('strong');
    strong.textContent = label + ': ';
    span.appendChild(strong);
    span.appendChild(document.createTextNode(value));
    container.appendChild(span);
  }

  // ─── Touch swipe ──────────────────────────────────────────────────────────

  function attachSwipeListeners(el) {
    let touchStartX = 0;
    let touchStartY = 0;

    el.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].clientX;
      touchStartY = e.changedTouches[0].clientY;
    }, { passive: true });

    el.addEventListener('touchend', (e) => {
      const dx = e.changedTouches[0].clientX - touchStartX;
      const dy = e.changedTouches[0].clientY - touchStartY;

      // Only treat as a horizontal swipe if horizontal movement dominates.
      if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;

      if (dx < 0) {
        navigate(1);   // swipe left → next
      } else {
        navigate(-1);  // swipe right → prev
      }
    }, { passive: true });
  }

  // ─── VideoJS cleanup ──────────────────────────────────────────────────────

  function destroyVjs() {
    if (vjsPlayer) {
      try { vjsPlayer.pause(); } catch (_) { /* ignore */ }
      try { vjsPlayer.dispose(); } catch (_) { /* ignore */ }
      vjsPlayer = null;
    }
  }

})(Drupal, once);
