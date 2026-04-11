<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the player template does not render a data-setup attribute.
 *
 * @group videojs_media
 */
class PlayerTemplateTest extends KernelTestBase {

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
   * Tests that the video element does not contain a data-setup attribute.
   */
  public function testVideoElementHasNoDataSetup(): void {
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

    $this->assertStringNotContainsString('data-setup', $rendered, 'Video element must not have a data-setup attribute.');
    $this->assertStringContainsString('class="video-js"', $rendered, 'Video element must have class="video-js".');
    $this->assertStringContainsString('<video', $rendered, 'A <video> element must be rendered for video bundles.');
  }

  /**
   * Tests that the audio element does not contain a data-setup attribute.
   */
  public function testAudioElementHasNoDataSetup(): void {
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

    $this->assertStringNotContainsString('data-setup', $rendered, 'Audio element must not have a data-setup attribute.');
    $this->assertStringContainsString('class="video-js"', $rendered, 'Audio element must have class="video-js".');
    $this->assertStringContainsString('<audio', $rendered, 'An <audio> element must be rendered for audio bundles.');
  }

  /**
   * Tests that the audio element has the data-videojs-audio attribute.
   */
  public function testAudioElementHasAudioDataAttribute(): void {
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

    $this->assertStringContainsString(
      'data-videojs-audio="true"',
      $rendered,
      'Audio element must have data-videojs-audio="true" for JS fluid detection.',
    );
  }

  /**
   * Tests that the video element does not have the data-videojs-audio attr.
   */
  public function testVideoElementHasNoAudioDataAttribute(): void {
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

    $this->assertStringNotContainsString('data-videojs-audio', $rendered, 'Video element must not have data-videojs-audio attribute.');
  }

}
