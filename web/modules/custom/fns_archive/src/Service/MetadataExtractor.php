<?php

declare(strict_types=1);

namespace Drupal\fns_archive\Service;

use Psr\Log\LoggerInterface;

/**
 * Extracts GPS EXIF metadata from images and video files.
 */
class MetadataExtractor {

  /**
   * Constructs a MetadataExtractor instance.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Extracts metadata from an image file.
   *
   * Reads EXIF data using PHP's exif_read_data() and returns an array with:
   * - 'gps_wkt': WKT POINT string (e.g. "POINT(139.7671 35.6812)") or NULL.
   * - 'metadata': associative array of raw EXIF data.
   *
   * @param string $filepath
   *   Absolute path to the JPEG image file.
   *
   * @return array{gps_wkt: string|null, metadata: array<string, mixed>}
   *   Extracted metadata.
   */
  public function extractFromImage(string $filepath): array {
    $result = [
      'gps_wkt' => NULL,
      'metadata' => [],
    ];

    if (!is_readable($filepath)) {
      $this->logger->warning('MetadataExtractor: file not readable: @path', ['@path' => $filepath]);
      return $result;
    }

    $exif = @exif_read_data($filepath, 'GPS', FALSE);
    if ($exif === FALSE) {
      $this->logger->notice('MetadataExtractor: no EXIF data in @path', ['@path' => $filepath]);
      return $result;
    }

    $result['metadata'] = $exif;

    $lat = $this->parseGpsCoordinate(
      $exif['GPSLatitude'] ?? NULL,
      $exif['GPSLatitudeRef'] ?? 'N'
    );
    $lon = $this->parseGpsCoordinate(
      $exif['GPSLongitude'] ?? NULL,
      $exif['GPSLongitudeRef'] ?? 'E'
    );

    if ($lat !== NULL && $lon !== NULL) {
      $result['gps_wkt'] = sprintf('POINT(%s %s)', $lon, $lat);
    }

    return $result;
  }

  /**
   * Extracts metadata from a video file using ffprobe.
   *
   * Reads format tags via ffprobe JSON output and returns an array with:
   * - 'gps_wkt': WKT POINT string or NULL.
   * - 'timecode': timecode string (e.g. "10:00:00:00") or NULL.
   * - 'metadata': associative array of raw format tags.
   *
   * @param string $filepath
   *   Absolute path to the video file.
   * @param string $ffprobe
   *   Path to the ffprobe binary (default: 'ffprobe').
   *
   * @return array{gps_wkt: string|null, timecode: string|null, metadata: array<string, mixed>}
   *   Extracted metadata.
   */
  public function extractFromVideo(string $filepath, string $ffprobe = 'ffprobe'): array {
    $result = [
      'gps_wkt' => NULL,
      'timecode' => NULL,
      'metadata' => [],
    ];
    if (!is_readable($filepath)) {
      $this->logger->warning('MetadataExtractor: file not readable: @path', ['@path' => $filepath]);
      return $result;
    }
    $cmd = escapeshellarg($ffprobe) . ' -v quiet -print_format json -show_format ' . escapeshellarg($filepath);
    $output = shell_exec($cmd);
    if ($output === NULL || $output === '') {
      $this->logger->warning('MetadataExtractor: ffprobe returned no output for @path', ['@path' => $filepath]);
      return $result;
    }
    $data = json_decode($output, TRUE);
    if (!is_array($data) || empty($data['format']['tags'])) {
      return $result;
    }
    $tags = $data['format']['tags'];
    $result['metadata'] = $tags;
    // Extract timecode.
    if (!empty($tags['timecode'])) {
      $result['timecode'] = $tags['timecode'];
    }
    // Extract GPS from ISO 6709 location tag (e.g. "+35.6812+139.7671/").
    $location = $tags['location'] ?? $tags['com.apple.quicktime.location.ISO6709'] ?? NULL;
    if ($location !== NULL) {
      $result['gps_wkt'] = $this->parseIso6709($location);
    }
    return $result;
  }

  /**
   * Parses an ISO 6709 location string into a WKT POINT.
   *
   * Handles formats like "+35.6812+139.7671/" or "+35.6812+139.7671+100/".
   *
   * @param string $location
   *   ISO 6709 location string.
   *
   * @return string|null
   *   WKT POINT string, or NULL if parsing fails.
   */
  protected function parseIso6709(string $location): ?string {
    // Pattern: sign + lat + sign + lon (+ optional altitude) + optional slash.
    if (!preg_match('/^([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)(?:[+-]\d+(?:\.\d+)?)?[\/]?$/', $location, $m)) {
      return NULL;
    }
    $lat = (float) $m[1];
    $lon = (float) $m[2];
    return sprintf('POINT(%s %s)', $lon, $lat);
  }

  /**
   * Converts an EXIF GPS coordinate array to a decimal float.
   *
   * @param array<int, string>|null $parts
   *   Array of three rational strings: degrees, minutes, seconds.
   * @param string $ref
   *   Hemisphere reference: 'N', 'S', 'E', or 'W'.
   *
   * @return float|null
   *   Decimal degrees, or NULL if input is invalid.
   */
  protected function parseGpsCoordinate(?array $parts, string $ref): ?float {
    if (empty($parts) || count($parts) < 3) {
      return NULL;
    }

    $degrees = $this->rationalToFloat($parts[0]);
    $minutes = $this->rationalToFloat($parts[1]);
    $seconds = $this->rationalToFloat($parts[2]);

    if ($degrees === NULL || $minutes === NULL || $seconds === NULL) {
      return NULL;
    }

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($ref === 'S' || $ref === 'W') {
      $decimal *= -1;
    }

    return $decimal;
  }

  /**
   * Converts an EXIF rational string (e.g. "35/1") to a float.
   *
   * @param string $rational
   *   Rational number as "numerator/denominator".
   *
   * @return float|null
   *   Float value, or NULL if the string is not a valid rational.
   */
  protected function rationalToFloat(string $rational): ?float {
    $parts = explode('/', $rational);
    if (count($parts) !== 2 || (float) $parts[1] === 0.0) {
      return NULL;
    }
    return (float) $parts[0] / (float) $parts[1];
  }

}
