<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\pathauto\Entity\PathautoPattern;

/**
 * Verifies the archive_media pathauto pattern shipped with fns_archive.
 *
 * @group fns_archive
 * @group pathauto
 */
class PathautoArchivePatternTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'filter',
    'user',
    'node',
    'path',
    'path_alias',
    'token',
    'ctools',
    'pathauto',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'pathauto']);

    // Install the archive_media pathauto pattern directly from fns_archive's
    // config/optional directory without requiring the full module install.
    $config_path = \Drupal::root() . '/../web/modules/custom/fns_archive/config/optional';
    $source = new FileStorage($config_path);
    $data = $source->read('pathauto.pattern.archive_media_pattern');
    \Drupal::service('config.storage')->write('pathauto.pattern.archive_media_pattern', $data);
    \Drupal::service('config.factory')->reset('pathauto.pattern.archive_media_pattern');
  }

  /**
   * Tests that the archive_media pathauto pattern config is correct.
   */
  public function testPatternConfigExists(): void {
    $pattern = PathautoPattern::load('archive_media_pattern');
    $this->assertNotNull($pattern, 'archive_media pathauto pattern entity exists.');
  }

  /**
   * Tests the pattern label.
   */
  public function testPatternLabel(): void {
    $pattern = PathautoPattern::load('archive_media_pattern');
    $this->assertEquals('Archive Media', $pattern->label(), 'Pattern label is "Archive Media".');
  }

  /**
   * Tests that the pattern is enabled.
   */
  public function testPatternIsEnabled(): void {
    $pattern = PathautoPattern::load('archive_media_pattern');
    $this->assertTrue($pattern->status(), 'Archive media pathauto pattern is enabled.');
  }

  /**
   * Tests the URL alias pattern string.
   */
  public function testPatternString(): void {
    $pattern = PathautoPattern::load('archive_media_pattern');
    $this->assertEquals(
      '/archive/[node:field_skate_date:entity:name]/[node:nid]',
      $pattern->getPattern(),
      'Pattern string uses skate_date taxonomy term name and node ID.'
    );
  }

  /**
   * Tests the pattern applies to node entities (canonical type).
   */
  public function testPatternType(): void {
    $pattern = PathautoPattern::load('archive_media_pattern');
    $this->assertEquals(
      'canonical_entities:node',
      $pattern->getType(),
      'Pattern type targets canonical node entities.'
    );
  }

  /**
   * Tests that the pattern targets the archive_media bundle.
   */
  public function testPatternTargetsArchiveMediaBundle(): void {
    $pattern = PathautoPattern::load('archive_media_pattern');
    $conditions = $pattern->getSelectionConditions();
    $this->assertNotEmpty($conditions, 'Pattern has bundle selection conditions.');

    $found = FALSE;
    foreach ($conditions as $condition) {
      $config = $condition->getConfiguration();
      if (isset($config['bundles']['archive_media'])) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, 'Pattern selection criteria includes archive_media bundle.');
  }

}
