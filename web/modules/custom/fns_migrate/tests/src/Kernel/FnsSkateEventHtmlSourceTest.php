<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Plugin\migrate\source\FnsSkateEventHtml;
use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Kernel test for the {@see FnsSkateEventHtml} source plugin.
 *
 * The test registers a stub {@see FnsHttpClient} that maps URL → fixture file
 * (under `tests/fixtures/skates/`) so the iterator runs entirely off-disk.
 * Three concerns are asserted:
 *  - Each detail page is parsed into the documented row shape.
 *  - Duplicates within an index page are deduplicated by slug.
 *  - URLs containing `/live` are silently skipped (live-tracker guard).
 *  - Pagination stops cleanly when a page has no new detail links.
 *
 * @group fns_migrate
 * @coversDefaultClass \Drupal\fns_migrate\Plugin\migrate\source\FnsSkateEventHtml
 */
class FnsSkateEventHtmlSourceTest extends MigrateTestBase {

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
    $fixtures = __DIR__ . '/../../fixtures/skates';
    $base = 'https://legacy.test';
    $stub = new FixtureSkateHttpClient([
      $base . '/skate' => $fixtures . '/index-1.html',
      $base . '/skate?page=2' => $fixtures . '/index-2.html',
      $base . '/skate/friday-night-canal-run' => $fixtures . '/friday-night-canal-run.html',
      $base . '/skate/old-town-evening-skate' => $fixtures . '/old-town-evening-skate.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * @covers ::initializeIterator
   * @covers ::parseDetail
   * @covers ::extractEventLinks
   * @covers ::isLiveTrackerUrl
   */
  public function testIteratorYieldsExpectedRows(): void {
    $plugin = $this->buildPlugin();
    $rows = [];
    foreach ($plugin as $row) {
      $rows[] = $row->getSource();
    }

    // Two unique slugs; duplicate and /live-tracker-test are both skipped.
    $this->assertCount(2, $rows, 'Two unique slugs; duplicate and live URL collapsed.');
    $bySlug = array_column($rows, NULL, 'slug');

    $this->assertArrayHasKey('friday-night-canal-run', $bySlug);
    $canal = $bySlug['friday-night-canal-run'];
    $this->assertSame('friday-night-canal-run', $canal['id']);
    $this->assertSame('https://legacy.test/skate/friday-night-canal-run', $canal['url']);
    $this->assertSame('Friday Night Canal Run', $canal['title']);
    $this->assertSame('2024-06-07', $canal['event_date']);
    $this->assertSame('20:30', $canal['event_time']);
    $this->assertSame('canal-classic', $canal['route_slug']);
    $this->assertSame('Barbican roundabout, EC2Y 8DS', $canal['meeting_point']);
    $this->assertStringContainsString('canal towpath', $canal['body_html']);

    $this->assertArrayHasKey('old-town-evening-skate', $bySlug);
    $old = $bySlug['old-town-evening-skate'];
    $this->assertSame('Old Town Evening Skate', $old['title']);
    $this->assertSame('2024-07-12', $old['event_date']);
    $this->assertSame('19:00', $old['event_time']);
    $this->assertSame('', $old['route_slug']);
    $this->assertSame('Monument station, EC3R 8AH', $old['meeting_point']);
  }

  /**
   * @covers ::fields
   * @covers ::getIds
   */
  public function testFieldsAndIdsContract(): void {
    $plugin = $this->buildPlugin();
    $fields = $plugin->fields();
    $expected = [
      'id', 'slug', 'url', 'title', 'event_date',
      'event_time', 'route_slug', 'meeting_point', 'body_html',
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
  protected function buildPlugin(): FnsSkateEventHtml {
    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'id' => 'fns_skate_event_html_test',
      'source' => [
        'plugin' => 'fns_skate_event_html',
        'base_url' => 'https://legacy.test',
        'index_path' => '/skate',
        'page_query' => 'page',
      ],
      'process' => [],
      'destination' => ['plugin' => 'null'],
    ]);
    $source = $migration->getSourcePlugin();
    $this->assertInstanceOf(FnsSkateEventHtml::class, $source);
    return $source;
  }

}

/**
 * Test double for {@see FnsHttpClient} that serves fixture HTML by URL.
 */
class FixtureSkateHttpClient extends FnsHttpClient {

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
