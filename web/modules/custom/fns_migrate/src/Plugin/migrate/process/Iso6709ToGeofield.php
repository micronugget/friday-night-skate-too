<?php

declare(strict_types=1);

namespace Drupal\fns_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Convert an ISO 6709 location string into a geofield-compatible WKT POINT.
 *
 * Handles the compact annex-H format used by Apple QuickTime and ffprobe:
 *   `+35.6812+139.7671/`
 *   `+35.6812+139.7671+100/`  (with altitude — altitude is ignored)
 *
 * Output: `POINT(lon lat)` — longitude first, matching WKT convention and
 * the geofield module's expected input order.
 *
 * Configuration:
 *   skip_on_empty: When TRUE (default), an empty or unparseable value is
 *                  skipped gracefully (returns NULL). When FALSE the bad
 *                  value surfaces as a MigrateException.
 *
 * Example:
 * @code
 * field_location/value:
 *   plugin: iso6709_to_geofield
 *   source: location_tag
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "iso6709_to_geofield"
 * )
 */
class Iso6709ToGeofield extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || $value === '') {
      return $this->handleEmpty('ISO 6709 location value is empty.');
    }

    $wkt = $this->parse((string) $value);
    if ($wkt === NULL) {
      return $this->handleEmpty(sprintf('Cannot parse ISO 6709 location: "%s".', $value));
    }

    return $wkt;
  }

  /**
   * Parse an ISO 6709 annex-H string into a WKT POINT.
   *
   * @param string $location
   *   ISO 6709 location string, e.g. "+35.6812+139.7671/".
   *
   * @return string|null
   *   WKT POINT string, or NULL if parsing fails.
   */
  public static function parse(string $location): ?string {
    // Pattern: sign + lat + sign + lon (+ optional altitude) + optional slash.
    if (!preg_match(
      '/^([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)(?:[+-]\d+(?:\.\d+)?)?[\/]?$/',
      trim($location),
      $m
    )) {
      return NULL;
    }
    $lat = (float) $m[1];
    $lon = (float) $m[2];
    if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
      return NULL;
    }
    return sprintf('POINT(%s %s)', $lon, $lat);
  }

  /**
   * Resolve the empty/error path according to the skip_on_empty flag.
   *
   * @param string $message
   *   Human-readable reason for the failure.
   *
   * @return null
   *   Always returns NULL when skip_on_empty is TRUE (default).
   *
   * @throws \Drupal\migrate\MigrateException
   *   When skip_on_empty is explicitly FALSE.
   */
  protected function handleEmpty(string $message): ?string {
    if (!empty($this->configuration['skip_on_empty']) || !array_key_exists('skip_on_empty', $this->configuration)) {
      return NULL;
    }
    throw new MigrateException($message);
  }

}
