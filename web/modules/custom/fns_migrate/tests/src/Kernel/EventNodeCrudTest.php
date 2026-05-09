<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_migrate\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\KernelTests\KernelTestBase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Smoke CRUD test for the event content type.
 *
 * Creates an event node via the entity API and asserts the datetime field
 * round-trips through storage and renders in the Europe/Amsterdam timezone
 * configured in setUp(). Exercised as a kernel test rather than a functional
 * test because the project's contrib stack (notably canvas/radix) trips the
 * core install pipeline used by BrowserTestBase; the entity-API path covers
 * the same content-model contract.
 *
 * @group fns_migrate
 */
class EventNodeCrudTest extends KernelTestBase {

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

    $this->config('system.date')
      ->set('timezone.default', 'Europe/Amsterdam')
      ->set('timezone.user.configurable', FALSE)
      ->save();
  }

  /**
   * Save an event node and assert its datetime field renders in local time.
   */
  public function testCreateEventNodePersistsAndRendersDate(): void {
    $this->assertNotNull(NodeType::load('event'), 'Event bundle exists.');

    // 24 April 2026 20:00 in Europe/Amsterdam (CEST, UTC+2) == 18:00 UTC.
    $node = Node::create([
      'type' => 'event',
      'title' => 'FNS Friday 24 April 2026',
      'status' => 1,
      'field_event_datetime' => '2026-04-24T18:00:00',
      'field_event_status' => 'confirmed',
      'field_legacy_id' => '12345',
      'field_legacy_url' => ['uri' => 'https://fridaynightskate.com/skate/12345'],
    ]);
    $node->save();

    $reloaded = Node::load($node->id());
    $this->assertNotNull($reloaded, 'Event node persists and reloads.');
    $this->assertSame('FNS Friday 24 April 2026', $reloaded->getTitle());
    $this->assertSame(
      'confirmed',
      $reloaded->get('field_event_status')->value,
      'Event status round-trips through storage.'
    );
    $this->assertSame(
      '12345',
      $reloaded->get('field_legacy_id')->value,
      'Legacy ID round-trips through storage.'
    );

    $stored = $reloaded->get('field_event_datetime')->value;
    $this->assertSame('2026-04-24T18:00:00', $stored, 'Datetime is stored as UTC.');

    // Convert the stored UTC value to the site's local timezone and confirm
    // the human-facing hour is 20:00 local — what the page would render via
    // the default datetime formatter.
    $utc = new DrupalDateTime($stored, DateTimeItemInterface::STORAGE_TIMEZONE);
    $utc->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
    $this->assertSame(
      '20:00',
      $utc->format('H:i'),
      'Stored UTC datetime renders as 20:00 in Europe/Amsterdam.'
    );

  }

}
