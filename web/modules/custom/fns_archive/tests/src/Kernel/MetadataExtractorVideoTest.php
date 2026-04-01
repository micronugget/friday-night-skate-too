<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\fns_archive\Service\MetadataExtractor;

/**
 * Kernel tests for MetadataExtractor::extractFromVideo().
 *
 * @group fns_archive
 * @coversDefaultClass \Drupal\fns_archive\Service\MetadataExtractor
 */
class MetadataExtractorVideoTest extends KernelTestBase {

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
   * The metadata extractor service.
   */
  protected MetadataExtractor $extractor;

  /**
   * Absolute path to the GPS-tagged MP4 fixture.
   */
  protected string $fixtureMp4;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->extractor = $this->container->get('fns_archive.metadata_extractor');
    $this->fixtureMp4 = dirname(__DIR__, 2) . '/fixtures/gps_tagged.mp4';
  }

  /**
   * @covers ::extractFromVideo
   */
  public function testWktFormat(): void {
    $result = $this->extractor->extractFromVideo($this->fixtureMp4);
    $this->assertNotNull($result['gps_wkt'], 'GPS WKT should not be null for GPS-tagged video.');
    $this->assertMatchesRegularExpression('/^POINT\(-?\d+\.\d+ -?\d+\.\d+\)$/', $result['gps_wkt']);
  }

  /**
   * @covers ::extractFromVideo
   */
  public function testCoordinateAccuracy(): void {
    $result = $this->extractor->extractFromVideo($this->fixtureMp4);
    $this->assertNotNull($result['gps_wkt']);
    // Parse lon/lat from POINT(lon lat).
    preg_match('/POINT\(([+-]?\d+\.\d+) ([+-]?\d+\.\d+)\)/', $result['gps_wkt'], $m);
    $lon = (float) $m[1];
    $lat = (float) $m[2];
    $this->assertEqualsWithDelta(139.7671, $lon, 0.01, 'Longitude should be ~139.7671');
    $this->assertEqualsWithDelta(35.6812, $lat, 0.01, 'Latitude should be ~35.6812');
  }

  /**
   * @covers ::extractFromVideo
   */
  public function testMetadataKeysPresent(): void {
    $result = $this->extractor->extractFromVideo($this->fixtureMp4);
    $this->assertIsArray($result['metadata']);
    $this->assertNotEmpty($result['metadata']);
    $this->assertArrayHasKey('location', $result['metadata']);
  }

  /**
   * @covers ::extractFromVideo
   */
  public function testMissingFileReturnsNulls(): void {
    $result = $this->extractor->extractFromVideo('/nonexistent/path/video.mp4');
    $this->assertNull($result['gps_wkt']);
    $this->assertNull($result['timecode']);
    $this->assertEmpty($result['metadata']);
  }

  /**
   * @covers ::extractFromVideo
   */
  public function testNoGpsVideoReturnsNullWkt(): void {
    // Use the JPEG fixture — ffprobe will parse it but find no location tag.
    $jpegFixture = dirname(__DIR__, 2) . '/fixtures/gps_tagged.jpg';
    $result = $this->extractor->extractFromVideo($jpegFixture);
    $this->assertNull($result['gps_wkt']);
  }

  /**
   * @covers ::parseIso6709
   */
  public function testIso6709WithAltitude(): void {
    $result = $this->extractor->extractFromVideo($this->fixtureMp4);
    // Verify the parser handles the trailing slash in "+35.6812+139.7671/".
    $this->assertNotNull($result['gps_wkt']);
    $this->assertStringStartsWith('POINT(', $result['gps_wkt']);
  }

}
