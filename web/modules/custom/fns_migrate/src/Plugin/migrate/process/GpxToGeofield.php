<?php

declare(strict_types=1);

namespace Drupal\fns_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Convert a GPX file path into a geofield-compatible WKT LINESTRING.
 *
 * Reads a downloaded `.gpx` file from disk and concatenates every `<trkpt>`
 * inside every `<trkseg>` of every `<trk>` into a single WKT
 * `LINESTRING(lon lat, lon lat, …)` string suitable for the
 * {@link https://www.drupal.org/project/geofield geofield} module's
 * `geofield_value` process.
 *
 * Handles three real-world variants encountered on the legacy site:
 *  - GPX 1.1 with the canonical xmlns
 *    (`http://www.topografix.com/GPX/1/1`).
 *  - GPX 1.0 with the older xmlns
 *    (`http://www.topografix.com/GPX/1/0`).
 *  - GPX exported without a default namespace at all (some hand-rolled
 *    routes from the v1 site).
 *
 * Multi-track files are concatenated in document order — all tracks are
 * treated as one logical route because that is how the legacy `route`
 * content type rendered them.
 *
 * Configuration:
 *   skip_on_empty: When TRUE (default), an empty/missing file or a GPX
 *                  containing zero track points is skipped via
 *                  MigrateSkipProcessException. When FALSE the empty case
 *                  surfaces as a MigrateException so the row fails loudly.
 *
 * Example:
 * @code
 * field_route_geometry/value:
 *   plugin: gpx_to_geofield
 *   source: gpx_file_path
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "gpx_to_geofield"
 * )
 */
class GpxToGeofield extends ProcessPluginBase {

  /**
   * Known GPX default namespaces.
   */
  protected const GPX_NAMESPACES = [
    'http://www.topografix.com/GPX/1/1',
    'http://www.topografix.com/GPX/1/0',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || $value === '') {
      return $this->handleEmpty('GPX file path is empty.');
    }

    $path = (string) $value;
    if (!is_file($path) || !is_readable($path)) {
      return $this->handleEmpty(sprintf('GPX file "%s" is missing or unreadable.', $path));
    }

    $contents = @file_get_contents($path);
    if ($contents === FALSE || $contents === '') {
      return $this->handleEmpty(sprintf('GPX file "%s" could not be read.', $path));
    }

    $points = $this->extractTrackPoints($contents);
    if ($points === []) {
      return $this->handleEmpty(sprintf('GPX file "%s" contains no track points.', $path));
    }

    return $this->toLinestring($points);
  }

  /**
   * Parse the GPX XML and return an ordered list of [lon, lat] pairs.
   *
   * The legacy export pipeline emits both namespaced and bare GPX, so we
   * try the standard 1.1/1.0 namespaces first and fall back to a
   * namespace-stripped pass for hand-rolled documents.
   *
   * @return array<int, array{0: float, 1: float}>
   *   Ordered list of [longitude, latitude] pairs in document order.
   */
  protected function extractTrackPoints(string $xml): array {
    $previous = libxml_use_internal_errors(TRUE);
    try {
      $element = simplexml_load_string($xml);
      if ($element === FALSE) {
        return [];
      }

      $points = $this->collectFromNamespaces($element);
      if ($points !== []) {
        return $points;
      }

      // Fall back to a namespace-stripped parse so bare GPX still works.
      $stripped = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml, 1);
      if ($stripped !== NULL && $stripped !== $xml) {
        $bare = simplexml_load_string($stripped);
        if ($bare !== FALSE) {
          return $this->collectBare($bare);
        }
      }
      return $this->collectBare($element);
    }
    finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
  }

  /**
   * Collect track points using the canonical GPX namespaces via XPath.
   *
   * @return array<int, array{0: float, 1: float}>
   *   Ordered list of [longitude, latitude] pairs.
   */
  protected function collectFromNamespaces(\SimpleXMLElement $element): array {
    $namespaces = $element->getDocNamespaces(TRUE);
    foreach (self::GPX_NAMESPACES as $namespace) {
      if (!in_array($namespace, $namespaces, TRUE)) {
        continue;
      }
      $element->registerXPathNamespace('gpx', $namespace);
      $nodes = $element->xpath('//gpx:trk/gpx:trkseg/gpx:trkpt');
      if ($nodes === FALSE || $nodes === []) {
        continue;
      }
      $points = [];
      foreach ($nodes as $node) {
        $point = $this->pointFromAttributes($node);
        if ($point !== NULL) {
          $points[] = $point;
        }
      }
      if ($points !== []) {
        return $points;
      }
    }
    return [];
  }

  /**
   * Collect track points from a non-namespaced GPX document.
   *
   * @return array<int, array{0: float, 1: float}>
   *   Ordered list of [longitude, latitude] pairs.
   */
  protected function collectBare(\SimpleXMLElement $element): array {
    $points = [];
    foreach ($element->trk as $track) {
      foreach ($track->trkseg as $segment) {
        foreach ($segment->trkpt as $point) {
          $pair = $this->pointFromAttributes($point);
          if ($pair !== NULL) {
            $points[] = $pair;
          }
        }
      }
    }
    return $points;
  }

  /**
   * Read `lat` and `lon` attributes off a `<trkpt>` element.
   *
   * Skips points that do not parse cleanly as floats or fall outside the
   * valid WGS-84 range — corrupt GPS samples occasionally appear in
   * legacy uploads and would otherwise produce invalid WKT.
   *
   * @return array{0: float, 1: float}|null
   *   The [longitude, latitude] pair, or NULL when the point is invalid.
   */
  protected function pointFromAttributes(\SimpleXMLElement $node): ?array {
    $attributes = $node->attributes();
    if ($attributes === NULL) {
      return NULL;
    }
    $lat = isset($attributes['lat']) ? (string) $attributes['lat'] : '';
    $lon = isset($attributes['lon']) ? (string) $attributes['lon'] : '';
    if ($lat === '' || $lon === '' || !is_numeric($lat) || !is_numeric($lon)) {
      return NULL;
    }
    $latF = (float) $lat;
    $lonF = (float) $lon;
    if ($latF < -90.0 || $latF > 90.0 || $lonF < -180.0 || $lonF > 180.0) {
      return NULL;
    }
    return [$lonF, $latF];
  }

  /**
   * Format an ordered list of [lon, lat] pairs as a WKT LINESTRING.
   *
   * Coordinates are emitted with up to 7 decimal places (≈1 cm precision)
   * and trailing zeros stripped to keep the stored value compact.
   *
   * @param array<int, array{0: float, 1: float}> $points
   *   Ordered list of [longitude, latitude] pairs.
   */
  protected function toLinestring(array $points): string {
    $formatted = [];
    foreach ($points as [$lon, $lat]) {
      $formatted[] = $this->formatCoordinate($lon) . ' ' . $this->formatCoordinate($lat);
    }
    return 'LINESTRING(' . implode(', ', $formatted) . ')';
  }

  /**
   * Format a coordinate without scientific notation or trailing zeros.
   */
  protected function formatCoordinate(float $value): string {
    $formatted = rtrim(rtrim(sprintf('%.7F', $value), '0'), '.');
    return $formatted === '' || $formatted === '-' ? '0' : $formatted;
  }

  /**
   * Resolve the empty/error path according to the `skip_on_empty` flag.
   *
   * @throws \Drupal\migrate\MigrateException
   *   When `skip_on_empty` is FALSE.
   */
  protected function handleEmpty(string $message): ?string {
    if (!empty($this->configuration['skip_on_empty']) || !array_key_exists('skip_on_empty', $this->configuration)) {
      // Default behaviour: yield NULL so downstream `skip_on_empty` /
      // optional-field semantics in the migration YAML can decide what to do.
      return NULL;
    }
    throw new MigrateException($message);
  }

}
