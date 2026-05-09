<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

/**
 * Verifies install-time creation of the event content type.
 *
 * Confirms the fns_migrate module creates the event node bundle and all
 * supporting fields shipped under config/optional/, and that the soft
 * legacy_url / legacy_id fields are also attachable to the news and page
 * bundles when they exist.
 *
 * @group fns_migrate
 */
class EventContentTypeInstallTest extends KernelTestBase {

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
    'migrate_tools',
    'migration_tools',
    'path_alias',
    'redirect',
    'fns_migrate',
  ];

  /**
   * Field machine names expected on the event bundle.
   *
   * Each entry maps the field name to the expected field type.
   */
  protected const EXPECTED_EVENT_FIELDS = [
    'body' => 'text_with_summary',
    'field_event_datetime' => 'datetime',
    'field_meeting_point' => 'geofield',
    'field_event_status' => 'list_string',
    'field_route' => 'entity_reference',
    'field_hero_image' => 'entity_reference',
    'field_legacy_url' => 'link',
    'field_legacy_id' => 'string',
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
    $this->installConfig(['filter', 'node', 'field', 'fns_migrate']);
    // Kernel tests don't run hook_install automatically; invoke ours so the
    // shared body field is attached to the route bundle, then attach a body
    // instance to the event bundle the same way the runtime install would.
    \Drupal::moduleHandler()->loadInclude('fns_migrate', 'install');
    fns_migrate_install();
  }

  /**
   * The event bundle is created on install.
   */
  public function testEventBundleExists(): void {
    $bundle = NodeType::load('event');
    $this->assertNotNull($bundle, 'The "event" node type is created on install.');
    $this->assertSame('Skate event', $bundle->label());
  }

  /**
   * Every documented field is present on the event bundle.
   */
  public function testAllExpectedFieldsArePresentOnEventBundle(): void {
    foreach (self::EXPECTED_EVENT_FIELDS as $field_name => $expected_type) {
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

      $instance = FieldConfig::loadByName('node', 'event', $field_name);
      $this->assertNotNull(
        $instance,
        sprintf('Field instance node.event.%s is installed.', $field_name)
      );
      $this->assertSame(
        $expected_type,
        $instance->getType(),
        sprintf('Field instance node.event.%s has type %s.', $field_name, $expected_type)
      );
    }
  }

  /**
   * Status, route reference and datetime settings match the specification.
   */
  public function testEventFieldKeySettings(): void {
    $status = FieldStorageConfig::loadByName('node', 'field_event_status');
    $allowed = $status->getSetting('allowed_values');
    if (is_array($allowed) && array_is_list($allowed) && isset($allowed[0]['value'])) {
      $values = array_map(static fn(array $v) => (string) $v['value'], $allowed);
    }
    else {
      $values = array_map('strval', array_keys((array) $allowed));
    }
    sort($values);
    $this->assertSame(
      ['cancelled', 'confirmed', 'tentative'],
      $values,
      'Event status allowed values are confirmed/cancelled/tentative.'
    );

    $datetime = FieldStorageConfig::loadByName('node', 'field_event_datetime');
    $this->assertSame('datetime', $datetime->getSetting('datetime_type'));
    $this->assertSame(1, $datetime->getCardinality());

    $route = FieldConfig::loadByName('node', 'event', 'field_route');
    $this->assertSame('default:node', $route->getSetting('handler'));
    $handler_settings = $route->getSetting('handler_settings');
    $this->assertSame(
      ['route' => 'route'],
      $handler_settings['target_bundles'] ?? [],
      'field_route only targets the route bundle.'
    );

    $datetime_instance = FieldConfig::loadByName('node', 'event', 'field_event_datetime');
    $this->assertTrue(
      (bool) $datetime_instance->isRequired(),
      'field_event_datetime is required on event bundle.'
    );
  }

  /**
   * Optional legacy_url / legacy_id instances attach to news + page bundles.
   *
   * Both bundles are created in this test because the drupal_cms_news /
   * drupal_cms_page recipes are not available in a bare kernel test; the
   * fns_migrate optional configs should still resolve once those bundles
   * exist (i.e. when the recipes have been applied at runtime).
   */
  public function testLegacyFieldsAttachToNewsAndPageBundles(): void {
    foreach (['news', 'page'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => ucfirst($bundle)])->save();
    }
    /** @var \Drupal\Core\Config\ConfigInstallerInterface $installer */
    $installer = \Drupal::service('config.installer');
    $installer->installOptionalConfig();

    foreach (['news', 'page'] as $bundle) {
      foreach (['field_legacy_url', 'field_legacy_id'] as $field_name) {
        $instance = FieldConfig::loadByName('node', $bundle, $field_name);
        $this->assertNotNull(
          $instance,
          sprintf(
            'Field instance node.%s.%s is installed once the bundle exists.',
            $bundle,
            $field_name
          )
        );
      }
    }
  }

}
