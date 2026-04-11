<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the player component renders the lazy-init facade markup.
 *
 * Verifies the facade wrapper, poster image slot, accessible play button,
 * and hidden video element are present — and that no eager VideoJS
 * initialization attribute (data-videojs-initialized) is present on load.
 *
 * @group videojs_media
 */
class LazyPlayerFacadeTest extends KernelTestBase {

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
   * Renders the player component with the given props and returns HTML.
   *
   * @param array $props
   *   Component props to pass.
   *
   * @return string
   *   Rendered HTML string.
   */
  private function renderPlayer(array $props): string {
    $build = [
      '#type' => 'component',
      '#component' => 'videojs_media:player',
      '#props' => $props + [
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
    return (string) \Drupal::service('renderer')->renderRoot($build);
  }

  /**
   * Tests that the facade wrapper div is rendered.
   */
  public function testFacadeWrapperIsRendered(): void {
    $rendered = $this->renderPlayer([]);
    $this->assertStringContainsString(
      'class="videojs-lazy-facade"',
      $rendered,
      'Facade wrapper div must be present.',
    );
    $this->assertStringContainsString(
      'data-lazy-player="true"',
      $rendered,
      'Facade wrapper must have data-lazy-player="true".',
    );
  }

  /**
   * Tests that the accessible play button is rendered with correct attributes.
   */
  public function testFacadePlayButtonAccessibility(): void {
    $rendered = $this->renderPlayer([]);
    $this->assertStringContainsString(
      'class="videojs-lazy-facade__play-btn"',
      $rendered,
      'Facade play button must be present.',
    );
    $this->assertStringContainsString(
      'role="button"',
      $rendered,
      'Play button must have role="button".',
    );
    $this->assertStringContainsString(
      'tabindex="0"',
      $rendered,
      'Play button must be keyboard-focusable (tabindex="0").',
    );
    $this->assertStringContainsString(
      'aria-label=',
      $rendered,
      'Play button must have an aria-label attribute.',
    );
  }

  /**
   * Tests that the hidden video element has the lazy-target class.
   */
  public function testVideoElementHasLazyTargetClass(): void {
    $rendered = $this->renderPlayer([]);
    $this->assertStringContainsString(
      'videojs-lazy-target',
      $rendered,
      'Video element must have the videojs-lazy-target class.',
    );
    $this->assertStringContainsString(
      'class="video-js videojs-lazy-target"',
      $rendered,
      'Video element must have both video-js and videojs-lazy-target classes.',
    );
  }

  /**
   * Tests that no data-videojs-initialized attribute is present on page load.
   */
  public function testNoEagerInitializationAttribute(): void {
    $rendered = $this->renderPlayer([]);
    $this->assertStringNotContainsString(
      'data-videojs-initialized',
      $rendered,
      'data-videojs-initialized must not be present on initial render — players are lazy.',
    );
  }

  /**
   * Tests that no data-setup attribute is present (no VideoJS auto-init).
   */
  public function testNoDataSetupAttribute(): void {
    $rendered = $this->renderPlayer([]);
    $this->assertStringNotContainsString(
      'data-setup',
      $rendered,
      'data-setup must not be present — VideoJS auto-init must be disabled.',
    );
  }

  /**
   * Tests that audio bundles do not render a play button facade.
   */
  public function testAudioBundleHasNoPlayButton(): void {
    $rendered = $this->renderPlayer(['bundle' => 'videojs_local_audio']);
    $this->assertStringNotContainsString(
      'videojs-lazy-facade__play-btn',
      $rendered,
      'Audio players must not render a facade play button.',
    );
  }

  /**
   * Tests that audio bundles still render the facade wrapper.
   */
  public function testAudioBundleHasFacadeWrapper(): void {
    $rendered = $this->renderPlayer(['bundle' => 'videojs_local_audio']);
    $this->assertStringContainsString(
      'data-lazy-player="true"',
      $rendered,
      'Audio facade wrapper must still have data-lazy-player="true".',
    );
    $this->assertStringContainsString(
      'data-lazy-player-audio="true"',
      $rendered,
      'Audio facade wrapper must have data-lazy-player-audio="true".',
    );
  }

  /**
   * Tests that preload="none" is set on the video element.
   */
  public function testPreloadNoneAttribute(): void {
    $rendered = $this->renderPlayer([]);
    $this->assertStringContainsString(
      'preload="none"',
      $rendered,
      'Video element must have preload="none" to prevent eager buffering.',
    );
  }

}
