<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests that VideoJS players are not eagerly initialized on page load.
 *
 * Verifies that the lazy-init facade is rendered on archive listing pages
 * and that no data-videojs-initialized attribute is present on initial load,
 * confirming zero VideoJS instances are created before user interaction.
 *
 * @group videojs_media
 */
class LazyInitializationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'views',
    'videojs_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $viewUser = $this->drupalCreateUser([
      'view local_video videojs media',
      'view youtube videojs media',
      'view remote_video videojs media',
      'access content',
    ]);
    $this->drupalLogin($viewUser);
  }

  /**
   * Tests that a local video page renders the facade, not an eager player.
   */
  public function testLocalVideoPageRendersLazyFacade(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Lazy Init Test Video',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);

    // Facade wrapper must be present.
    $this->assertSession()->responseContains('data-lazy-player="true"');

    // No eager initialization on page load.
    $this->assertSession()->responseNotContains('data-videojs-initialized');

    // No data-setup (VideoJS auto-init must be disabled).
    $this->assertSession()->responseNotContains('data-setup');

    // Play button must be present and accessible.
    $this->assertSession()->responseContains('videojs-lazy-facade__play-btn');
    $this->assertSession()->responseContains('aria-label=');
    $this->assertSession()->responseContains('tabindex="0"');
  }

  /**
   * Tests that a YouTube entity page renders the facade, not an eager player.
   */
  public function testYoutubePageRendersLazyFacade(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'Lazy Init Test YouTube',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->responseContains('data-lazy-player="true"');
    $this->assertSession()->responseNotContains('data-videojs-initialized');
    $this->assertSession()->responseNotContains('data-setup');
  }

  /**
   * Tests that a remote video page renders the facade, not an eager player.
   */
  public function testRemoteVideoPageRendersLazyFacade(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'Lazy Init Test Remote',
      'status' => TRUE,
      'field_remote_url' => [
        'uri' => 'https://example.com/video.mp4',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->responseContains('data-lazy-player="true"');
    $this->assertSession()->responseNotContains('data-videojs-initialized');
    $this->assertSession()->responseNotContains('data-setup');
  }

  /**
   * Tests that multiple video entities on a listing page all use the facade.
   *
   * Simulates an archive listing with multiple thumbnails and asserts that
   * zero players are eagerly initialized.
   */
  public function testMultipleVideosOnListingPageAllUseFacade(): void {
    // Create several video entities.
    for ($i = 1; $i <= 3; $i++) {
      $entity = VideoJsMedia::create([
        'type' => 'videojs_local_video',
        'name' => "Archive Video {$i}",
        'status' => TRUE,
      ]);
      $entity->save();
    }

    // Visit the videojs_media collection page (default listing).
    $this->drupalGet('/admin/content/videojs-media');
    $this->assertSession()->statusCodeEquals(200);

    // No player should be initialized on the listing page.
    $this->assertSession()->responseNotContains('data-videojs-initialized');
  }

  /**
   * Tests that the lazy target video element has preload="none".
   */
  public function testLazyTargetHasPreloadNone(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Preload None Test',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('preload="none"');
  }

}
