<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests all 10 bundle × view-mode combinations for VideoJS media.
 *
 * Matrix:
 *   local_video   × default, teaser
 *   local_audio   × default, teaser
 *   remote_video  × default, teaser
 *   remote_audio  × default, teaser
 *   youtube       × default, teaser.
 *
 * @group videojs_media
 */
class VideoJsMediaViewModeMatrixTest extends BrowserTestBase {

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
   * A user with view permissions for all bundle types.
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
      'administer videojs media',
      'access content',
    ]);
  }

  /**
   * Helper: render an entity in a given view mode and assert non-empty output.
   *
   * @param \Drupal\videojs_media\Entity\VideoJsMedia $entity
   *   The entity to render.
   * @param string $view_mode
   *   The view mode machine name.
   */
  protected function assertViewModeRenders(VideoJsMedia $entity, string $view_mode): void {
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('videojs_media');
    $build = $view_builder->view($entity, $view_mode);
    $rendered = (string) \Drupal::service('renderer')->renderRoot($build);
    $this->assertNotEmpty($rendered, sprintf(
      'Bundle "%s" in view mode "%s" produced empty output.',
      $entity->bundle(),
      $view_mode
    ));
  }

  // -----------------------------------------------------------------------
  // local_video
  // -----------------------------------------------------------------------

  /**
   * Tests local_video — default view mode.
   */
  public function testLocalVideoDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Local Video Default',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Local Video Default');
    $this->assertViewModeRenders($entity, 'default');
  }

  /**
   * Tests local_video — teaser view mode.
   */
  public function testLocalVideoTeaser(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Local Video Teaser',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->assertViewModeRenders($entity, 'teaser');
  }

  // -----------------------------------------------------------------------
  // local_audio
  // -----------------------------------------------------------------------

  /**
   * Tests local_audio — default view mode.
   */
  public function testLocalAudioDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_audio',
      'name' => 'Local Audio Default',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Local Audio Default');
    $this->assertViewModeRenders($entity, 'default');
  }

  /**
   * Tests local_audio — teaser view mode.
   */
  public function testLocalAudioTeaser(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_audio',
      'name' => 'Local Audio Teaser',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->assertViewModeRenders($entity, 'teaser');
  }

  // -----------------------------------------------------------------------
  // remote_video
  // -----------------------------------------------------------------------

  /**
   * Tests remote_video — default view mode.
   */
  public function testRemoteVideoDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'Remote Video Default',
      'status' => TRUE,
      'field_remote_url' => ['uri' => 'https://example.com/video.mp4'],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Remote Video Default');
    $this->assertViewModeRenders($entity, 'default');
  }

  /**
   * Tests remote_video — teaser view mode.
   */
  public function testRemoteVideoTeaser(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'Remote Video Teaser',
      'status' => TRUE,
      'field_remote_url' => ['uri' => 'https://example.com/video.mp4'],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->assertViewModeRenders($entity, 'teaser');
  }

  // -----------------------------------------------------------------------
  // remote_audio
  // -----------------------------------------------------------------------

  /**
   * Tests remote_audio — default view mode.
   */
  public function testRemoteAudioDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_audio',
      'name' => 'Remote Audio Default',
      'status' => TRUE,
      'field_remote_url' => ['uri' => 'https://example.com/audio.mp3'],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Remote Audio Default');
    $this->assertViewModeRenders($entity, 'default');
  }

  /**
   * Tests remote_audio — teaser view mode.
   */
  public function testRemoteAudioTeaser(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_audio',
      'name' => 'Remote Audio Teaser',
      'status' => TRUE,
      'field_remote_url' => ['uri' => 'https://example.com/audio.mp3'],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->assertViewModeRenders($entity, 'teaser');
  }

  // -----------------------------------------------------------------------
  // youtube
  // -----------------------------------------------------------------------

  /**
   * Tests youtube — default view mode.
   */
  public function testYoutubeDefault(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'YouTube Default',
      'status' => TRUE,
      'field_youtube_url' => ['uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('YouTube Default');
    $this->assertViewModeRenders($entity, 'default');
  }

  /**
   * Tests youtube — teaser view mode.
   */
  public function testYoutubeTeaser(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'YouTube Teaser',
      'status' => TRUE,
      'field_youtube_url' => ['uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    ]);
    $entity->save();

    $this->drupalLogin($this->viewUser);
    $this->assertViewModeRenders($entity, 'teaser');
  }

}
