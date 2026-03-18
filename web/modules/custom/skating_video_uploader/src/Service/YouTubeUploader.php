<?php

declare(strict_types=1);

namespace Drupal\skating_video_uploader\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\videojs_media\VideoJsMediaInterface;

/**
 * Service for uploading videos to YouTube.
 */
class YouTubeUploader {

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
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new YouTubeUploader object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger,
    FileSystemInterface $file_system,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory->get('skating_video_uploader');
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->fileSystem = $file_system;
  }

  /**
   * Uploads a video to YouTube.
   *
   * @param \Drupal\videojs_media\VideoJsMediaInterface $videojs_media
   *   The VideoJS Media entity containing the video file.
   * @param array $metadata
   *   The metadata extracted from the video file.
   *
   * @return string|null
   *   The YouTube video ID if upload was successful, NULL otherwise.
   */
  public function uploadVideo(VideoJsMediaInterface $videojs_media, array $metadata) {
    try {
      // Get the file entity from the VideoJS Media entity.
      $file = $this->getFileFromVideoJsMedia($videojs_media);
      if (!$file) {
        $this->loggerFactory->error('No file found in VideoJS Media entity @id', ['@id' => $videojs_media->id()]);
        return NULL;
      }

      // Get the file URI and convert it to a local path.
      $uri = $file->getFileUri();
      $local_path = $this->fileSystem->realpath($uri);

      if (!$local_path || !file_exists($local_path)) {
        $this->loggerFactory->error('File not found at @uri', ['@uri' => $uri]);
        return NULL;
      }

      // Initialize the Google client.
      $client = $this->getAuthorizedClient();
      if (!$client) {
        $this->loggerFactory->error('Failed to initialize Google client');
        return NULL;
      }

      // Create a YouTube service object.
      $youtube = new \Google_Service_YouTube($client);

      // Prepare the video metadata.
      $snippet = new \Google_Service_YouTube_VideoSnippet();
      $snippet->setTitle($videojs_media->getName() ?: 'Skating Video ' . date('Y-m-d H:i:s'));

      // Build description with metadata.
      $description = 'Uploaded from Friday Night Skate Club website';
      if (isset($metadata['latitude']) && isset($metadata['longitude'])) {
        $description .= "\n\nLocation: {$metadata['latitude']}, {$metadata['longitude']}";
      }
      if (isset($metadata['creation_time'])) {
        $description .= "\nRecorded: {$metadata['creation_time']}";
      }
      $snippet->setDescription($description);
      $snippet->setTags(['skating', 'friday night skate', 'club']);
      // Sports category.
      $snippet->setCategoryId('17');

      // Set the video status.
      $status = new \Google_Service_YouTube_VideoStatus();
      // 'private', 'public', or 'unlisted'
      $status->setPrivacyStatus('unlisted');

      // Create a video resource with the snippet and status.
      $video = new \Google_Service_YouTube_Video();
      $video->setSnippet($snippet);
      $video->setStatus($status);

      // Set up the chunked upload.
      $client->setDefer(TRUE);
      $insertRequest = $youtube->videos->insert('snippet,status', $video);
      $media_file = new \Google_Http_MediaFileUpload(
        $client,
        $insertRequest,
        'video/*',
        NULL,
        TRUE,
      // Chunk size in bytes (1MB)
        1 * 1024 * 1024
      );
      $media_file->setFileSize(filesize($local_path));

      // Upload the file in chunks.
      $upload_status = FALSE;
      $handle = fopen($local_path, 'rb');
      while (!$upload_status && !feof($handle)) {
        $chunk = fread($handle, 1 * 1024 * 1024);
        $upload_status = $media_file->nextChunk($chunk);
      }
      fclose($handle);

      // If upload was successful, return the YouTube video ID.
      if ($upload_status) {
        $youtube_id = $upload_status->getId();
        $this->loggerFactory->notice('Video uploaded to YouTube with ID @id', ['@id' => $youtube_id]);
        return $youtube_id;
      }
      else {
        $this->loggerFactory->error('Failed to upload video to YouTube');
        return NULL;
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error uploading video to YouTube: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Gets an authorized Google client.
   *
   * @return \Google_Client|null
   *   The authorized Google client or NULL if authorization failed.
   */
  protected function getAuthorizedClient() {
    try {
      // Create a Google client.
      $client = new \Google_Client();
      $client->setApplicationName('Friday Night Skate Club Video Uploader');
      $client->setScopes([
        'https://www.googleapis.com/auth/youtube.upload',
      ]);

      // Set the client ID, client secret, and redirect URI from configuration.
      $config = $this->configFactory->get('skating_video_uploader.settings');
      $client->setClientId($config->get('youtube_client_id'));
      $client->setClientSecret($config->get('youtube_client_secret'));
      $client->setRedirectUri($config->get('youtube_redirect_uri'));

      // Set the access token from configuration.
      $access_token = $config->get('youtube_access_token');
      if ($access_token) {
        $client->setAccessToken($access_token);
      }

      // Refresh the token if it's expired.
      if ($client->isAccessTokenExpired()) {
        $refresh_token = $config->get('youtube_refresh_token');
        if ($refresh_token) {
          $client->refreshToken($refresh_token);
          // Save the new access token to configuration.
          $this->saveAccessToken($client->getAccessToken());
        }
        else {
          $this->loggerFactory->error('No refresh token available');
          return NULL;
        }
      }

      return $client;
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error initializing Google client: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Saves the access token to configuration.
   *
   * @param string $access_token
   *   The access token to save.
   */
  protected function saveAccessToken($access_token) {
    $config = $this->configFactory->getEditable('skating_video_uploader.settings');
    $config->set('youtube_access_token', $access_token)->save();
  }

  /**
   * Gets the file entity from a VideoJS Media entity.
   *
   * @param \Drupal\videojs_media\VideoJsMediaInterface $videojs_media
   *   The VideoJS Media entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or NULL if not found.
   */
  protected function getFileFromVideoJsMedia(VideoJsMediaInterface $videojs_media) {
    // Check if this is a local_video or local_audio VideoJS Media entity.
    $bundle = $videojs_media->bundle();
    if ($bundle !== 'local_video' && $bundle !== 'local_audio') {
      return NULL;
    }

    // VideoJS Media uses field_media_file for local video/audio files.
    $field_name = 'field_media_file';

    // Get the file entity from the VideoJS Media entity.
    if ($videojs_media->hasField($field_name) && !$videojs_media->get($field_name)->isEmpty()) {
      $target_id = $videojs_media->get($field_name)->target_id;
      return $this->entityTypeManager->getStorage('file')->load($target_id);
    }

    return NULL;
  }

}
