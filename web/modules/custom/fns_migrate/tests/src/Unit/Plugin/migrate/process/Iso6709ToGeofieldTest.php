<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\fns_migrate\Plugin\migrate\process\Iso6709ToGeofield;
use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * Unit tests for the Iso6709ToGeofield migrate process plugin.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\process\Iso6709ToGeofield
 */
class Iso6709ToGeofieldTest extends MigrateTestCase {

  /**
   * Build a plugin instance with the given configuration.
   *
   * @param array $configuration
   *   Plugin configuration overrides.
   */
  protected function buildPlugin(array $configuration = []): Iso6709ToGeofield {
    return new Iso6709ToGeofield(
      $configuration,
      'iso6709_to_geofield',
      [],
    );
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

  /**
   * @covers ::transform
   * @covers ::parse
   *
   * @dataProvider provideValidLocations
   */
  public function testValidLocations(string $input, string $expected): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(
      $input,
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_location'
    );
    $this->assertSame($expected, $result);
  }

  /**
   * Data provider for valid ISO 6709 strings.
   *
   * @return array<string, array{string, string}>
   *   Keyed by label; each value is [input, expected WKT].
   */
  public static function provideValidLocations(): array {
    return [
      'lat+lon no altitude no slash' => [
        '+35.6812+139.7671',
        'POINT(139.7671 35.6812)',
      ],
      'lat+lon with trailing slash' => [
        '+35.6812+139.7671/',
        'POINT(139.7671 35.6812)',
      ],
      'lat+lon+altitude with slash' => [
        '+35.6812+139.7671+100/',
        'POINT(139.7671 35.6812)',
      ],
      'negative lat and lon' => [
        '-33.8688+151.2093/',
        'POINT(151.2093 -33.8688)',
      ],
      'both negative' => [
        '-51.5074-0.1278/',
        'POINT(-0.1278 -51.5074)',
      ],
      'leading/trailing whitespace' => [
        '  +51.5074-0.1278/  ',
        'POINT(-0.1278 51.5074)',
      ],
    ];
  }

  /**
   * @covers ::transform
   */
  public function testEmptyValueReturnsNullByDefault(): void {
    $plugin = $this->buildPlugin();
    $this->assertNull(
      $plugin->transform('', $this->getMigrateExecutable(), $this->getRow(), 'field_location')
    );
  }

  /**
   * @covers ::transform
   */
  public function testNullValueReturnsNullByDefault(): void {
    $plugin = $this->buildPlugin();
    $this->assertNull(
      $plugin->transform(NULL, $this->getMigrateExecutable(), $this->getRow(), 'field_location')
    );
  }

  /**
   * @covers ::transform
   * @covers ::parse
   */
  public function testUnparsableValueReturnsNullByDefault(): void {
    $plugin = $this->buildPlugin();
    $this->assertNull(
      $plugin->transform('not-a-location', $this->getMigrateExecutable(), $this->getRow(), 'field_location')
    );
  }

  /**
   * @covers ::handleEmpty
   */
  public function testEmptyThrowsWhenSkipOnEmptyFalse(): void {
    $plugin = $this->buildPlugin(['skip_on_empty' => FALSE]);
    $this->expectException(MigrateException::class);
    $plugin->transform('', $this->getMigrateExecutable(), $this->getRow(), 'field_location');
  }

  /**
   * @covers ::parse
   */
  public function testOutOfRangeLatReturnsNull(): void {
    $this->assertNull(Iso6709ToGeofield::parse('+91.0+139.7671/'));
  }

  /**
   * @covers ::parse
   */
  public function testOutOfRangeLonReturnsNull(): void {
    $this->assertNull(Iso6709ToGeofield::parse('+35.6812+181.0/'));
  }

}
