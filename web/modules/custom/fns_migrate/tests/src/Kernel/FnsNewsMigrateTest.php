<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\node\Entity\Node;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * End-to-end migration test for the fns_news migration.
 *
 * Runs the full migration pipeline against fixture HTML files and asserts
 * that news nodes are created with the correct field values. The stub
 * HTTP client serves fixture files from `tests/fixtures/news/` so no
 * network traffic occurs.
 *
 * @group fns_migrate
 */
class FnsNewsMigrateTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'link',
    'datetime',
    'file',
    'image',
    'media',
    'taxonomy',
    'node',
    'filter',
    'geofield',
    'migrate',
    'migrate_plus',
    'path_alias',
    'fns_migrate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'field', 'fns_migrate']);

    \Drupal::moduleHandler()->loadInclude('fns_migrate', 'install');
    fns_migrate_install();


    $fixtures = __DIR__ . '/../../fixtures/news';
    $base = 'https://legacy.test';
    $stub = new FixtureNewsExecuteHttpClient([
      $base . '/news'         => $fixtures . '/index-1.html',
      $base . '/news?page=2'  => $fixtures . '/index-2.html',
      $base . '/news/summer-skate-season-2024'        => $fixtures . '/summer-skate-season-2024.html',
      $base . '/news/new-route-added-regents-canal'   => $fixtures . '/new-route-added-regents-canal.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * Running fns_news creates news nodes with correct field values.
   */
  public function testMigrationCreatesNewsNodes(): void {
    $migration = $this->getMigration('fns_news');
    $source = $migration->getSourceConfiguration();
    $source['base_url'] = 'https://legacy.test';
    $migration->set('source', $source);
    $this->executeMigration($migration);

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'news')
      ->accessCheck(FALSE)
      ->execute();

    // Two unique slugs from the fixtures (duplicate deduplicated).
    $this->assertCount(2, $nids, 'Two news nodes created.');

    $nodes = Node::loadMultiple($nids);
    $byTitle = [];
    foreach ($nodes as $node) {
      $byTitle[$node->getTitle()] = $node;
    }

    $this->assertArrayHasKey('Summer Skate Season 2024', $byTitle);
    $this->assertArrayHasKey("New Route Added: Regent's Canal", $byTitle);
  }

}

/**
 * Test double for {@see FnsHttpClient} that serves fixture HTML by URL.
 */
class FixtureNewsExecuteHttpClient extends FnsHttpClient {

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
