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

    // Use capture phase so our handler fires before VideoJS consumes the event.
    document.addEventListener('keydown', handleKeydown, true);
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
      renderVideo(videoUrl, videoId, fullsize);
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

  function renderVideo(src, videoId, poster) {
    const uid = videoId || `fns-modal-video-${Date.now()}`;

    const wrapper = document.createElement('div');
    wrapper.className = 'fns-modal__video-wrap';

    const video = document.createElement('video');
    video.id = uid;
    video.className = 'video-js vjs-default-skin vjs-big-play-centered';
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');
    if (poster) video.poster = poster;

    const source = document.createElement('source');
    source.src  = src;
    source.type = src.includes('youtube.com') || src.includes('youtu.be') ? 'video/youtube' : 'video/mp4';
    video.appendChild(source);
    wrapper.appendChild(video);
    mediaWrapEl.appendChild(wrapper);

    // Initialise VideoJS if available
    if (typeof videojs !== 'undefined') {
      setTimeout(() => {
        vjsPlayer = videojs(uid, {
          fluid: true,
          aspectRatio: '16:9',
          controls: true,
          preload: 'auto',
          techOrder: ['html5', 'videojs_youtube'],
          // Disable VideoJS hotkeys so modal keyboard nav (←/→/Esc) works.
          userActions: { hotkeys: false },
        });
      }, 50);
    }
  }

  function renderImage(src, alt) {
    const wrapper = document.createElement('div');
    wrapper.className = 'fns-modal__image-wrap';

    const img = document.createElement('img');
    img.src     = src;
    img.alt     = alt;
    img.className = 'fns-modal__image';
    img.loading = 'eager';

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

  // ─── VideoJS cleanup ──────────────────────────────────────────────────────

  function destroyVjs() {
    if (vjsPlayer) {
      try { vjsPlayer.dispose(); } catch (_) { /* ignore */ }
      vjsPlayer = null;
    }
  }

})(Drupal, once);
