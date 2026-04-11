<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests that rendered player pages do not contain a data-setup attribute.
 *
 * Ensures the Drupal behavior is the single VideoJS initialization path —
 * no data-setup attribute means Video.js auto-init is disabled and only
 * the JS behavior initializes the player.
 *
 * @group videojs_media
 */
class SingleInitializationTest extends BrowserTestBase {

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
   * Tests that a local video page does not contain data-setup.
   */
  public function testLocalVideoPageHasNoDataSetup(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Single Init Test Video',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('data-setup');
    $this->assertSession()->responseContains('class="video-js"');
  }

  /**
   * Tests that a YouTube entity page does not contain data-setup.
   */
  public function testYoutubePageHasNoDataSetup(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'Single Init Test YouTube',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('data-setup');
    $this->assertSession()->responseContains('class="video-js"');
  }

  /**
   * Tests that a remote video page does not contain data-setup.
   */
  public function testRemoteVideoPageHasNoDataSetup(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'Single Init Test Remote',
      'status' => TRUE,
      'field_remote_url' => [
        'uri' => 'https://example.com/video.mp4',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('data-setup');
    $this->assertSession()->responseContains('class="video-js"');
  }

}
