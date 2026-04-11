<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests that the HLS library is attached only for adaptive stream sources.
 *
 * @group videojs_media
 */
class ConditionalVhsLibraryTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'file',
    'image',
    'options',
    'file_upload_secure_validator',
    'link',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('videojs_media');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['videojs_media']);
  }

  /**
   * Creates a minimal EntityViewDisplay for the given bundle.
   */
  protected function createDisplay(string $bundle): EntityViewDisplay {
    return EntityViewDisplay::create([
      'targetEntityType' => 'videojs_media',
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }

  /**
   * Invokes the hook and returns the libraries attached to $build.
   */
  protected function getAttachedLibraries(VideoJsMedia $entity): array {
    $build = [];
    $display = $this->createDisplay($entity->bundle());
    videojs_media_videojs_media_view($build, $entity, $display, 'default');
    return $build['#attached']['library'] ?? [];
  }

  /**
   * Tests that an MP4 local video does NOT attach the HLS library.
   */
  public function testMp4DoesNotAttachHlsLibrary(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'MP4 Test',
      'status' => TRUE,
    ]);
    $entity->save();

    $libraries = $this->getAttachedLibraries($entity);
    $this->assertNotContains('videojs_media/videojs-player-hls', $libraries,
      'MP4 source must not attach the HLS library.');
  }

  /**
   * Tests that a YouTube entity does NOT attach the HLS library.
   */
  public function testYoutubeDoesNotAttachHlsLibrary(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'YouTube Test',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ],
    ]);
    $entity->save();

    $libraries = $this->getAttachedLibraries($entity);
    $this->assertNotContains('videojs_media/videojs-player-hls', $libraries,
      'YouTube source must not attach the HLS library.');
  }

  /**
   * Tests that a remote HLS (.m3u8) source DOES attach the HLS library.
   */
  public function testRemoteHlsAttachesHlsLibrary(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'HLS Remote Test',
      'status' => TRUE,
      'field_remote_url' => [
        'value' => 'https://example.com/stream/playlist.m3u8',
      ],
    ]);
    $entity->save();

    $libraries = $this->getAttachedLibraries($entity);
    $this->assertContains('videojs_media/videojs-player-hls', $libraries,
      'Remote HLS (.m3u8) source must attach the HLS library.');
  }

  /**
   * Tests that a remote DASH (.mpd) source DOES attach the HLS library.
   */
  public function testRemoteDashAttachesHlsLibrary(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'DASH Remote Test',
      'status' => TRUE,
      'field_remote_url' => [
        'value' => 'https://example.com/stream/manifest.mpd',
      ],
    ]);
    $entity->save();

    $libraries = $this->getAttachedLibraries($entity);
    $this->assertContains('videojs_media/videojs-player-hls', $libraries,
      'Remote DASH (.mpd) source must attach the HLS library.');
  }

}
