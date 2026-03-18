<?php

declare(strict_types=1);

namespace Drupal\skating_video_uploader\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\skating_video_uploader\Service\MetadataExtractor;
use Drupal\skating_video_uploader\Service\VideoProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Multi-step bulk upload form for Friday Night Skate media.
 *
 * Stages:
 * 1. File Selection (bulk images + YouTube URLs)
 * 2. Metadata Extraction (progress indicators)
 * 3. Assign Skate Date (autocomplete taxonomy)
 * 4. Review & Submit for moderation.
 */
class BulkUploadForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The metadata extractor service.
   *
   * @var \Drupal\skating_video_uploader\Service\MetadataExtractor
   */
  protected MetadataExtractor $metadataExtractor;

  /**
   * The video processor service.
   *
   * @var \Drupal\skating_video_uploader\Service\VideoProcessor
   */
  protected VideoProcessor $videoProcessor;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a BulkUploadForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\skating_video_uploader\Service\MetadataExtractor $metadata_extractor
   *   The metadata extractor service.
   * @param \Drupal\skating_video_uploader\Service\VideoProcessor $video_processor
   *   The video processor service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MetadataExtractor $metadata_extractor,
    VideoProcessor $video_processor,
    MessengerInterface $messenger,
    AccountInterface $current_user,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->metadataExtractor = $metadata_extractor;
    $this->videoProcessor = $video_processor;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('skating_video_uploader.metadata_extractor'),
      $container->get('skating_video_uploader.processor'),
      $container->get('messenger'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'skating_video_uploader_bulk_upload';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $step = $form_state->get('step') ?? 1;
    $form_state->set('step', $step);

    $form['#attributes']['class'][] = 'bulk-upload-form';
    $form['#attached']['library'][] = 'skating_video_uploader/bulk_upload';

    // Progress indicator.
    $form['progress'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['upload-progress', 'mb-4']],
    ];
    $form['progress']['indicator'] = [
      '#markup' => $this->buildProgressIndicator($step),
    ];

    // Build the appropriate step.
    switch ($step) {
      case 1:
        $this->buildStepOne($form, $form_state);
        break;

      case 2:
        $this->buildStepTwo($form, $form_state);
        break;

      case 3:
        $this->buildStepThree($form, $form_state);
        break;

      case 4:
        $this->buildStepFour($form, $form_state);
        break;
    }

    return $form;
  }

  /**
   * Build progress indicator HTML.
   *
   * @param int $current_step
   *   The current step number.
   *
   * @return string
   *   The progress indicator HTML.
   */
  protected function buildProgressIndicator(int $current_step): string {
    $steps = [
      1 => $this->t('Select Files'),
      2 => $this->t('Extract Metadata'),
      3 => $this->t('Assign Date'),
      4 => $this->t('Review & Submit'),
    ];

    $html = '<div class="progress-steps d-flex justify-content-between mb-4">';
    foreach ($steps as $step => $label) {
      $classes = ['step'];
      if ($step < $current_step) {
        $classes[] = 'completed';
      }
      elseif ($step === $current_step) {
        $classes[] = 'active';
      }
      $class_string = implode(' ', $classes);
      $html .= sprintf(
        '<div class="%s"><span class="step-number">%d</span><span class="step-label">%s</span></div>',
        Html::escape($class_string),
        $step,
        Html::escape($label)
      );
    }
    $html .= '</div>';

    return $html;
  }

  /**
   * Build step 1: File selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildStepOne(array &$form, FormStateInterface $form_state): void {
    $form['step1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['step-one']],
    ];

    $form['step1']['description'] = [
      '#markup' => '<p class="lead">' . $this->t('Upload images and videos from your Friday Night Skate session.') . '</p>',
    ];

    // Bulk file upload.
    $form['step1']['files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select Files'),
      '#description' => $this->t('Select multiple images (JPG, PNG) and videos (MP4, MOV). Maximum 50 files per upload. Each file should be under 500MB.'),
      '#upload_location' => 'private://skating-uploads',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png gif mp4 mov avi'],
        'file_validate_size' => [500 * 1024 * 1024],
      ],
      '#multiple' => TRUE,
      '#required' => FALSE,
      '#attributes' => [
        'class' => ['bulk-file-upload'],
        'accept' => 'image/*,video/*',
        'capture' => 'environment',
      ],
    ];

    // YouTube URLs.
    $form['step1']['youtube_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('YouTube Video URLs'),
      '#description' => $this->t('Enter one YouTube URL per line. These videos will be linked to your archive.'),
      '#placeholder' => "https://www.youtube.com/watch?v=...\nhttps://youtu.be/...",
      '#rows' => 4,
      '#attributes' => ['class' => ['youtube-urls']],
    ];

    // Navigation.
    $form['step1']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['d-flex', 'justify-content-end', 'mt-4']],
    ];

    $form['step1']['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next: Extract Metadata'),
      '#submit' => ['::nextStep'],
      '#validate' => ['::validateStepOne'],
      '#attributes' => ['class' => ['btn', 'btn-primary', 'btn-lg']],
    ];
  }

  /**
   * Build step 2: Metadata extraction.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildStepTwo(array &$form, FormStateInterface $form_state): void {
    $form['step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['step-two']],
    ];

    $form['step2']['description'] = [
      '#markup' => '<p class="lead">' . $this->t('Extracting metadata from your files...') . '</p>',
    ];

    // Progress bar for metadata extraction.
    $form['step2']['extraction_progress'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['metadata-extraction-progress'],
        'id' => 'extraction-progress',
      ],
    ];

    $uploaded_files = $form_state->get('uploaded_files') ?? [];
    $total_files = count($uploaded_files);
    $processed = $form_state->get('processed_files') ?? 0;

    $form['step2']['extraction_progress']['bar'] = [
      '#markup' => sprintf(
        '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: %d%%" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100">%d of %d files processed</div></div>',
        $total_files > 0 ? ($processed / $total_files * 100) : 0,
        $total_files > 0 ? ($processed / $total_files * 100) : 0,
        $processed,
        $total_files
      ),
    ];

    // File list with metadata status.
    $form['step2']['file_list'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['file-list', 'mt-4']],
    ];

    $metadata_results = $form_state->get('metadata_results') ?? [];
    foreach ($uploaded_files as $index => $file_id) {
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);
      if ($file) {
        $status = isset($metadata_results[$file_id]) ? 'success' : 'pending';
        $form['step2']['file_list']['file_' . $index] = [
          '#markup' => sprintf(
            '<div class="file-item d-flex align-items-center mb-2"><span class="badge bg-%s me-2">%s</span><span>%s</span></div>',
            $status === 'success' ? 'success' : 'secondary',
            $status === 'success' ? '✓' : '⋯',
            Html::escape($file->getFilename())
          ),
        ];
      }
    }

    // Navigation.
    $form['step2']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['d-flex', 'justify-content-between', 'mt-4']],
    ];

    $form['step2']['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('← Back'),
      '#submit' => ['::previousStep'],
      '#attributes' => ['class' => ['btn', 'btn-secondary']],
      '#limit_validation_errors' => [],
    ];

    $form['step2']['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next: Assign Date →'),
      '#submit' => ['::nextStep'],
      '#ajax' => [
        'callback' => '::ajaxExtractMetadata',
        'wrapper' => 'extraction-progress',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Extracting metadata...'),
        ],
      ],
      '#attributes' => ['class' => ['btn', 'btn-primary', 'btn-lg']],
    ];
  }

  /**
   * Build step 3: Assign skate date.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildStepThree(array &$form, FormStateInterface $form_state): void {
    $form['step3'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['step-three']],
    ];

    $form['step3']['description'] = [
      '#markup' => '<p class="lead">' . $this->t('Assign a skate date to your uploaded files.') . '</p>',
    ];

    // Skate date selector with autocomplete.
    $form['step3']['skate_date'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Skate Date'),
      '#description' => $this->t('Select or create a skate date. Format: YYYY-MM-DD - Location/Description'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['skate_dates'],
      ],
      '#required' => TRUE,
      '#attributes' => ['class' => ['form-control-lg']],
      '#tags' => FALSE,
    ];

    // Attribution field.
    $default_attribution = $this->currentUser->getDisplayName();
    $form['step3']['attribution'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attribution'),
      '#description' => $this->t('Credit for these uploads. Defaults to your username.'),
      '#default_value' => $default_attribution,
      '#required' => TRUE,
      '#attributes' => ['class' => ['form-control-lg']],
    ];

    // Apply to all checkbox.
    $form['step3']['apply_to_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply this date and attribution to all uploaded files'),
      '#default_value' => TRUE,
    ];

    // Navigation.
    $form['step3']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['d-flex', 'justify-content-between', 'mt-4']],
    ];

    $form['step3']['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('← Back'),
      '#submit' => ['::previousStep'],
      '#attributes' => ['class' => ['btn', 'btn-secondary']],
      '#limit_validation_errors' => [],
    ];

    $form['step3']['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next: Review →'),
      '#submit' => ['::nextStep'],
      '#validate' => ['::validateStepThree'],
      '#attributes' => ['class' => ['btn', 'btn-primary', 'btn-lg']],
    ];
  }

  /**
   * Build step 4: Review and submit.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function buildStepFour(array &$form, FormStateInterface $form_state): void {
    $form['step4'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['step-four']],
    ];

    $form['step4']['description'] = [
      '#markup' => '<p class="lead">' . $this->t('Review your submission before finalizing.') . '</p>',
    ];

    // Summary.
    $uploaded_files = $form_state->get('uploaded_files') ?? [];
    $youtube_urls = $form_state->get('youtube_urls') ?? [];
    $skate_date_id = $form_state->get('skate_date');
    $attribution = $form_state->get('attribution');

    $form['step4']['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
    ];

    $summary_html = '<div class="card-body">';
    $summary_html .= '<h5 class="card-title">' . $this->t('Upload Summary') . '</h5>';
    $summary_html .= '<dl class="row">';
    $summary_html .= '<dt class="col-sm-3">' . $this->t('Files') . '</dt><dd class="col-sm-9">' . count($uploaded_files) . ' ' . $this->t('files') . '</dd>';

    if (!empty($youtube_urls)) {
      $summary_html .= '<dt class="col-sm-3">' . $this->t('YouTube URLs') . '</dt><dd class="col-sm-9">' . count($youtube_urls) . ' ' . $this->t('videos') . '</dd>';
    }

    if ($skate_date_id) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($skate_date_id);
      if ($term) {
        $summary_html .= '<dt class="col-sm-3">' . $this->t('Skate Date') . '</dt><dd class="col-sm-9">' . Html::escape($term->getName()) . '</dd>';
      }
    }

    $summary_html .= '<dt class="col-sm-3">' . $this->t('Attribution') . '</dt><dd class="col-sm-9">' . Html::escape($attribution ?? '') . '</dd>';
    $summary_html .= '</dl>';
    $summary_html .= '</div>';

    $form['step4']['summary']['content'] = [
      '#markup' => $summary_html,
    ];

    // Moderation note.
    $form['step4']['moderation_note'] = [
      '#markup' => '<div class="alert alert-info">' . $this->t('Your submission will be sent for moderation review before appearing publicly.') . '</div>',
    ];

    // Navigation.
    $form['step4']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['d-flex', 'justify-content-between', 'mt-4']],
    ];

    $form['step4']['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('← Back'),
      '#submit' => ['::previousStep'],
      '#attributes' => ['class' => ['btn', 'btn-secondary']],
      '#limit_validation_errors' => [],
    ];

    $form['step4']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit for Moderation'),
      '#attributes' => ['class' => ['btn', 'btn-success', 'btn-lg']],
    ];
  }

  /**
   * Validate step 1: File selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateStepOne(array &$form, FormStateInterface $form_state): void {
    $files = $form_state->getValue('files');
    $youtube_urls = trim((string) $form_state->getValue('youtube_urls'));

    if (empty($files) && empty($youtube_urls)) {
      $form_state->setErrorByName('files', $this->t('Please upload at least one file or provide a YouTube URL.'));
      return;
    }

    // Server-side validation: maximum 50 files.
    if (!empty($files) && count($files) > 50) {
      $form_state->setErrorByName('files', $this->t('You can upload a maximum of 50 files at once. You selected @count files.', [
        '@count' => count($files),
      ]));
      return;
    }

    // Validate YouTube URLs.
    if (!empty($youtube_urls)) {
      $urls = array_filter(array_map('trim', explode("\n", $youtube_urls)));

      // Server-side validation: maximum 50 YouTube URLs.
      if (count($urls) > 50) {
        $form_state->setErrorByName('youtube_urls', $this->t('You can submit a maximum of 50 YouTube URLs at once. You provided @count URLs.', [
          '@count' => count($urls),
        ]));
        return;
      }

      $valid_urls = [];
      foreach ($urls as $url) {
        if ($this->isValidYouTubeUrl($url)) {
          $valid_urls[] = $url;
        }
        else {
          $form_state->setErrorByName('youtube_urls', $this->t('Invalid YouTube URL: @url', ['@url' => $url]));
        }
      }
      $form_state->set('youtube_urls', $valid_urls);
    }

    // Store uploaded files.
    if (!empty($files)) {
      $form_state->set('uploaded_files', $files);
    }
  }

  /**
   * Validate step 3: Skate date assignment.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateStepThree(array &$form, FormStateInterface $form_state): void {
    $skate_date = $form_state->getValue('skate_date');
    if (empty($skate_date)) {
      $form_state->setErrorByName('skate_date', $this->t('Please select or create a skate date.'));
    }
    else {
      $form_state->set('skate_date', $skate_date);
    }

    $attribution = trim((string) $form_state->getValue('attribution'));
    if (empty($attribution)) {
      $form_state->setErrorByName('attribution', $this->t('Attribution is required.'));
    }
    else {
      $form_state->set('attribution', $attribution);
    }
  }

  /**
   * Check if a URL is a valid YouTube URL.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function isValidYouTubeUrl(string $url): bool {
    $pattern = '/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    return (bool) preg_match($pattern, $url);
  }

  /**
   * AJAX callback for metadata extraction.
   *
   * Note: This performs synchronous extraction which may timeout for large
   * batches. Future enhancement: Implement queue-based processing with
   * progress updates using Drupal Batch API or Queue API.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxExtractMetadata(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    $uploaded_files = $form_state->get('uploaded_files') ?? [];
    $metadata_results = [];

    foreach ($uploaded_files as $file_id) {
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);
      if ($file) {
        try {
          $metadata = $this->metadataExtractor->extractMetadata($file);
          $metadata_results[$file_id] = $metadata;
        }
        catch (\Exception $e) {
          $metadata_results[$file_id] = ['error' => $e->getMessage()];
        }
      }
    }

    $form_state->set('metadata_results', $metadata_results);
    $form_state->set('processed_files', count($metadata_results));

    $response->addCommand(new HtmlCommand('#extraction-progress', $form['step2']['extraction_progress']));

    return $response;
  }

  /**
   * Submit handler to move to the next step.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function nextStep(array &$form, FormStateInterface $form_state): void {
    $current_step = $form_state->get('step');
    $form_state->set('step', $current_step + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler to move to the previous step.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function previousStep(array &$form, FormStateInterface $form_state): void {
    $current_step = $form_state->get('step');
    $form_state->set('step', max(1, $current_step - 1));
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uploaded_files = $form_state->get('uploaded_files') ?? [];
    $youtube_urls = $form_state->get('youtube_urls') ?? [];
    $skate_date_id = $form_state->get('skate_date');
    $attribution = $form_state->get('attribution');
    $metadata_results = $form_state->get('metadata_results') ?? [];

    try {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $media_storage = $this->entityTypeManager->getStorage('media');

      // Create archive_media nodes for each uploaded file.
      foreach ($uploaded_files as $file_id) {
        $file = $this->entityTypeManager->getStorage('file')->load($file_id);
        if (!$file) {
          continue;
        }

        // Determine media bundle based on file MIME type.
        $mime_type = $file->getMimeType();
        $is_video = str_starts_with($mime_type, 'video/');
        $bundle = $is_video ? 'video' : 'image';
        $source_field = $is_video ? 'field_media_video_file' : 'field_media_image';

        // Create media entity.
        $media = $media_storage->create([
          'bundle' => $bundle,
          'name' => $file->getFilename(),
          $source_field => [
            'target_id' => $file_id,
          ],
          'uid' => $this->currentUser->id(),
        ]);
        $media->save();

        // Extract metadata.
        $metadata = $metadata_results[$file_id] ?? [];

        // Create archive_media node with media reference.
        $node = $node_storage->create([
          'type' => 'archive_media',
          'title' => $file->getFilename(),
          'field_archive_media' => [
            'target_id' => $media->id(),
          ],
          'field_skate_date' => ['target_id' => $skate_date_id],
          'field_uploader' => $attribution,
          'field_metadata' => json_encode($metadata),
          'moderation_state' => 'draft',
          'uid' => $this->currentUser->id(),
        ]);

        $node->save();
      }

      // Process YouTube URLs.
      // Note: YouTube URLs require a dedicated field (e.g., field_youtube_url).
      // For now, we store them in a text field or link field if available.
      foreach ($youtube_urls as $url) {
        $video_id = $this->extractYouTubeVideoId($url);
        $title = $video_id ? $this->t('YouTube Video: @id', ['@id' => $video_id]) : $this->t('YouTube Video');

        $node = $node_storage->create([
          'type' => 'archive_media',
          'title' => $title,
          'field_skate_date' => ['target_id' => $skate_date_id],
          'field_uploader' => $attribution,
          'field_metadata' => json_encode(['youtube_url' => $url]),
          'moderation_state' => 'draft',
          'uid' => $this->currentUser->id(),
        ]);

        $node->save();
      }

      $this->messenger->addStatus($this->t('Successfully uploaded @count items. Your submission has been sent for moderation.', [
        '@count' => count($uploaded_files) + count($youtube_urls),
      ]));

      $form_state->setRedirect('view.moderation_dashboard.page_1');
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('An error occurred during submission: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Extract YouTube video ID from URL.
   *
   * @param string $url
   *   The YouTube URL.
   *
   * @return string|null
   *   The video ID or NULL if not found.
   */
  protected function extractYouTubeVideoId(string $url): ?string {
    $pattern = '/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    if (preg_match($pattern, $url, $matches)) {
      return $matches[1];
    }
    return NULL;
  }

}
