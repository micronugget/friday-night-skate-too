<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the videojs_media install and update hooks.
 *
 * @group videojs_media
 *
 * @covers ::videojs_media_update_10001
 */
#[RunTestsInSeparateProcesses]
class VideoJsMediaUpdateTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'file',
    'image',
    'options',
    'file_upload_secure_validator',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('videojs_media');
    // Do NOT install videojs_media config so that the teaser configs are
    // absent before the update hook runs, which is the state it is designed
    // to fix on existing installations.
    $this->loadInstallFile();
  }

  /**
   * Loads the videojs_media.install file so update hooks are callable.
   */
  protected function loadInstallFile(): void {
    $module_path = $this->container->get('extension.list.module')
      ->getPath('videojs_media');
    // phpcs:ignore
    require_once \Drupal::root() . '/' . $module_path . '/videojs_media.install';
  }

  /**
   * Tests that videojs_media_update_10001() imports the teaser view mode.
   */
  public function testUpdate10001ImportsTeaserViewMode(): void {
    $config_storage = $this->container->get('config.storage');

    $this->assertFalse(
      $config_storage->exists('core.entity_view_mode.videojs_media.teaser'),
      'Teaser view mode must not exist before the update hook runs.'
    );

    videojs_media_update_10001();

    $this->assertTrue(
      $config_storage->exists('core.entity_view_mode.videojs_media.teaser'),
      'Teaser view mode was imported by videojs_media_update_10001().'
    );
  }

  /**
   * Tests update_10001 imports teaser view displays for all bundles.
   */
  public function testUpdate10001ImportsTeaserDisplaysForAllBundles(): void {
    $config_storage = $this->container->get('config.storage');

    videojs_media_update_10001();

    foreach (['local_video', 'local_audio', 'remote_video', 'remote_audio', 'youtube'] as $bundle) {
      $config_name = "core.entity_view_display.videojs_media.$bundle.teaser";
      $this->assertTrue(
        $config_storage->exists($config_name),
        "Teaser view display for '$bundle' was imported by videojs_media_update_10001()."
      );
    }
  }

  /**
   * Tests that videojs_media_update_10001() does not overwrite existing config.
   *
   * The hook is designed to be safe to run on sites that have already
   * imported the teaser config manually.
   */
  public function testUpdate10001SkipsExistingConfig(): void {
    $config_factory = $this->container->get('config.factory');

    // Pre-seed the teaser view mode with a sentinel label value.
    $config_factory->getEditable('core.entity_view_mode.videojs_media.teaser')
      ->setData([
        'id' => 'videojs_media.teaser',
        'label' => 'Pre-existing sentinel',
        'targetEntityType' => 'videojs_media',
        'cache' => TRUE,
        'langcode' => 'en',
        'status' => TRUE,
        'dependencies' => ['module' => ['videojs_media']],
      ])
      ->save();

    videojs_media_update_10001();

    // The sentinel label must remain; the hook must not overwrite it.
    $this->assertEquals(
      'Pre-existing sentinel',
      $config_factory->get('core.entity_view_mode.videojs_media.teaser')->get('label')
    );
  }

  /**
   * Tests that videojs_media_update_10001() returns a translatable string.
   */
  public function testUpdate10001ReturnsMessage(): void {
    $result = videojs_media_update_10001();
    $this->assertNotEmpty((string) $result);
  }

}
