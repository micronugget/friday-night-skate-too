<?php

declare(strict_types=1);

namespace Drupal\skating_video_uploader\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\videojs_media\VideoJsMediaInterface;

/**
 * Service for extracting metadata from video and image files.
 */
class MetadataExtractor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new MetadataExtractor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface $file_system,
    Connection $database,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory->get('skating_video_uploader');
    $this->fileSystem = $file_system;
    $this->database = $database;
  }

  /**
   * Extracts metadata from a video file and stores it in the database.
   *
   * @param \Drupal\videojs_media\VideoJsMediaInterface $videojs_media
   *   The VideoJS Media entity containing the video file.
   *
   * @return array|null
   *   An array of metadata or NULL if extraction failed.
   */
  public function extractMetadata(VideoJsMediaInterface $videojs_media) {
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

      // Extract metadata using ffprobe command.
      $metadata = $this->extractWithFfprobe($local_path);
      if (!$metadata) {
        $this->loggerFactory->error('Failed to extract metadata from @uri', ['@uri' => $uri]);
        return NULL;
      }

      // Add VideoJS Media and file IDs.
      $metadata['videojs_media_id'] = $videojs_media->id();
      $metadata['file_id'] = $file->id();
      $metadata['created'] = time();
      $metadata['changed'] = time();

      // Store the metadata in the database.
      $this->storeMetadataToDatabase($metadata);

      return $metadata;
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error extracting metadata: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Extracts metadata from a video file using ffprobe.
   *
   * @param string $file_path
   *   The local path to the video file.
   *
   * @return array|null
   *   An array of metadata or NULL if extraction failed.
   */
  protected function extractWithFfprobe(string $file_path): ?array {
    // Escape the file path for shell command.
    $escaped_path = escapeshellarg($file_path);

    // Run ffprobe to get metadata in JSON format.
    // Note: When Drupal runs in DDEV, PHP code already executes inside the container,
    // so we don't need 'ddev exec' prefix. The command runs directly in the container.
    $command = "ffprobe -v quiet -print_format json -show_format -show_streams {$escaped_path} 2>&1";

    $output = shell_exec($command);

    if (!$output) {
      $this->loggerFactory->error('ffprobe command failed or produced no output');
      return NULL;
    }

    // Decode the JSON output.
    $data = json_decode($output, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->loggerFactory->error('Failed to decode ffprobe output: @error', ['@error' => json_last_error_msg()]);
      return NULL;
    }

    $metadata = [];

    // Extract basic format metadata.
    if (isset($data['format'])) {
      $format = $data['format'];
      if (isset($format['duration'])) {
        $metadata['duration'] = (float) $format['duration'];
      }

      // Extract GPS and creation time from format tags.
      if (isset($format['tags'])) {
        $tags = $format['tags'];

        // Try different tag names for creation time.
        foreach (['creation_time', 'com.apple.quicktime.creationdate', 'date'] as $tag_name) {
          if (isset($tags[$tag_name])) {
            $metadata['creation_time'] = $tags[$tag_name];
            break;
          }
        }

        // Extract GPS metadata - format: "+35.6812+139.7671/" or similar.
        if (isset($tags['location'])) {
          $gps_data = $this->parseGpsLocation($tags['location']);
          if ($gps_data) {
            $metadata = array_merge($metadata, $gps_data);
          }
        }

        // Try Apple's location format.
        if (isset($tags['com.apple.quicktime.location.ISO6709'])) {
          $gps_data = $this->parseGpsLocation($tags['com.apple.quicktime.location.ISO6709']);
          if ($gps_data) {
            $metadata = array_merge($metadata, $gps_data);
          }
        }
      }
    }

    // Extract timecode data from video stream.
    if (isset($data['streams']) && is_array($data['streams'])) {
      $timecode_data = [];
      foreach ($data['streams'] as $stream) {
        if (isset($stream['codec_type']) && $stream['codec_type'] === 'video') {
          // Extract timecode if available.
          if (isset($stream['tags']['timecode'])) {
            $timecode_data['timecode'] = $stream['tags']['timecode'];
          }
          // Add frame rate information.
          if (isset($stream['r_frame_rate'])) {
            $timecode_data['frame_rate'] = $stream['r_frame_rate'];
          }
          break;
        }
      }

      if (!empty($timecode_data)) {
        $metadata['timecode_data'] = serialize($timecode_data);
      }
    }

    return $metadata;
  }

  /**
   * Parses GPS location string into latitude, longitude, and altitude.
   *
   * @param string $location
   *   The location string (e.g., "+35.6812+139.7671/" or "+35.6812+139.7671+100.5/").
   *
   * @return array
   *   An array with latitude, longitude, and optionally altitude.
   */
  protected function parseGpsLocation(string $location): array {
    $result = [];

    // Parse location string - format: "+35.6812+139.7671/" or "+35.6812+139.7671+100.5/".
    if (preg_match('/([+-]\d+\.\d+)([+-]\d+\.\d+)([+-]\d+\.\d+)?/', $location, $matches)) {
      $result['latitude'] = (float) $matches[1];
      $result['longitude'] = (float) $matches[2];
      if (isset($matches[3]) && $matches[3] !== '') {
        $result['altitude'] = (float) $matches[3];
      }
    }

    return $result;
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

  /**
   * Stores metadata in the database.
   *
   * @param array $metadata
   *   The metadata to store.
   *
   * @return int|null
   *   The ID of the stored metadata or NULL if storage failed.
   */
  protected function storeMetadataToDatabase(array $metadata) {
    try {
      // Check if metadata already exists for this VideoJS Media entity.
      $existing = $this->database->select('skating_video_metadata', 'm')
        ->fields('m', ['id'])
        ->condition('videojs_media_id', $metadata['videojs_media_id'])
        ->execute()
        ->fetchField();

      if ($existing) {
        // Update existing metadata.
        $metadata['changed'] = time();
        $this->database->update('skating_video_metadata')
          ->fields($metadata)
          ->condition('id', $existing)
          ->execute();
        return $existing;
      }
      else {
        // Insert new metadata.
        return $this->database->insert('skating_video_metadata')
          ->fields($metadata)
          ->execute();
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error storing metadata: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Extracts metadata from an image file using EXIF data.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity containing the image.
   *
   * @return array|null
   *   An array of metadata or NULL if extraction failed.
   */
  public function extractImageMetadata(FileInterface $file): ?array {
    try {
      // Check if EXIF extension is available.
      if (!function_exists('exif_read_data')) {
        $this->loggerFactory->warning('EXIF extension not available for metadata extraction');
        return NULL;
      }

      // Get the file URI and convert it to a local path.
      $uri = $file->getFileUri();
      $local_path = $this->fileSystem->realpath($uri);

      if (!$local_path || !file_exists($local_path)) {
        $this->loggerFactory->error('Image file not found at @uri', ['@uri' => $uri]);
        return NULL;
      }

      // Read EXIF data from the image.
      $exif_data = @exif_read_data($local_path, NULL, TRUE);
      if ($exif_data === FALSE) {
        $this->loggerFactory->warning('Failed to read EXIF data from @uri', ['@uri' => $uri]);
        return NULL;
      }

      $metadata = [];

      // Extract GPS coordinates.
      if (isset($exif_data['GPS'])) {
        $gps = $this->extractGpsFromExif($exif_data['GPS']);
        if ($gps) {
          $metadata = array_merge($metadata, $gps);
        }
      }

      // Extract timestamp.
      if (isset($exif_data['EXIF']['DateTimeOriginal'])) {
        $metadata['timestamp'] = $exif_data['EXIF']['DateTimeOriginal'];
      }
      elseif (isset($exif_data['IFD0']['DateTime'])) {
        $metadata['timestamp'] = $exif_data['IFD0']['DateTime'];
      }

      // Extract camera information.
      if (isset($exif_data['IFD0']['Make'])) {
        $metadata['camera_make'] = $exif_data['IFD0']['Make'];
      }
      if (isset($exif_data['IFD0']['Model'])) {
        $metadata['camera_model'] = $exif_data['IFD0']['Model'];
      }

      // Extract technical details.
      if (isset($exif_data['EXIF']['ISOSpeedRatings'])) {
        $metadata['iso'] = $exif_data['EXIF']['ISOSpeedRatings'];
      }
      if (isset($exif_data['EXIF']['FNumber'])) {
        $metadata['aperture'] = $this->convertExifRational($exif_data['EXIF']['FNumber']);
      }
      if (isset($exif_data['EXIF']['FocalLength'])) {
        $metadata['focal_length'] = $this->convertExifRational($exif_data['EXIF']['FocalLength']);
      }
      if (isset($exif_data['EXIF']['ExposureTime'])) {
        $metadata['exposure_time'] = $exif_data['EXIF']['ExposureTime'];
      }

      return $metadata;
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error extracting image metadata: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Extracts GPS coordinates from EXIF GPS data.
   *
   * @param array $gps_data
   *   The GPS section of EXIF data.
   *
   * @return array
   *   An array with latitude, longitude, and optionally altitude.
   */
  protected function extractGpsFromExif(array $gps_data): array {
    $result = [];

    // Extract latitude.
    if (isset($gps_data['GPSLatitude'], $gps_data['GPSLatitudeRef'])) {
      $lat = $this->convertGpsCoordinate($gps_data['GPSLatitude']);
      if ($gps_data['GPSLatitudeRef'] === 'S') {
        $lat = -$lat;
      }
      $result['latitude'] = $lat;
    }

    // Extract longitude.
    if (isset($gps_data['GPSLongitude'], $gps_data['GPSLongitudeRef'])) {
      $lon = $this->convertGpsCoordinate($gps_data['GPSLongitude']);
      if ($gps_data['GPSLongitudeRef'] === 'W') {
        $lon = -$lon;
      }
      $result['longitude'] = $lon;
    }

    // Extract altitude.
    if (isset($gps_data['GPSAltitude'])) {
      $altitude = $this->convertExifRational($gps_data['GPSAltitude']);
      if (isset($gps_data['GPSAltitudeRef']) && $gps_data['GPSAltitudeRef'] === '1') {
        $altitude = -$altitude;
      }
      $result['altitude'] = $altitude;
    }

    return $result;
  }

  /**
   * Converts GPS coordinate from EXIF format to decimal degrees.
   *
   * @param array $coordinate
   *   Array of three rational numbers [degrees, minutes, seconds].
   *
   * @return float
   *   The coordinate in decimal degrees.
   */
  protected function convertGpsCoordinate(array $coordinate): float {
    // Validate array has required elements.
    if (count($coordinate) < 3) {
      return 0.0;
    }

    $degrees = $this->convertExifRational($coordinate[0]);
    $minutes = $this->convertExifRational($coordinate[1]);
    $seconds = $this->convertExifRational($coordinate[2]);

    return $degrees + ($minutes / 60) + ($seconds / 3600);
  }

  /**
   * Converts EXIF rational number to float.
   *
   * @param string|float|int $rational
   *   The rational number in "numerator/denominator" format or a numeric value.
   *
   * @return float
   *   The decimal value.
   */
  protected function convertExifRational($rational): float {
    if (is_float($rational) || is_int($rational)) {
      return (float) $rational;
    }

    if (is_string($rational)) {
      // Check if it's a rational number (e.g., "1/60").
      if (str_contains($rational, '/')) {
        $parts = explode('/', $rational);
        if (count($parts) === 2 && $parts[1] !== '0') {
          return (float) $parts[0] / (float) $parts[1];
        }
      }
      // It's a plain string number.
      else {
        return (float) $rational;
      }
    }

    return 0.0;
  }

  /**
   * Extracts metadata from a video file using File entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity containing the video.
   *
   * @return array|null
   *   An array of metadata or NULL if extraction failed.
   */
  public function extractVideoMetadata(FileInterface $file): ?array {
    try {
      // Get the file URI and convert it to a local path.
      $uri = $file->getFileUri();
      $local_path = $this->fileSystem->realpath($uri);

      if (!$local_path || !file_exists($local_path)) {
        $this->loggerFactory->error('Video file not found at @uri', ['@uri' => $uri]);
        return NULL;
      }

      // Use the existing extractWithFfprobe method.
      return $this->extractWithFfprobe($local_path);
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error extracting video metadata: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Stores metadata to a node's field_metadata as JSON.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity (typically archive_media).
   * @param array $metadata
   *   The metadata array to store.
   *
   * @return bool
   *   TRUE if metadata was stored successfully, FALSE otherwise.
   */
  public function storeMetadata(NodeInterface $node, array $metadata): bool {
    try {
      if (!$node->hasField('field_metadata')) {
        $this->loggerFactory->warning('Node @id does not have field_metadata field', ['@id' => $node->id()]);
        return FALSE;
      }

      // Convert metadata to JSON.
      $json = json_encode($metadata, JSON_PRETTY_PRINT);
      if ($json === FALSE) {
        $this->loggerFactory->error('Failed to encode metadata as JSON');
        return FALSE;
      }

      // Store in the field.
      $node->set('field_metadata', $json);

      return TRUE;
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error storing metadata to node: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

}
