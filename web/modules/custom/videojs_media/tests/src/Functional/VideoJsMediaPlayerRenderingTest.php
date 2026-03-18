<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests VideoJsMedia player rendering in different view modes.
 *
 * @group videojs_media
 */
class VideoJsMediaPlayerRenderingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with view permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $viewUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->viewUser = $this->drupalCreateUser([
      'view local_video videojs media',
      'view youtube videojs media',
      'view remote_video videojs media',
      'access content',
    ]);
  }

  /**
   * Tests rendering local video in default view mode.
   */
  public function testRenderLocalVideoDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Test Local Video',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Local Video');
  }

  /**
   * Tests rendering YouTube video in default view mode.
   */
  public function testRenderYoutubeDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'Test YouTube Video',
      'status' => TRUE,
      'field_youtube_url' => [
        'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      ],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test YouTube Video');
  }

  /**
   * Tests rendering remote video in default view mode.
   */
  public function testRenderRemoteVideoDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'remote_video',
      'name' => 'Test Remote Video',
      'status' => TRUE,
      'field_remote_url' => [
        'uri' => 'https://example.com/video.mp4',
      ],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Remote Video');
  }

  /**
   * Tests rendering in teaser view mode.
   */
  public function testRenderTeaserViewMode(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Test Video Teaser',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);

    // Render entity in teaser view mode.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('videojs_media');
    $build = $view_builder->view($entity, 'teaser');
    $rendered = \Drupal::service('renderer')->renderRoot($build);

    $this->assertNotEmpty($rendered);
  }

  /**
   * Tests access to unpublished entity.
   */
  public function testUnpublishedEntityAccess(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Unpublished Video',
      'status' => FALSE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");

    // User without unpublished view permission should get access denied.
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests canonical route access.
   */
  public function testCanonicalRoute(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Canonical Route Test',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet($entity->toUrl()->toString());

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Canonical Route Test');
  }

  /**
   * Tests rendering with subtitle field.
   */
  public function testRenderWithSubtitle(): void {
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Video With Subtitle',
      'status' => TRUE,
      'field_subtitle' => [
        'value' => 'This is a subtitle',
        'format' => 'plain_text',
      ],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Video With Subtitle');
  }

}
