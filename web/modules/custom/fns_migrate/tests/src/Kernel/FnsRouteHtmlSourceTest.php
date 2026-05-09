<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Plugin\migrate\source\FnsRouteHtml;
use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Kernel test for the {@see FnsRouteHtml} source plugin.
 *
 * The test registers a stub {@see FnsHttpClient} that maps URL → fixture file
 * (under `tests/fixtures/routes/`) so the iterator runs entirely off-disk.
 * Three concerns are asserted:
 *  - Each detail page is parsed into the documented row shape.
 *  - Duplicates within an index page are deduplicated by slug.
 *  - Pagination stops cleanly when a page has no new detail links.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\source\FnsRouteHtml
 */
class FnsRouteHtmlSourceTest extends MigrateTestBase {

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

    $fixtures = __DIR__ . '/../../fixtures/routes';
    $base = 'https://legacy.test';
    $stub = new FixtureFnsHttpClient([
      $base . '/routes' => $fixtures . '/index-1.html',
      $base . '/routes?page=2' => $fixtures . '/index-2.html',
      $base . '/routes/canal-classic' => $fixtures . '/canal-classic.html',
      $base . '/routes/old-town-loop' => $fixtures . '/old-town-loop.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * @covers ::initializeIterator
   * @covers ::parseDetail
   * @covers ::extractRouteLinks
   */
  public function testIteratorYieldsExpectedRows(): void {
    $plugin = $this->buildPlugin();

    $rows = [];
    foreach ($plugin as $row) {
      // SourcePluginBase yields Row objects; reduce to the source array so the
      // assertions below speak the documented row-shape contract directly.
      $rows[] = $row->getSource();
    }

    $this->assertCount(2, $rows, 'Two unique slugs across pages, duplicates collapsed.');

    $bySlug = array_column($rows, NULL, 'slug');

    $this->assertArrayHasKey('canal-classic', $bySlug);
    $canal = $bySlug['canal-classic'];
    $this->assertSame('canal-classic', $canal['id']);
    $this->assertSame('https://legacy.test/routes/canal-classic', $canal['url']);
    $this->assertSame('Canal Classic', $canal['title']);
    $this->assertSame('12.5', $canal['distance_km']);
    $this->assertSame('Easy', $canal['difficulty']);
    $this->assertSame('https://legacy.test/sites/default/files/routes/canal-classic.gpx', $canal['gpx_url']);
    $this->assertSame('https://legacy.test/sites/default/files/routes/canal-classic-hero.jpg', $canal['hero_image_url']);
    $this->assertStringContainsString('canal-side spin', $canal['body_html']);
    $this->assertSame(['Canals', 'Beginner'], $canal['collections']);

    $this->assertArrayHasKey('old-town-loop', $bySlug);
    $loop = $bySlug['old-town-loop'];
    $this->assertSame('Old Town Loop', $loop['title']);
    $this->assertSame('8', $loop['distance_km']);
    $this->assertSame('Medium', $loop['difficulty']);
    // Hero comes from a fully-qualified <img src> that should pass through.
    $this->assertSame('https://cdn.fridaynightskate.test/old-town-loop.jpg', $loop['hero_image_url']);
    $this->assertSame('', $loop['gpx_url']);
    $this->assertSame([], $loop['collections']);
  }

  /**
   * @covers ::fields
   * @covers ::getIds
   */
  public function testFieldsAndIdsContract(): void {
    $plugin = $this->buildPlugin();
    $fields = $plugin->fields();
    $expected = [
      'id', 'slug', 'url', 'title', 'distance_km',
      'difficulty', 'gpx_url', 'hero_image_url', 'body_html', 'collections',
    ];
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
  protected function buildPlugin(): FnsRouteHtml {
    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'id' => 'fns_route_html_test',
      'source' => [
        'plugin' => 'fns_route_html',
        'base_url' => 'https://legacy.test',
        'index_path' => '/routes',
        'page_query' => 'page',
      ],
      'process' => [],
      'destination' => ['plugin' => 'null'],
    ]);
    $source = $migration->getSourcePlugin();
    $this->assertInstanceOf(FnsRouteHtml::class, $source);
    return $source;
  }

}

/**
 * Test double for {@see FnsHttpClient} that serves fixture HTML by URL.
 *
 * Bypasses the real constructor — none of the parent dependencies are needed
 * because every code path that would touch them goes through ::fetch().
 */
class FixtureFnsHttpClient extends FnsHttpClient {

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
