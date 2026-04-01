<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Kernel;

use Drupal\fns_archive\Service\MetadataExtractor;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for MetadataExtractor::extractFromImage().
 *
 * Verifies that GPS EXIF data is correctly extracted from a JPEG file and
 * converted to a WKT POINT string suitable for a geofield value.
 *
 * @group fns_archive
 * @coversDefaultClass \Drupal\fns_archive\Service\MetadataExtractor
 */
class MetadataExtractorImageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'fns_archive',
    'content_moderation',
    'workflows',
    'user',
    'node',
    'field',
    'text',
    'system',
  ];

  /**
   * The MetadataExtractor service under test.
   */
  protected MetadataExtractor $extractor;

  /**
   * Absolute path to the GPS-tagged JPEG fixture.
   */
  protected string $fixturePath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->extractor = $this->container->get('fns_archive.metadata_extractor');
    $this->fixturePath = dirname(__DIR__, 2) . '/fixtures/gps_tagged.jpg';
  }

  /**
   * @covers ::extractFromImage
   */
  public function testExtractFromImageReturnsGpsWkt(): void {
    $this->assertFileExists($this->fixturePath, 'GPS fixture JPEG must exist.');

    $result = $this->extractor->extractFromImage($this->fixturePath);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('gps_wkt', $result);
    $this->assertArrayHasKey('metadata', $result);

    $this->assertNotNull($result['gps_wkt'], 'gps_wkt must not be NULL for a GPS-tagged image.');
    $this->assertStringStartsWith('POINT(', $result['gps_wkt'], 'gps_wkt must be a WKT POINT string.');
  }

  /**
   * @covers ::extractFromImage
   */
  public function testExtractFromImageGpsCoordinatesAreAccurate(): void {
    $result = $this->extractor->extractFromImage($this->fixturePath);

    // Fixture encodes lat=35.6812, lon=139.7671.
    // Parse the WKT: POINT(lon lat).
    $this->assertNotNull($result['gps_wkt']);
    preg_match('/POINT\(([0-9.\-]+)\s+([0-9.\-]+)\)/', $result['gps_wkt'], $matches);
    $this->assertCount(3, $matches, 'WKT POINT must contain two coordinates.');

    $lon = (float) $matches[1];
    $lat = (float) $matches[2];

    // Allow ±0.01 degree tolerance for DMS rounding.
    $this->assertEqualsWithDelta(35.6812, $lat, 0.01, 'Latitude must be approximately 35.6812.');
    $this->assertEqualsWithDelta(139.7671, $lon, 0.01, 'Longitude must be approximately 139.7671.');
  }

  /**
   * @covers ::extractFromImage
   */
  public function testExtractFromImagePopulatesMetadata(): void {
    $result = $this->extractor->extractFromImage($this->fixturePath);

    $this->assertNotEmpty($result['metadata'], 'metadata array must not be empty for a GPS-tagged image.');
    $this->assertArrayHasKey('GPSLatitude', $result['metadata'], 'metadata must contain GPSLatitude.');
    $this->assertArrayHasKey('GPSLongitude', $result['metadata'], 'metadata must contain GPSLongitude.');
    $this->assertArrayHasKey('GPSLatitudeRef', $result['metadata'], 'metadata must contain GPSLatitudeRef.');
    $this->assertArrayHasKey('GPSLongitudeRef', $result['metadata'], 'metadata must contain GPSLongitudeRef.');
  }

  /**
   * @covers ::extractFromImage
   */
  public function testExtractFromImageWithNonExistentFileReturnsNullGps(): void {
    $result = $this->extractor->extractFromImage('/tmp/does_not_exist_fns_archive_test.jpg');

    $this->assertNull($result['gps_wkt'], 'gps_wkt must be NULL for a non-existent file.');
    $this->assertEmpty($result['metadata'], 'metadata must be empty for a non-existent file.');
  }

  /**
   * @covers ::extractFromImage
   */
  public function testExtractFromImageWithNoExifReturnsNullGps(): void {
    // Create a plain JPEG with no EXIF data.
    $tmpFile = tempnam(sys_get_temp_dir(), 'fns_no_exif_') . '.jpg';
    $img = imagecreatetruecolor(10, 10);
    imagejpeg($img, $tmpFile);
    imagedestroy($img);

    $result = $this->extractor->extractFromImage($tmpFile);

    $this->assertNull($result['gps_wkt'], 'gps_wkt must be NULL for a JPEG with no GPS EXIF.');

    unlink($tmpFile);
  }

}
