<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests that the VHS script is only loaded for adaptive stream sources.
 *
 * Verifies that MP4/YouTube pages do not include the http-streaming script,
 * and that HLS sources do include it.
 *
 * @group videojs_media
 */
class PlayerWithoutVhsTest extends BrowserTestBase {

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
   * Tests that an MP4 video page does not load the http-streaming script.
   */
  public function testMp4PageDoesNotLoadVhs(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'MP4 No VHS Test',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('videojs-http-streaming');
  }

  /**
   * Tests that a YouTube video page does not load the http-streaming script.
   */
  public function testYoutubePageDoesNotLoadVhs(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'YouTube No VHS Test',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('videojs-http-streaming');
  }

  /**
   * Tests that a remote HLS source page DOES load the http-streaming script.
   */
  public function testHlsPageLoadsVhs(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'HLS VHS Test',
      'status' => TRUE,
      'field_remote_url' => [
        'value' => 'https://example.com/stream/playlist.m3u8',
      ],
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('videojs-http-streaming');
  }

}
