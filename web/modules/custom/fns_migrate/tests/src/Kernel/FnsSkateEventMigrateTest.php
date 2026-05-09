<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\node\Entity\Node;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * End-to-end migration test for the fns_skate_event migration.
 *
 * Runs the full migration pipeline against fixture HTML files and asserts
 * that event nodes are created with the correct field values. The stub
 * HTTP client serves fixture files from `tests/fixtures/skates/` so no
 * network traffic occurs.
 *
 * @group fns_migrate
 */
class FnsSkateEventMigrateTest extends MigrateTestBase {

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

    $fixtures = __DIR__ . '/../../fixtures/skates';
    $base = 'https://legacy.test';
    $stub = new FixtureSkateEventHttpClient([
      $base . '/skate/1' => $fixtures . '/friday-night-canal-run.html',
      $base . '/skate/2' => $fixtures . '/old-town-evening-skate.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * Running fns_skate_event creates event nodes with correct field values.
   */
  public function testMigrationCreatesEventNodes(): void {
    $migration = $this->getMigration('fns_skate_event');
    $source = $migration->getSourceConfiguration();
    $source['base_url'] = 'https://legacy.test';
    $source['ids'] = [1, 2];
    $migration->set('source', $source);
    $this->executeMigration($migration);

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'event')
      ->accessCheck(FALSE)
      ->execute();

    // Two events fetched directly by ID from fixtures.
    $this->assertCount(2, $nids, 'Two event nodes created.');

    $nodes = Node::loadMultiple($nids);
    $byTitle = [];
    foreach ($nodes as $node) {
      $byTitle[$node->getTitle()] = $node;
    }

    $this->assertArrayHasKey('Friday Night Canal Run', $byTitle);
    $canal = $byTitle['Friday Night Canal Run'];
    $this->assertSame('2024-06-07', $canal->get('field_event_datetime')->value);
    $this->assertStringContainsString('canal towpath', $canal->get('body')->value);

    $this->assertArrayHasKey('Old Town Evening Skate', $byTitle);
    $old = $byTitle['Old Town Evening Skate'];
    $this->assertSame('2024-07-12', $old->get('field_event_datetime')->value);
  }

}

/**
 * Test double for {@see FnsHttpClient} that serves fixture HTML by URL.
 */
class FixtureSkateEventHttpClient extends FnsHttpClient {

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
