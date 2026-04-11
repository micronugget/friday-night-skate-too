<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests that rendered pages include preload="none" on video elements.
 *
 * @group videojs_media
 */
class PreloadNonePageTest extends BrowserTestBase {

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
   * Tests that a local video entity page renders preload="none".
   */
  public function testLocalVideoPageHasPreloadNone(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Preload Test Video',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('preload="none"');
  }

  /**
   * Tests that a YouTube entity page renders preload="none".
   */
  public function testYoutubePageHasPreloadNone(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'Preload Test YouTube',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('preload="none"');
  }

  /**
   * Tests that a remote video entity page renders preload="none".
   */
  public function testRemoteVideoPageHasPreloadNone(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'Preload Test Remote',
      'status' => TRUE,
      'field_remote_url' => [
        'uri' => 'https://example.com/video.mp4',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('preload="none"');
  }

}
