<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Plugin\migrate\source\FnsRouteCollectionsHtml;
use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Kernel test for the {@see FnsRouteCollectionsHtml} source plugin.
 *
 * A stub {@see FnsHttpClient} maps each URL to a fixture file under
 * `tests/fixtures/route_collections/` so the iterator runs entirely off-disk.
 *
 * Asserts:
 *  - All 3 collection rows are yielded with the correct shape.
 *  - The `id` field equals the URL slug.
 *  - HTML entities in the description meta tag are decoded.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\source\FnsRouteCollectionsHtml
 */
class FnsRouteCollectionsHtmlSourceTest extends MigrateTestBase {

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

    $fixtures = __DIR__ . '/../../fixtures/route_collections';
    $base = 'https://legacy.test';
    $stub = new FixtureFnsHttpClientForCollections([
      $base . '/routes/collections'                        => $fixtures . '/index.html',
      $base . '/routes/collections/fns'                   => $fixtures . '/fns.html',
      $base . '/routes/collections/sunday-morning-skate'  => $fixtures . '/sunday-morning-skate.html',
      $base . '/routes/collections/OldSite'               => $fixtures . '/OldSite.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * @covers ::initializeIterator
   * @covers ::parseDetail
   * @covers ::extractCollectionUrls
   */
  public function testIteratorYieldsAllThreeCollections(): void {
    $plugin = $this->buildPlugin();

    $rows = [];
    foreach ($plugin as $row) {
      $rows[] = $row->getSource();
    }

    $this->assertCount(3, $rows, 'Exactly 3 collection rows yielded.');

    $bySlug = array_column($rows, NULL, 'slug');

    // --- fns ---
    $this->assertArrayHasKey('fns', $bySlug);
    $fns = $bySlug['fns'];
    $this->assertSame('fns', $fns['id']);
    $this->assertSame('https://legacy.test/routes/collections/fns', $fns['url']);
    $this->assertSame('Friday Night Skate routes', $fns['name']);
    $this->assertSame(
      'This is the largest collection of FNS Skate routes, dating back to the year 2008',
      $fns['description'],
    );

    // --- sunday-morning-skate ---
    $this->assertArrayHasKey('sunday-morning-skate', $bySlug);
    $sms = $bySlug['sunday-morning-skate'];
    $this->assertSame('sunday-morning-skate', $sms['id']);
    $this->assertSame('Sunday Morning Skate routes', $sms['name']);
    $this->assertStringContainsString('40+ km', $sms['description']);

    // --- OldSite ---
    $this->assertArrayHasKey('OldSite', $bySlug);
    $old = $bySlug['OldSite'];
    $this->assertSame('OldSite', $old['id']);
    $this->assertSame('Skate routes through amsterdam', $old['name']);
    // HTML entity &amp; in the fixture must be decoded to a plain ampersand.
    $this->assertStringContainsString('&', $old['description']);
    $this->assertStringNotContainsString('&amp;', $old['description']);
  }

  /**
   * @covers ::fields
   * @covers ::getIds
   */
  public function testFieldsAndIdsContract(): void {
    $plugin = $this->buildPlugin();

    $fields = $plugin->fields();
    foreach (['id', 'slug', 'url', 'name', 'description'] as $key) {
      $this->assertArrayHasKey($key, $fields);
    }

    $ids = $plugin->getIds();
    $this->assertSame(['id'], array_keys($ids));
    $this->assertSame('string', $ids['id']['type']);
  }

  /**
   * Construct the source plugin under test against an in-memory migration.
   */
  protected function buildPlugin(): FnsRouteCollectionsHtml {
    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'id'          => 'fns_route_collections_html_test',
      'source'      => [
        'plugin'     => 'fns_route_collections_html',
        'base_url'   => 'https://legacy.test',
        'index_path' => '/routes/collections',
      ],
      'process'     => [],
      'destination' => ['plugin' => 'null'],
    ]);
    $source = $migration->getSourcePlugin();
    $this->assertInstanceOf(FnsRouteCollectionsHtml::class, $source);
    return $source;
  }

}

/**
 * Test double for FnsHttpClient that serves fixture HTML by URL.
 *
 * Used exclusively by {@see FnsRouteCollectionsHtmlSourceTest}.
 */
class FixtureFnsHttpClientForCollections extends FnsHttpClient {

  /**
   * Constructs the fixture-backed client.
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
