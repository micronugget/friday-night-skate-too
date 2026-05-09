<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Plugin\migrate\source\FnsNewsHtml;
use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Kernel test for the {@see FnsNewsHtml} source plugin.
 *
 * The test registers a stub {@see FnsHttpClient} that maps URL → fixture file
 * (under `tests/fixtures/news/`) so the iterator runs entirely off-disk.
 * Three concerns are asserted:
 *  - Each detail page is parsed into the documented row shape.
 *  - Duplicates within an index page are deduplicated by slug.
 *  - Pagination stops cleanly when a page has no new detail links.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\source\FnsNewsHtml
 */
class FnsNewsHtmlSourceTest extends MigrateTestBase {

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
    $fixtures = __DIR__ . '/../../fixtures/news';
    $base = 'https://legacy.test';
    $stub = new FixtureNewsHttpClient([
      $base . '/news' => $fixtures . '/index-1.html',
      $base . '/news?page=2' => $fixtures . '/index-2.html',
      $base . '/news/summer-skate-season-2024' => $fixtures . '/summer-skate-season-2024.html',
      $base . '/news/new-route-added-regents-canal' => $fixtures . '/new-route-added-regents-canal.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * @covers ::initializeIterator
   * @covers ::parseDetail
   * @covers ::extractNewsLinks
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

    $this->assertArrayHasKey('summer-skate-season-2024', $bySlug);
    $summer = $bySlug['summer-skate-season-2024'];
    $this->assertSame('summer-skate-season-2024', $summer['id']);
    $this->assertSame('https://legacy.test/news/summer-skate-season-2024', $summer['url']);
    $this->assertSame('Summer Skate Season 2024', $summer['title']);
    $this->assertSame('2024-05-01', $summer['published_date']);
    $this->assertSame('https://legacy.test/sites/default/files/news/summer-2024-hero.jpg', $summer['hero_image_url']);
    $this->assertStringContainsString('canal run', $summer['body_html']);

    $this->assertArrayHasKey('new-route-added-regents-canal', $bySlug);
    $route = $bySlug['new-route-added-regents-canal'];
    $this->assertSame("New Route Added: Regent's Canal", $route['title']);
    $this->assertSame('2024-03-15', $route['published_date']);
    $this->assertSame('', $route['hero_image_url']);
    $this->assertStringContainsString("Regent's Canal", $route['body_html']);
  }

  /**
   * @covers ::fields
   * @covers ::getIds
   */
  public function testFieldsAndIdsContract(): void {
    $plugin = $this->buildPlugin();
    $fields = $plugin->fields();
    $expected = ['id', 'slug', 'url', 'title', 'published_date', 'hero_image_url', 'body_html'];
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
  protected function buildPlugin(): FnsNewsHtml {
    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'id' => 'fns_news_html_test',
      'source' => [
        'plugin' => 'fns_news_html',
        'base_url' => 'https://legacy.test',
        'index_path' => '/news',
        'page_query' => 'page',
      ],
      'process' => [],
      'destination' => ['plugin' => 'null'],
    ]);
    $source = $migration->getSourcePlugin();
    $this->assertInstanceOf(FnsNewsHtml::class, $source);
    return $source;
  }

}

/**
 * Test double for {@see FnsHttpClient} that serves fixture HTML by URL.
 */
class FixtureNewsHttpClient extends FnsHttpClient {

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
