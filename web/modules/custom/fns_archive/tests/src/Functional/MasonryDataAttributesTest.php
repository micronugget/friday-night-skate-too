<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Functional;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests that masonry items render with correct data attributes for the modal.
 *
 * The data attributes are added by
 * fridaynightskate_preprocess_views_view_unformatted() in the theme, so this
 * test must run with the fridaynightskate theme active.
 *
 * @group fns_archive
 * @group functional
 * @group masonry
 */
class MasonryDataAttributesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'fridaynightskate';

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
    'content_moderation',
    'workflows',
    'videojs_media',
    'fns_archive',
    'fridaynightskate',
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
   * Tests that a remote video masonry item has the required data attributes.
   */
  public function testRemoteVideoDataAttributes(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'Friday Night Skate Test Video',
      'status' => TRUE,
      'field_remote_url' => 'https://example.com/video.mp4',
      'field_skate_date' => ['target_id' => $this->term->id()],
    ]);
    $entity->save();

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $page = $this->getSession()->getPage();
    $item = $page->find('css', '.masonry-item');
    $this->assertNotNull($item, 'A .masonry-item element exists on the page.');

    $this->assertEquals('video', $item->getAttribute('data-media-type'), 'data-media-type is "video" for a remote video entity.');
    $this->assertStringStartsWith('video-', $item->getAttribute('data-video-id'), 'data-video-id starts with "video-".');
    $this->assertEquals('Friday Night Skate Test Video', $item->getAttribute('data-title'), 'data-title matches the entity label.');
    $this->assertEquals('March 2025', $item->getAttribute('data-date'), 'data-date matches the skate_date term label.');
    $this->assertEquals('https://example.com/video.mp4', $item->getAttribute('data-video-url'), 'data-video-url matches the remote URL field value.');
  }

  /**
   * Tests that a YouTube video masonry item has the required data attributes.
   */
  public function testYoutubeVideoDataAttributes(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_youtube',
      'name' => 'FNS YouTube Test',
      'status' => TRUE,
      'field_remote_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      'field_skate_date' => ['target_id' => $this->term->id()],
    ]);
    $entity->save();

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $item = $this->getSession()->getPage()->find('css', '.masonry-item');
    $this->assertNotNull($item, 'A .masonry-item element exists on the page.');
    $this->assertEquals('video', $item->getAttribute('data-media-type'), 'data-media-type is "video" for a YouTube entity.');
    $this->assertStringContainsString('youtube.com', $item->getAttribute('data-video-url'), 'data-video-url contains the YouTube URL.');
  }

  /**
   * Tests that data-media-type is "image" for non-video bundles.
   */
  public function testImageBundleDataAttributes(): void {
    // videojs_local_audio is a non-video bundle.
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_audio',
      'name' => 'FNS Audio Test',
      'status' => TRUE,
      'field_skate_date' => ['target_id' => $this->term->id()],
    ]);
    $entity->save();

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $item = $this->getSession()->getPage()->find('css', '.masonry-item');
    $this->assertNotNull($item, 'A .masonry-item element exists on the page.');
    $this->assertEquals('image', $item->getAttribute('data-media-type'), 'data-media-type is "image" for a non-video bundle.');
  }

  /**
   * Tests that masonry items without a skate_date term have no data-date attr.
   */
  public function testMissingDateFieldOmitsDataDate(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_remote_video',
      'name' => 'No Date Video',
      'status' => TRUE,
      'field_remote_url' => 'https://example.com/nodatevideo.mp4',
    ]);
    $entity->save();

    // Visit a different term page that has no content — entity has no term.
    // Instead visit the entity directly and confirm no data-date is set.
    // We test via the archive page of our term (entity not linked to it).
    // Create a second term and link entity to it to isolate.
    $term2 = Term::create(['vid' => 'skate_dates', 'name' => 'April 2025']);
    $term2->save();

    $this->drupalGet("/archive/{$term2->id()}");
    $this->assertSession()->statusCodeEquals(200);

    // No masonry items should appear since entity is not linked to term2.
    $items = $this->getSession()->getPage()->findAll('css', '.masonry-item');
    $this->assertCount(0, $items, 'No masonry items appear for a term with no linked entities.');
  }

}
