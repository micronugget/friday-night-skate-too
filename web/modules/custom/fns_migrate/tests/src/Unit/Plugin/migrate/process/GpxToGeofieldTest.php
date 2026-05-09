<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\fns_migrate\Plugin\migrate\process\GpxToGeofield;
use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * Unit tests for the GpxToGeofield migrate process plugin.
 *
 * Uses three fixture GPX files under tests/fixtures/gpx/:
 *  - single-track-gpx11.gpx  — GPX 1.1 namespace, one track.
 *  - multi-track-gpx10.gpx   — GPX 1.0 namespace, two tracks concatenated.
 *  - bare-no-namespace.gpx   — No xmlns declaration (hand-rolled export).
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\process\GpxToGeofield
 */
class GpxToGeofieldTest extends MigrateTestCase {

  /**
   * Fixture directory.
   */
  protected string $fixtureDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fixtureDir = dirname(__DIR__, 5) . '/fixtures/gpx';
  }

  /**
   * Build a plugin instance with the given configuration.
   *
   * @param array $configuration
   *   Plugin configuration overrides.
   */
  protected function buildPlugin(array $configuration = []): GpxToGeofield {
    return new GpxToGeofield(
      $configuration,
      'gpx_to_geofield',
      [],
    );
  }

  /**
   * @covers ::transform
   * @covers ::extractTrackPoints
   * @covers ::collectFromNamespaces
   */
  public function testSingleTrackGpx11(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      $this->fixtureDir . '/single-track-gpx11.gpx',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    $this->assertSame(
      'LINESTRING(-0.1278 51.5074, -0.1265 51.508, -0.125 51.509)',
      $result
    );
  }

  /**
   * @covers ::transform
   * @covers ::extractTrackPoints
   * @covers ::collectFromNamespaces
   */
  public function testMultiTrackGpx10(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      $this->fixtureDir . '/multi-track-gpx10.gpx',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    // Both tracks concatenated in document order.
    $this->assertSame(
      'LINESTRING(-0.12 51.51, -0.119 51.511, -0.118 51.512, -0.117 51.513)',
      $result
    );
  }

  /**
   * @covers ::transform
   * @covers ::extractTrackPoints
   * @covers ::collectBare
   */
  public function testBareNoNamespace(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      $this->fixtureDir . '/bare-no-namespace.gpx',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    $this->assertSame(
      'LINESTRING(-0.14 51.49, -0.139 51.491, -0.138 51.492)',
      $result
    );
  }

  /**
   * @covers ::transform
   */
  public function testEmptyPathReturnsNullByDefault(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      '',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    $this->assertNull($result);
  }

  /**
   * @covers ::transform
   */
  public function testNullPathReturnsNullByDefault(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      NULL,
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    $this->assertNull($result);
  }

  /**
   * @covers ::transform
   * @covers ::handleEmpty
   */
  public function testEmptyPathThrowsWhenSkipOnEmptyFalse(): void {
    $plugin = $this->buildPlugin(['skip_on_empty' => FALSE]);
    $this->expectException(MigrateException::class);
    $plugin->transform(
      '',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
  }

  /**
   * @covers ::transform
   */
  public function testMissingFileReturnsNull(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      '/tmp/does-not-exist-fns-migrate-test.gpx',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    $this->assertNull($result);
  }

  /**
   * @covers ::pointFromAttributes
   */
  public function testCorruptPointsAreSkipped(): void {
    $gpx = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="FNS Test" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg>
    <trkpt lat="999" lon="0"/>
    <trkpt lat="51.5" lon="-0.1"/>
    <trkpt lat="0" lon="999"/>
  </trkseg></trk>
</gpx>
XML;
    $tmp = tempnam(sys_get_temp_dir(), 'fns_gpx_') . '.gpx';
    file_put_contents($tmp, $gpx);
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      $tmp,
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    unlink($tmp);
    // Only the valid point survives; a single point still forms a LINESTRING.
    $this->assertSame('LINESTRING(-0.1 51.5)', $result);
  }

  /**
   * @covers ::formatCoordinate
   */
  public function testTrailingZerosStripped(): void {
    $gpx = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="FNS Test" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg>
    <trkpt lat="51.5000000" lon="-0.1000000"/>
  </trkseg></trk>
</gpx>
XML;
    $tmp = tempnam(sys_get_temp_dir(), 'fns_gpx_') . '.gpx';
    file_put_contents($tmp, $gpx);
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      $tmp,
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route_geometry'
    );
    unlink($tmp);
    $this->assertSame('LINESTRING(-0.1 51.5)', $result);
  }

  /**
   * Return a minimal MigrateExecutable mock.
   */
  protected function getMigrateExecutable(): MigrateExecutableInterface {
    return $this->createMock(MigrateExecutableInterface::class);
  }

  /**
   * Return a minimal Row mock.
   */
  protected function getRow(): Row {
    return $this->createMock(Row::class);
  }

}
