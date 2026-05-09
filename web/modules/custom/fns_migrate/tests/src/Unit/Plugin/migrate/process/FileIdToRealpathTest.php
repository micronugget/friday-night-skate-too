<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Unit\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\fns_migrate\Plugin\migrate\process\FileIdToRealpath;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * Unit tests for the FileIdToRealpath migrate process plugin.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\process\FileIdToRealpath
 */
class FileIdToRealpathTest extends MigrateTestCase {

  /**
   * Build a plugin instance with mocked services.
   *
   * @param int $expectedFid
   *   The fid we expect to be loaded.
   * @param string|null $uri
   *   The URI returned by the loaded file (NULL means no file).
   * @param string|false $realpath
   *   The realpath result for the URI.
   */
  protected function buildPlugin(int $expectedFid, ?string $uri, $realpath): FileIdToRealpath {
    $fileStorage = $this->createMock(EntityStorageInterface::class);
    if ($uri === NULL) {
      $fileStorage->method('load')->with($expectedFid)->willReturn(NULL);
    }
    else {
      $file = $this->createMock(FileInterface::class);
      $file->method('getFileUri')->willReturn($uri);
      $fileStorage->method('load')->with($expectedFid)->willReturn($file);
    }

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('file')->willReturn($fileStorage);

    $fs = $this->createMock(FileSystemInterface::class);
    if ($uri !== NULL) {
      $fs->method('realpath')->with($uri)->willReturn($realpath);
    }

    return new FileIdToRealpath([], 'file_id_to_realpath', [], $fs, $etm);
  }

  /**
   * Convenience: a no-op MigrateExecutable.
   */
  protected function executable(): MigrateExecutableInterface {
    return $this->createMock(MigrateExecutableInterface::class);
  }

  /**
   * Convenience: a no-op Row.
   */
  protected function row(): Row {
    return $this->createMock(Row::class);
  }

  /**
   * @covers ::transform
   */
  public function testReturnsRealpathForValidFid(): void {
    $plugin = $this->buildPlugin(42, 'private://routes/gpx/foo.gpx', '/var/www/private/routes/gpx/foo.gpx');
    $result = $plugin->transform(42, $this->executable(), $this->row(), 'destination');
    $this->assertSame('/var/www/private/routes/gpx/foo.gpx', $result);
  }

  /**
   * @covers ::transform
   */
  public function testAcceptsNumericStringFid(): void {
    $plugin = $this->buildPlugin(7, 'public://hero/x.jpg', '/srv/files/hero/x.jpg');
    $result = $plugin->transform('7', $this->executable(), $this->row(), 'destination');
    $this->assertSame('/srv/files/hero/x.jpg', $result);
  }

  /**
   * @covers ::transform
   */
  public function testUnwrapsArrayValueFromMigrationLookup(): void {
    $plugin = $this->buildPlugin(99, 'private://x.gpx', '/tmp/x.gpx');
    // `migration_lookup` returns the destination id which for `entity:file`
    // arrives as an array on multi-key lookups.
    $result = $plugin->transform([99], $this->executable(), $this->row(), 'destination');
    $this->assertSame('/tmp/x.gpx', $result);
  }

  /**
   * @covers ::transform
   *
   * @dataProvider provideEmptyValues
   */
  public function testEmptyValuesReturnNull($value): void {
    $plugin = $this->buildPlugin(0, NULL, FALSE);
    $this->assertNull($plugin->transform($value, $this->executable(), $this->row(), 'destination'));
  }

  /**
   * Data provider: values that should short-circuit to NULL.
   *
   * @return array<string, array{0: mixed}>
   *   Keyed test cases of fid-like inputs that must produce a NULL result.
   */
  public static function provideEmptyValues(): array {
    return [
      'null' => [NULL],
      'empty string' => [''],
      'integer zero' => [0],
      'string zero' => ['0'],
      'non-numeric string' => ['not-a-fid'],
    ];
  }

  /**
   * @covers ::transform
   */
  public function testMissingFileReturnsNull(): void {
    $plugin = $this->buildPlugin(123, NULL, FALSE);
    $this->assertNull($plugin->transform(123, $this->executable(), $this->row(), 'destination'));
  }

  /**
   * @covers ::transform
   */
  public function testRealpathFailureReturnsNull(): void {
    $plugin = $this->buildPlugin(1, 'private://gone.gpx', FALSE);
    $this->assertNull($plugin->transform(1, $this->executable(), $this->row(), 'destination'));
  }

}
