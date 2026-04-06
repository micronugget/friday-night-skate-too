<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Functional;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests that the VideoJS library is attached on archive pages.
 *
 * Verifies that visiting /archive/{term} with at least one videojs_media entity
 * causes the videojs_media/videojs-player library assets (video-js.css and
 * video.js) to be present in the page response.
 *
 * @group fns_archive
 * @group functional
 * @group videojs
 */
class VideoJsLibraryAttachmentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'fridaynightskate';

  /**
   * {@inheritdoc}
   *
   * Disable strict config schema checking — the archive_by_date view config
   * has known schema gaps in Views argument plugins that do not affect runtime
   * behaviour and are outside the scope of this test.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'taxonomy',
    'datetime',
    'image',
    'media',
    'file',
    'views',
    'menu_ui',
    'content_moderation',
    'workflows',
    'videojs_media',
    'fns_archive',
  ];

  /**
   * A skate_dates taxonomy term used as the archive date.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!Vocabulary::load('skate_dates')) {
      Vocabulary::create([
        'vid' => 'skate_dates',
        'name' => 'Skate Dates',
      ])->save();
    }

    $this->term = Term::create([
      'vid' => 'skate_dates',
      'name' => 'March 2025',
    ]);
    $this->term->save();
  }

  /**
   * Tests that video-js.css is present in the archive page response.
   */
  public function testVideoJsCssAttachedOnArchivePage(): void {
    VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'FNS Library Test Video',
      'status' => TRUE,
      'field_remote_url' => 'https://example.com/test.mp4',
      'field_skate_date' => ['target_id' => $this->term->id()],
    ])->save();

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->responseContains('video-js.css');
  }

  /**
   * Tests that video.js is present in the archive page response.
   */
  public function testVideoJsScriptAttachedOnArchivePage(): void {
    VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'FNS Library Test Video JS',
      'status' => TRUE,
      'field_remote_url' => 'https://example.com/test2.mp4',
      'field_skate_date' => ['target_id' => $this->term->id()],
    ])->save();

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->responseContains('video.js');
  }

  /**
   * Tests that the modal-viewer library script is present on archive pages.
   */
  public function testModalViewerScriptAttachedOnArchivePage(): void {
    VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'FNS Modal Viewer Test',
      'status' => TRUE,
      'field_remote_url' => 'https://example.com/test3.mp4',
      'field_skate_date' => ['target_id' => $this->term->id()],
    ])->save();

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->responseContains('modal-viewer.js');
  }

}
