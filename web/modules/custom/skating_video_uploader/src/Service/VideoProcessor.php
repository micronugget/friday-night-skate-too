<?php

declare(strict_types=1);

namespace Drupal\skating_video_uploader\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\videojs_media\VideoJsMediaInterface;

/**
 * Service for processing video entities and coordinating metadata extraction and YouTube uploads.
 */
class VideoProcessor {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The metadata extractor service.
   *
   * @var \Drupal\skating_video_uploader\Service\MetadataExtractor
   */
  protected $metadataExtractor;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The YouTube uploader service.
   *
   * @var \Drupal\skating_video_uploader\Service\YouTubeUploader
   */
  protected $youtubeUploader;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new VideoProcessor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\skating_video_uploader\Service\MetadataExtractor $metadata_extractor
   *   The metadata extractor service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\skating_video_uploader\Service\YouTubeUploader $youtube_uploader
   *   The YouTube uploader service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    MetadataExtractor $metadata_extractor,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger,
    YouTubeUploader $youtube_uploader,
    Connection $database,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory->get('skating_video_uploader');
    $this->metadataExtractor = $metadata_extractor;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->youtubeUploader = $youtube_uploader;
    $this->database = $database;
  }

  /**
   * Processes an entity for metadata extraction and YouTube upload.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return bool
   *   TRUE if processing was successful, FALSE otherwise.
   */
  public function processEntity(EntityInterface $entity) {
    try {
      // Check if this is a VideoJS Media entity.
      if (!($entity instanceof VideoJsMediaInterface)) {
        $this->loggerFactory->error('Unsupported entity type @type', ['@type' => $entity->getEntityTypeId()]);
        $this->messenger->addError($this->t('Unsupported entity type.'));
        return FALSE;
      }

      // Only process local video and local audio types.
      $bundle = $entity->bundle();
      if ($bundle !== 'local_video' && $bundle !== 'local_audio') {
        $this->loggerFactory->error('VideoJS Media entity must be local_video or local_audio, got @bundle', ['@bundle' => $bundle]);
        $this->messenger->addError($this->t('Only local video and audio files can be processed.'));
        return FALSE;
      }

      // Extract metadata from the VideoJS Media entity.
      $metadata = $this->metadataExtractor->extractMetadata($entity);
      if (!$metadata) {
        $this->loggerFactory->error('Failed to extract metadata from VideoJS Media entity @id', ['@id' => $entity->id()]);
        $this->messenger->addError($this->t('Failed to extract metadata from the video.'));
        return FALSE;
      }

      // Update the metadata with consent information.
      $this->updateConsentStatus($entity->id(), TRUE);

      // Upload the video to YouTube.
      $youtube_id = $this->youtubeUploader->uploadVideo($entity, $metadata);
      if (!$youtube_id) {
        $this->loggerFactory->error('Failed to upload video to YouTube for VideoJS Media entity @id', ['@id' => $entity->id()]);
        $this->messenger->addError($this->t('Failed to upload the video to YouTube. Please check the YouTube API configuration.'));
        return FALSE;
      }

      // Update the metadata with the YouTube ID.
      $this->updateYouTubeId($entity->id(), $youtube_id);

      $this->messenger->addStatus($this->t('Video successfully processed and uploaded to YouTube with ID: @id', ['@id' => $youtube_id]));
      return TRUE;
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error processing entity: @error', ['@error' => $e->getMessage()]);
      $this->messenger->addError($this->t('Error processing the video: @error', ['@error' => $e->getMessage()]));
      return FALSE;
    }
  }

  /**
   * Updates the consent status for a VideoJS Media entity's metadata.
   *
   * @param int $videojs_media_id
   *   The VideoJS Media entity ID.
   * @param bool $consent
   *   The consent status.
   *
   * @return bool
   *   TRUE if the update was successful, FALSE otherwise.
   */
  protected function updateConsentStatus($videojs_media_id, $consent) {
    try {
      $this->database->update('skating_video_metadata')
        ->fields([
          'consent_given' => $consent ? 1 : 0,
          'changed' => time(),
        ])
        ->condition('videojs_media_id', $videojs_media_id)
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error updating consent status: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Updates the YouTube ID for a VideoJS Media entity's metadata.
   *
   * @param int $videojs_media_id
   *   The VideoJS Media entity ID.
   * @param string $youtube_id
   *   The YouTube video ID.
   *
   * @return bool
   *   TRUE if the update was successful, FALSE otherwise.
   */
  protected function updateYouTubeId($videojs_media_id, $youtube_id) {
    try {
      $this->database->update('skating_video_metadata')
        ->fields([
          'youtube_id' => $youtube_id,
          'changed' => time(),
        ])
        ->condition('videojs_media_id', $videojs_media_id)
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error updating YouTube ID: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

}
