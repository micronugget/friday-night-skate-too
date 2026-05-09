<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Unit\Plugin\migrate\process;

use Drupal\fns_migrate\Plugin\migrate\process\RouteSlugLookup;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * Unit tests for the RouteSlugLookup migrate process plugin.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\process\RouteSlugLookup
 */
class RouteSlugLookupTest extends MigrateTestCase {

  /**
   * Build a plugin instance with mocked dependencies.
   *
   * @param \Drupal\migrate\MigrateLookupInterface $lookup
   *   The migrate lookup service mock.
   * @param array $configuration
   *   Plugin configuration overrides.
   */
  protected function buildPlugin(MigrateLookupInterface $lookup, array $configuration = []): RouteSlugLookup {
    $migration = $this->createMock(MigrationInterface::class);
    return new RouteSlugLookup(
      $configuration,
      'fns_route_slug_lookup',
      [],
      $lookup,
      $migration,
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
   */
  public function testReturnsNidWhenSlugFound(): void {
    $lookup = $this->createMock(MigrateLookupInterface::class);
    $lookup->expects($this->once())
      ->method('lookup')
      ->with(['fns_route'], ['canal-classic'])
      ->willReturn([['nid' => 42]]);

    $plugin = $this->buildPlugin($lookup);
    $result = $plugin->transform(
      'canal-classic',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route'
    );
    $this->assertSame(42, $result);
  }

  /**
   * @covers ::transform
   */
  public function testReturnsNullWhenSlugNotFound(): void {
    $lookup = $this->createMock(MigrateLookupInterface::class);
    $lookup->expects($this->once())
      ->method('lookup')
      ->willReturn([]);

    $executable = $this->createMock(MigrateExecutableInterface::class);
    $executable->expects($this->once())
      ->method('saveMessage')
      ->with($this->stringContains('no v2 node found'));

    $plugin = $this->buildPlugin($lookup);
    $result = $plugin->transform(
      'unknown-slug',
      $executable,
      $this->getRow(),
      'field_route'
    );
    $this->assertNull($result);
  }

  /**
   * @covers ::transform
   */
  public function testReturnsNullOnEmptyValue(): void {
    $lookup = $this->createMock(MigrateLookupInterface::class);
    $lookup->expects($this->never())->method('lookup');

    $plugin = $this->buildPlugin($lookup);
    $this->assertNull(
      $plugin->transform('', $this->getMigrateExecutable(), $this->getRow(), 'field_route')
    );
  }

  /**
   * @covers ::transform
   */
  public function testReturnsNullOnNullValue(): void {
    $lookup = $this->createMock(MigrateLookupInterface::class);
    $lookup->expects($this->never())->method('lookup');

    $plugin = $this->buildPlugin($lookup);
    $this->assertNull(
      $plugin->transform(NULL, $this->getMigrateExecutable(), $this->getRow(), 'field_route')
    );
  }

  /**
   * @covers ::transform
   */
  public function testReturnsNullAndLogsOnLookupException(): void {
    $lookup = $this->createMock(MigrateLookupInterface::class);
    $lookup->method('lookup')->willThrowException(new \RuntimeException('DB error'));

    $executable = $this->createMock(MigrateExecutableInterface::class);
    $executable->expects($this->once())
      ->method('saveMessage')
      ->with($this->stringContains('lookup failed'));

    $plugin = $this->buildPlugin($lookup);
    $result = $plugin->transform(
      'canal-classic',
      $executable,
      $this->getRow(),
      'field_route'
    );
    $this->assertNull($result);
  }

  /**
   * @covers ::transform
   */
  public function testCustomMigrationIdUsed(): void {
    $lookup = $this->createMock(MigrateLookupInterface::class);
    $lookup->expects($this->once())
      ->method('lookup')
      ->with(['custom_migration'], ['some-slug'])
      ->willReturn([['nid' => 7]]);

    $plugin = $this->buildPlugin($lookup, ['migration' => 'custom_migration']);
    $result = $plugin->transform(
      'some-slug',
      $this->getMigrateExecutable(),
      $this->getRow(),
      'field_route'
    );
    $this->assertSame(7, $result);
  }

}
