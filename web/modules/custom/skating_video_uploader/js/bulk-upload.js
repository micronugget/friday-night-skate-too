/**
 * @file
 * Client-side validation and enhancements for bulk upload form.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.bulkUploadForm = {
    attach: function (context, settings) {
      const $form = $('.bulk-upload-form', context).once('bulk-upload-init');

      if ($form.length === 0) {
        return;
      }

      // File input enhancements.
      const $fileInput = $('input[type="file"].bulk-file-upload', $form);
      if ($fileInput.length > 0) {
        this.initFileUpload($fileInput);
      }

      // YouTube URL validation.
      const $youtubeUrls = $('.youtube-urls', $form);
      if ($youtubeUrls.length > 0) {
        this.initYouTubeValidation($youtubeUrls);
      }

      // Form submission with loading indicator.
      $form.on('submit', function (e) {
        const $submitButton = $(':submit', this);
        if (!$submitButton.hasClass('ajax-processed')) {
          $submitButton.prop('disabled', TRUE).addClass('disabled');
          $('<span class="spinner-border spinner-border-sm ms-2" role="status"><span class="visually-hidden">Loading...</span></span>').insertAfter($submitButton);
        }
      });
    },

    /**
     * Initialize file upload with drag-and-drop.
     */
    initFileUpload: function ($fileInput) {
      const $wrapper = $fileInput.closest('.form-item');

      // Drag and drop events.
      $wrapper.on('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $fileInput.addClass('dragover');
      });

      $wrapper.on('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $fileInput.removeClass('dragover');
      });

      $wrapper.on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $fileInput.removeClass('dragover');

        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
          $fileInput[0].files = files;
          $fileInput.trigger('change');
        }
      });

      // File validation on change.
      $fileInput.on('change', function () {
        const files = this.files;
        const maxFileSize = 500 * 1024 * 1024; // 500MB
        const maxFiles = 50;
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];

        if (files.length > maxFiles) {
          alert(Drupal.t('You can upload a maximum of @count files at once.', {'@count': maxFiles}));
          this.value = '';
          return FALSE;
        }

        for (let i = 0; i < files.length; i++) {
          const file = files[i];

          // Check file size.
          if (file.size > maxFileSize) {
            alert(Drupal.t('File "@name" is too large. Maximum size is 500MB.', {'@name': file.name}));
            this.value = '';
            return FALSE;
          }

          // Check file extension.
          const ext = file.name.split('.').pop().toLowerCase();
          if (allowedExtensions.indexOf(ext) === -1) {
            alert(Drupal.t('File "@name" has an invalid extension. Allowed: @ext', {
              '@name': file.name,
              '@ext': allowedExtensions.join(', ')
            }));
            this.value = '';
            return FALSE;
          }
        }

        // Display file count.
        const $description = $wrapper.find('.description');
        if ($description.length > 0 && files.length > 0) {
          // Remove any previously inserted file count messages.
          $wrapper.find('.file-count-message').remove();

          const countMsg = Drupal.t('@count file(s) selected', {'@count': files.length});
          $('<div class="alert alert-info mt-2 file-count-message">' + countMsg + '</div>').insertAfter($description);
        }
      });
    },

    /**
     * Initialize YouTube URL validation.
     */
    initYouTubeValidation: function ($textarea) {
      const youtubePattern = /^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/;

      $textarea.on('blur', function () {
        const value = $(this).val().trim();
        if (value === '') {
          return;
        }

        const urls = value.split('\n');
        const invalidUrls = [];

        urls.forEach(function (url) {
          const trimmedUrl = url.trim();
          if (trimmedUrl !== '' && !youtubePattern.test(trimmedUrl)) {
            invalidUrls.push(trimmedUrl);
          }
        });

        // Remove any existing error message.
        $(this).closest('.form-item').find('.youtube-validation-error').remove();

        if (invalidUrls.length > 0) {
          const errorMsg = Drupal.t('Invalid YouTube URL(s): @urls', {
            '@urls': invalidUrls.join(', ')
          });
          $('<div class="alert alert-danger youtube-validation-error mt-2">' + errorMsg + '</div>')
            .insertAfter($(this));
        }
      });
    }
  };

  // Mobile camera integration hint.
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    Drupal.behaviors.bulkUploadMobileCamera = {
      attach: function (context) {
        const $fileInput = $('input[type="file"][accept*="image"]', context).once('camera-hint');
        if ($fileInput.length > 0 && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
          const $hint = $('<div class="alert alert-info mt-2"><small>📷 ' +
            Drupal.t('Tap to use your camera or select from gallery') +
            '</small></div>');
          $fileInput.closest('.form-item').find('.description').after($hint);
        }
      }
    };
  }

})(jQuery, Drupal);
