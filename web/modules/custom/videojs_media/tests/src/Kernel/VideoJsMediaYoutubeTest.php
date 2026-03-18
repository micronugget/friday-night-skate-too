<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests YouTube URL field integration.
 *
 * @group videojs_media
 */
class VideoJsMediaYoutubeTest extends KernelTestBase {

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
    'videojs_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('videojs_media');
    $this->installConfig(['videojs_media']);
  }

  /**
   * Tests creating YouTube entity with URL.
   */
  public function testCreateYoutubeEntity(): void {
    $entity = VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'Test YouTube Video',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ],
    ]);
    $entity->save();

    $this->assertNotEmpty($entity->id());
    $this->assertEquals('youtube', $entity->bundle());
  }

  /**
   * Tests YouTube URL validation.
   *
   * @dataProvider youtubeUrlProvider
   */
  public function testYoutubeUrlFormats(string $url): void {
    $entity = VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'Test YouTube Video',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => $url,
      ],
    ]);
    $entity->save();

    $loaded = VideoJsMedia::load($entity->id());
    $this->assertEquals($url, $loaded->get('field_youtube_url')->uri);
  }

  /**
   * Tests loading YouTube entity with URL.
   */
  public function testLoadYoutubeEntity(): void {
    $entity = VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'Test YouTube Video',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=abc123',
      ],
    ]);
    $entity->save();

    $loaded = VideoJsMedia::load($entity->id());
    $this->assertInstanceOf(VideoJsMedia::class, $loaded);
    $this->assertEquals('https://www.youtube.com/watch?v=abc123', $loaded->get('field_youtube_url')->uri);
  }

  /**
   * Tests updating YouTube URL.
   */
  public function testUpdateYoutubeUrl(): void {
    $entity = VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'Test YouTube Video',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=original',
      ],
    ]);
    $entity->save();

    // Update URL.
    $entity->set('field_youtube_url', [
      'uri' => 'https://www.youtube.com/watch?v=updated',
    ]);
    $entity->save();

    $loaded = VideoJsMedia::load($entity->id());
    $this->assertEquals('https://www.youtube.com/watch?v=updated', $loaded->get('field_youtube_url')->uri);
  }

  /**
   * Data provider for YouTube URLs.
   */
  public static function youtubeUrlProvider(): array {
    return [
      'standard_url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
      'short_url' => ['https://youtu.be/dQw4w9WgXcQ'],
      'embed_url' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
    ];
  }

}
