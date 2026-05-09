<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\node\Entity\Node;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * End-to-end migration test for the fns_page migration.
 *
 * Runs the full migration pipeline against fixture HTML files and asserts
 * that page nodes are created with the correct field values. The stub
 * HTTP client serves fixture files from `tests/fixtures/pages/` so no
 * network traffic occurs.
 *
 * @group fns_migrate
 */
class FnsPageMigrateTest extends MigrateTestBase {

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

    $fixtures = __DIR__ . '/../../fixtures/pages';
    $base = 'https://legacy.test';
    $stub = new FixturePageExecuteHttpClient([
      $base . '/pages'          => $fixtures . '/index-1.html',
      $base . '/pages?page=2'   => $fixtures . '/index-2.html',
      $base . '/pages/about'        => $fixtures . '/about.html',
      $base . '/pages/safety-guide' => $fixtures . '/safety-guide.html',
    ]);
    $this->container->set('fns_migrate.http_client', $stub);
  }

  /**
   * Running fns_page creates page nodes with correct field values.
   */
  public function testMigrationCreatesPageNodes(): void {
    $migration = $this->getMigration('fns_page');
    $source = $migration->getSourceConfiguration();
    $source['base_url'] = 'https://legacy.test';
    $migration->set('source', $source);
    $this->executeMigration($migration);

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->accessCheck(FALSE)
      ->execute();

    // Two unique slugs from the fixtures (duplicate deduplicated).
    $this->assertCount(2, $nids, 'Two page nodes created.');

    $nodes = Node::loadMultiple($nids);
    $byTitle = [];
    foreach ($nodes as $node) {
      $byTitle[$node->getTitle()] = $node;
    }

    $this->assertArrayHasKey('About Friday Night Skate', $byTitle);
    $this->assertArrayHasKey('Safety Guide', $byTitle);
  }

}

/**
 * Test double for {@see FnsHttpClient} that serves fixture HTML by URL.
 */
class FixturePageExecuteHttpClient extends FnsHttpClient {

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
