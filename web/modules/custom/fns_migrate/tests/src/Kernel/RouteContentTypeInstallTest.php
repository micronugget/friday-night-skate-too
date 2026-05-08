<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Verifies install-time creation of the route content type.
 *
 * Confirms the fns_migrate module creates the route node bundle, the
 * route_collection vocabulary, and all supporting fields shipped under
 * config/install/ (plus the body field attached via hook_install).
 *
 * @group fns_migrate
 */
class RouteContentTypeInstallTest extends KernelTestBase {

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
    'file',
    'image',
    'media',
    'taxonomy',
    'node',
    'filter',
    'geofield',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'migration_tools',
    'path_alias',
    'redirect',
    'fns_migrate',
  ];

  /**
   * Field machine names expected on the route bundle.
   *
   * Each entry maps the field name to the expected field type.
   */
  protected const EXPECTED_FIELDS = [
    'body' => 'text_with_summary',
    'field_distance_km' => 'decimal',
    'field_difficulty' => 'list_integer',
    'field_start_point' => 'geofield',
    'field_gpx_file' => 'file',
    'field_track_geometry' => 'geofield',
    'field_hero_image' => 'entity_reference',
    'field_collections' => 'entity_reference',
    'field_legacy_url' => 'link',
    'field_legacy_id' => 'string',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install the schemas needed for the field plumbing to work in a kernel
    // test, and import all default config shipped by fns_migrate.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['filter', 'node', 'field', 'fns_migrate']);
    // Kernel tests don't run hook_install automatically; invoke ours so the
    // shared body field is attached to the route bundle.
    \Drupal::moduleHandler()->loadInclude('fns_migrate', 'install');
    fns_migrate_install();
  }

  /**
   * The route bundle and its supporting vocabulary are created on install.
   */
  public function testRouteBundleAndVocabularyExist(): void {
    $bundle = NodeType::load('route');
    $this->assertNotNull($bundle, 'The "route" node type is created on install.');
    $this->assertSame('Route', $bundle->label());

    $vocab = Vocabulary::load('route_collection');
    $this->assertNotNull(
      $vocab,
      'The "route_collection" taxonomy vocabulary is created on install.'
    );
  }

  /**
   * Every documented field is present on the route bundle.
   */
  public function testAllExpectedFieldsArePresentOnRouteBundle(): void {
    foreach (self::EXPECTED_FIELDS as $field_name => $expected_type) {
      $storage = FieldStorageConfig::loadByName('node', $field_name);
      $this->assertNotNull(
        $storage,
        sprintf('Field storage node.%s is installed.', $field_name)
      );
      $this->assertSame(
        $expected_type,
        $storage->getType(),
        sprintf('Field storage node.%s has type %s.', $field_name, $expected_type)
      );

      $instance = FieldConfig::loadByName('node', 'route', $field_name);
      $this->assertNotNull(
        $instance,
        sprintf('Field instance node.route.%s is installed.', $field_name)
      );
      $this->assertSame(
        $expected_type,
        $instance->getType(),
        sprintf('Field instance node.route.%s has type %s.', $field_name, $expected_type)
      );
    }
  }

  /**
   * Cardinalities and key option settings match the issue specification.
   */
  public function testFieldCardinalitiesAndKeySettings(): void {
    $distance = FieldStorageConfig::loadByName('node', 'field_distance_km');
    $this->assertSame(1, $distance->getCardinality());
    $this->assertSame(5, (int) $distance->getSetting('precision'));
    $this->assertSame(2, (int) $distance->getSetting('scale'));

    $collections = FieldStorageConfig::loadByName('node', 'field_collections');
    $this->assertSame(
      FieldStorageConfig::CARDINALITY_UNLIMITED,
      $collections->getCardinality(),
      'field_collections is unlimited cardinality.'
    );

    $difficulty = FieldStorageConfig::loadByName('node', 'field_difficulty');
    $allowed = $difficulty->getSetting('allowed_values');
    // Drupal may normalize allowed_values to either a list of [value, label]
    // arrays or to a flat [value => label] map; both are acceptable here.
    if (is_array($allowed) && array_is_list($allowed) && isset($allowed[0]['value'])) {
      $values = array_map(static fn(array $v) => (int) $v['value'], $allowed);
    }
    else {
      $values = array_map('intval', array_keys((array) $allowed));
    }
    sort($values);
    $this->assertSame([1, 2, 3, 4, 5], $values, 'Difficulty allowed values are 1..5.');

    $gpx = FieldConfig::loadByName('node', 'route', 'field_gpx_file');
    $this->assertSame('gpx', $gpx->getSetting('file_extensions'));
  }

}
