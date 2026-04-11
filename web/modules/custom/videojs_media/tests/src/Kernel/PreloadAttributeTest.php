<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the player template renders preload="none" on media elements.
 *
 * @group videojs_media
 */
class PreloadAttributeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'link',
    'image',
    'taxonomy',
    'videojs_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('videojs_media');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['field', 'file', 'videojs_media']);
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Tests that the video element renders with preload="none".
   */
  public function testVideoElementHasPreloadNone(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'videojs_media:player',
      '#props' => [
        'entity_type' => 'videojs_media',
        'bundle' => 'videojs_local_video',
        'field_media_file' => [],
        'field_remote_url' => [],
        'field_youtube_url' => [],
        'field_poster_image' => [],
        'field_subtitle' => [],
        'enable_viewport_monitoring' => FALSE,
      ],
    ];

    $rendered = (string) \Drupal::service('renderer')->renderRoot($build);

    $this->assertStringContainsString('preload="none"', $rendered, 'Video element must have preload="none".');
    $this->assertStringContainsString('<video', $rendered, 'A <video> element must be rendered for video bundles.');
  }

  /**
   * Tests that the audio element also renders with preload="none".
   */
  public function testAudioElementHasPreloadNone(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'videojs_media:player',
      '#props' => [
        'entity_type' => 'videojs_media',
        'bundle' => 'videojs_local_audio',
        'field_media_file' => [],
        'field_remote_url' => [],
        'field_youtube_url' => [],
        'field_poster_image' => [],
        'field_subtitle' => [],
        'enable_viewport_monitoring' => FALSE,
      ],
    ];

    $rendered = (string) \Drupal::service('renderer')->renderRoot($build);

    $this->assertStringContainsString('preload="none"', $rendered, 'Audio element must have preload="none".');
    $this->assertStringContainsString('<audio', $rendered, 'An <audio> element must be rendered for audio bundles.');
  }

}
