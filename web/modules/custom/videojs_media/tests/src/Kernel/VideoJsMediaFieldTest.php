<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests bundle-specific fields for VideoJsMedia entities.
 *
 * @group videojs_media
 */
class VideoJsMediaFieldTest extends KernelTestBase {

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
    $this->installConfig(['field', 'file', 'videojs_media']);
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Tests that local video bundle has media file field.
   */
  public function testLocalVideoFields(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Test Local Video',
      'status' => TRUE,
    ]);

    // Check for media file field.
    $this->assertTrue($entity->hasField('field_media_file'));
    $this->assertTrue($entity->hasField('field_subtitle'));
    $this->assertTrue($entity->hasField('field_poster_image'));
  }

  /**
   * Tests that local audio bundle has media file field.
   */
  public function testLocalAudioFields(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_audio',
      'name' => 'Test Local Audio',
      'status' => TRUE,
    ]);

    // Check for media file field.
    $this->assertTrue($entity->hasField('field_media_file'));
    $this->assertTrue($entity->hasField('field_subtitle'));
    $this->assertTrue($entity->hasField('field_poster_image'));
  }

  /**
   * Tests that remote video bundle has remote URL field.
   */
  public function testRemoteVideoFields(): void {
    $entity = VideoJsMedia::create([
      'type' => 'remote_video',
      'name' => 'Test Remote Video',
      'status' => TRUE,
    ]);

    // Check for remote URL field.
    $this->assertTrue($entity->hasField('field_remote_url'));
    $this->assertTrue($entity->hasField('field_subtitle'));
    $this->assertTrue($entity->hasField('field_poster_image'));
  }

  /**
   * Tests that remote audio bundle has remote URL field.
   */
  public function testRemoteAudioFields(): void {
    $entity = VideoJsMedia::create([
      'type' => 'remote_audio',
      'name' => 'Test Remote Audio',
      'status' => TRUE,
    ]);

    // Check for remote URL field.
    $this->assertTrue($entity->hasField('field_remote_url'));
    $this->assertTrue($entity->hasField('field_subtitle'));
    $this->assertTrue($entity->hasField('field_poster_image'));
  }

  /**
   * Tests that YouTube bundle has YouTube URL field.
   */
  public function testYoutubeFields(): void {
    $entity = VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'Test YouTube Video',
      'status' => TRUE,
    ]);

    // Check for YouTube URL field.
    $this->assertTrue($entity->hasField('field_youtube_url'));
    $this->assertTrue($entity->hasField('field_subtitle'));
    $this->assertTrue($entity->hasField('field_poster_image'));
  }

  /**
   * Tests setting field values on local video.
   */
  public function testSetLocalVideoFieldValues(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Test Local Video',
      'status' => TRUE,
    ]);
    $entity->save();

    // Test subtitle field (text field).
    $entity->set('field_subtitle', [
      'value' => 'Test subtitle text',
      'format' => 'plain_text',
    ]);
    $entity->save();

    $loaded = VideoJsMedia::load($entity->id());
    $this->assertEquals('Test subtitle text', $loaded->get('field_subtitle')->value);
  }

  /**
   * Tests setting remote URL field value.
   */
  public function testSetRemoteUrlFieldValue(): void {
    $entity = VideoJsMedia::create([
      'type' => 'remote_video',
      'name' => 'Test Remote Video',
      'status' => TRUE,
    ]);

    $entity->set('field_remote_url', [
      'uri' => 'https://example.com/video.mp4',
    ]);
    $entity->save();

    $loaded = VideoJsMedia::load($entity->id());
    $this->assertEquals('https://example.com/video.mp4', $loaded->get('field_remote_url')->uri);
  }

  /**
   * Tests setting YouTube URL field value.
   */
  public function testSetYoutubeUrlFieldValue(): void {
    $entity = VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'Test YouTube Video',
      'status' => TRUE,
    ]);

    $entity->set('field_youtube_url', [
      'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);
    $entity->save();

    $loaded = VideoJsMedia::load($entity->id());
    $this->assertEquals('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $loaded->get('field_youtube_url')->uri);
  }

}
