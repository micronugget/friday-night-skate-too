<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\fns_migrate\Plugin\migrate\process\AbsoluteUrlToInternal;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * Unit tests for the AbsoluteUrlToInternal migrate process plugin.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\process\AbsoluteUrlToInternal
 */
class AbsoluteUrlToInternalTest extends MigrateTestCase {

  /**
   * Build a plugin instance with the given configuration.
   *
   * @param array $configuration
   *   Plugin configuration overrides.
   */
  protected function buildPlugin(array $configuration = []): AbsoluteUrlToInternal {
    return new AbsoluteUrlToInternal(
      $configuration,
      'absolute_url_to_internal',
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
   * @covers ::rewrite
   */
  public function testRewritesHrefInDoubleQuotes(): void {
    $plugin = $this->buildPlugin();
    $html = '<a href="https://fridaynightskate.com/routes/canal-classic">Canal</a>';
    $result = $plugin->transform($html, $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertSame('<a href="/routes/canal-classic">Canal</a>', $result);
  }

  /**
   * @covers ::transform
   * @covers ::rewrite
   */
  public function testRewritesSrcInDoubleQuotes(): void {
    $plugin = $this->buildPlugin();
    $html = '<img src="https://fridaynightskate.com/sites/default/files/hero.jpg">';
    $result = $plugin->transform($html, $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertSame('<img src="/sites/default/files/hero.jpg">', $result);
  }

  /**
   * @covers ::rewrite
   */
  public function testRewritesMultipleOccurrences(): void {
    $plugin = $this->buildPlugin();
    $html = '<a href="https://fridaynightskate.com/a">A</a> <a href="https://fridaynightskate.com/b">B</a>';
    $result = $plugin->transform($html, $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertSame('<a href="/a">A</a> <a href="/b">B</a>', $result);
  }

  /**
   * @covers ::rewrite
   */
  public function testExternalUrlsUntouched(): void {
    $plugin = $this->buildPlugin();
    $html = '<a href="https://example.com/page">External</a>';
    $result = $plugin->transform($html, $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertSame($html, $result);
  }

  /**
   * @covers ::rewrite
   */
  public function testLegacyUrlInVisibleTextUntouched(): void {
    $plugin = $this->buildPlugin();
    // The URL appears as visible text, not inside an attribute — must not be
    // stripped.
    $html = '<p>Visit https://fridaynightskate.com/routes for more.</p>';
    $result = $plugin->transform($html, $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertSame($html, $result);
  }

  /**
   * @covers ::transform
   */
  public function testEmptyValuePassedThrough(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform('', $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertSame('', $result);
  }

  /**
   * @covers ::transform
   */
  public function testNullValuePassedThrough(): void {
    $plugin = $this->buildPlugin();
    $result = $plugin->transform(NULL, $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertNull($result);
  }

  /**
   * @covers ::transform
   * @covers ::rewrite
   */
  public function testCustomLegacyBaseUrl(): void {
    $plugin = $this->buildPlugin(['legacy_base_url' => 'https://legacy.test']);
    $html = '<a href="https://legacy.test/routes/foo">Foo</a>';
    $result = $plugin->transform($html, $this->getMigrateExecutable(), $this->getRow(), 'body');
    $this->assertSame('<a href="/routes/foo">Foo</a>', $result);
  }

  /**
   * @covers ::rewrite
   */
  public function testDefaultBaseNotRewrittenWhenCustomBaseSet(): void {
    $plugin = $this->buildPlugin(['legacy_base_url' => 'https://legacy.test']);
    $html = '<a href="https://fridaynightskate.com/routes/foo">Foo</a>';
    $result = $plugin->transform($html, $this->getMigrateExecutable(), $this->getRow(), 'body');
    // Default base should NOT be rewritten when a custom base is configured.
    $this->assertSame($html, $result);
  }

}
