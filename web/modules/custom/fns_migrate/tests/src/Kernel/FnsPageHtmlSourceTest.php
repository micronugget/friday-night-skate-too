<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Plugin\migrate\source\FnsPageHtml;
use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Kernel test for the {@see FnsPageHtml} source plugin.
 *
 * The test registers a stub {@see FnsHttpClient} that maps URL → fixture file
 * (under `tests/fixtures/pages/`) so the iterator runs entirely off-disk.
 * Three concerns are asserted:
 *  - Each detail page is parsed into the documented row shape.
 *  - Duplicates within an index page are deduplicated by slug.
 *  - Pagination stops cleanly when a page has no new detail links.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\source\FnsPageHtml
 */
class FnsPageHtmlSourceTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'fns_migrate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $fixtures = __DIR__ . '/../../fixtures/pages';
    $base = 'https://legacy.test';
    $stub = new FixturePageHttpClient([
      $base . '/pages' => $fixtures . '/index-1.html',
      $base . '/pages?page=2' => $fixtures . '/index-2.html',
      $base . '/pages/about' => $fixtures . '/about.html',
      $base . '/pages/safety-guide' => $fixtures . '/safety-guide.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * @covers ::initializeIterator
   * @covers ::parseDetail
   * @covers ::extractPageLinks
   */
  public function testIteratorYieldsExpectedRows(): void {
    $plugin = $this->buildPlugin();
    $rows = [];
    foreach ($plugin as $row) {
      $rows[] = $row->getSource();
    }

    // Two unique slugs; duplicate collapsed.
    $this->assertCount(2, $rows, 'Two unique slugs across pages, duplicates collapsed.');
    $bySlug = array_column($rows, NULL, 'slug');

    $this->assertArrayHasKey('about', $bySlug);
    $about = $bySlug['about'];
    $this->assertSame('about', $about['id']);
    $this->assertSame('https://legacy.test/pages/about', $about['url']);
    $this->assertSame('About Friday Night Skate', $about['title']);
    $this->assertSame('https://legacy.test/sites/default/files/pages/about-hero.jpg', $about['hero_image_url']);
    $this->assertStringContainsString('free weekly group skate', $about['body_html']);

    $this->assertArrayHasKey('safety-guide', $bySlug);
    $safety = $bySlug['safety-guide'];
    $this->assertSame('Safety Guide', $safety['title']);
    $this->assertSame('', $safety['hero_image_url']);
    $this->assertStringContainsString('helmet', $safety['body_html']);
  }

  /**
   * @covers ::fields
   * @covers ::getIds
   */
  public function testFieldsAndIdsContract(): void {
    $plugin = $this->buildPlugin();
    $fields = $plugin->fields();
    $expected = ['id', 'slug', 'url', 'title', 'hero_image_url', 'body_html'];
    foreach ($expected as $key) {
      $this->assertArrayHasKey($key, $fields);
    }
    $ids = $plugin->getIds();
    $this->assertSame(['id'], array_keys($ids));
    $this->assertSame('string', $ids['id']['type']);
  }

  /**
   * Construct the source plugin under test against an in-memory migration.
   */
  protected function buildPlugin(): FnsPageHtml {
    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'id' => 'fns_page_html_test',
      'source' => [
        'plugin' => 'fns_page_html',
        'base_url' => 'https://legacy.test',
        'index_path' => '/pages',
        'page_query' => 'page',
      ],
      'process' => [],
      'destination' => ['plugin' => 'null'],
    ]);
    $source = $migration->getSourcePlugin();
    $this->assertInstanceOf(FnsPageHtml::class, $source);
    return $source;
  }

}

/**
 * Test double for {@see FnsHttpClient} that serves fixture HTML by URL.
 */
class FixturePageHttpClient extends FnsHttpClient {

  /**
   * Construct the fixture-backed client.
   *
   * @param array<string, string> $map
   *   URL → fixture file path.
   */
  public function __construct(private array $map) {
    // Intentionally do not call parent::__construct().
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(string $url, string $group, string $cacheKey): string {
    if (!isset($this->map[$url])) {
      throw new \RuntimeException('No fixture registered for ' . $url);
    }
    $contents = file_get_contents($this->map[$url]);
    if ($contents === FALSE) {
      throw new \RuntimeException('Cannot read fixture for ' . $url);
    }
    return $contents;
  }

}
