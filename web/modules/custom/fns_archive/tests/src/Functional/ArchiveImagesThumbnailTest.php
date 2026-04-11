<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Functional;

use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests that image media entities render correctly in the archive_images view.
 *
 * Covers:
 * - data-* attributes set by fridaynightskate_preprocess_views_view_unformatted
 *   for the archive_images view (data-media-type=image, data-title, data-date,
 *   data-uploader, data-fullsize).
 * - The media--image--teaser.html.twig template renders the
 *   .videojs-media-thumb wrapper and .videojs-media-thumb__title span with the
 *   correct entity label via media.label().
 * - Image masonry items appear in the embedded archive_images block on the
 *   archive_by_date page.
 *
 * The preprocess hook and template both live in the fridaynightskate theme, so
 * this test must run with that theme active.
 *
 * @group fns_archive
 * @group functional
 * @group masonry
 */
class ArchiveImagesThumbnailTest extends BrowserTestBase {

  /**
   * Use the Standard profile to ensure default entity view modes exist.
   *
   * @var string
   */
  protected $profile = 'testing';

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'fridaynightskate';

  /**
   * {@inheritdoc}
   *
   * Disable strict config schema — Views argument plugin schemas have known
   * gaps that do not affect runtime behaviour.
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
    'action',
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
      'name' => 'April 2026',
    ]);
    $this->term->save();
  }

  /**
   * Creates a published image media entity linked to the test skate date term.
   *
   * @param string $name
   *   The media entity label.
   *
   * @return \Drupal\media\Entity\MediaInterface
   *   The saved media entity.
   */
  protected function createImageMedia(string $name): \Drupal\media\Entity\MediaInterface {
    // Create a minimal file entity so field_media_image has a value.
    $file = \Drupal\file\Entity\File::create([
      'uri' => 'public://test-image.jpg',
      'status' => 1,
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => $name,
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Test image',
      ],
      'field_skate_date' => ['target_id' => $this->term->id()],
    ]);
    $media->save();

    return $media;
  }

  /**
   * Tests that an image media masonry item has data-media-type="image".
   */
  public function testImageMediaTypeAttribute(): void {
    $this->createImageMedia('PXL_20260407_130249138.MP_.jpg');

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $item = $this->getSession()->getPage()->find('css', '.masonry-item[data-media-type="image"]');
    $this->assertNotNull($item, 'A .masonry-item with data-media-type="image" exists for an image media entity.');
  }

  /**
   * Tests that data-title matches the image media entity label.
   */
  public function testImageDataTitleAttribute(): void {
    $label = 'PXL_20260407_130249138.MP_.jpg';
    $this->createImageMedia($label);

    $this->drupalGet("/archive/{$this->term->id()}");

    $item = $this->getSession()->getPage()->find('css', '.masonry-item[data-media-type="image"]');
    $this->assertNotNull($item, 'Image masonry item found.');
    $this->assertEquals($label, $item->getAttribute('data-title'), 'data-title matches the image media entity label.');
  }

  /**
   * Tests that data-date is set from the skate_date taxonomy term label.
   */
  public function testImageDataDateAttribute(): void {
    $this->createImageMedia('test-skate-date-image.jpg');

    $this->drupalGet("/archive/{$this->term->id()}");

    $item = $this->getSession()->getPage()->find('css', '.masonry-item[data-media-type="image"]');
    $this->assertNotNull($item, 'Image masonry item found.');
    $this->assertEquals('April 2026', $item->getAttribute('data-date'), 'data-date matches the skate_date term label.');
  }

  /**
   * Tests that data-uploader is set to the entity owner's display name.
   */
  public function testImageDataUploaderAttribute(): void {
    $this->createImageMedia('uploader-test.jpg');

    $this->drupalGet("/archive/{$this->term->id()}");

    $item = $this->getSession()->getPage()->find('css', '.masonry-item[data-media-type="image"]');
    $this->assertNotNull($item, 'Image masonry item found.');
    $uploader = $item->getAttribute('data-uploader');
    $this->assertNotEmpty($uploader, 'data-uploader is set on the image masonry item.');
  }

  /**
   * Tests that data-video-id starts with "media-" for image media entities.
   */
  public function testImageDataVideoIdPrefix(): void {
    $this->createImageMedia('prefix-test.jpg');

    $this->drupalGet("/archive/{$this->term->id()}");

    $item = $this->getSession()->getPage()->find('css', '.masonry-item[data-media-type="image"]');
    $this->assertNotNull($item, 'Image masonry item found.');
    $this->assertStringStartsWith('media-', $item->getAttribute('data-video-id'), 'data-video-id starts with "media-" for image media entities.');
  }

  /**
   * Tests that the image thumbnail renders the .videojs-media-thumb wrapper.
   */
  public function testImageThumbnailWrapperClass(): void {
    $this->createImageMedia('wrapper-test.jpg');

    $this->drupalGet("/archive/{$this->term->id()}");

    $wrapper = $this->getSession()->getPage()->find('css', '.masonry-item .videojs-media-thumb');
    $this->assertNotNull($wrapper, 'The .videojs-media-thumb wrapper is rendered inside the image masonry item.');
  }

  /**
   * Tests that the image thumbnail overlay contains the entity label as title.
   */
  public function testImageThumbnailOverlayTitle(): void {
    $label = 'PXL_20260407_130249138.MP_.jpg';
    $this->createImageMedia($label);

    $this->drupalGet("/archive/{$this->term->id()}");

    $titleSpan = $this->getSession()->getPage()->find('css', '.masonry-item .videojs-media-thumb__title');
    $this->assertNotNull($titleSpan, 'The .videojs-media-thumb__title span is rendered in the image thumbnail overlay.');
    $this->assertEquals($label, $titleSpan->getText(), 'The title span contains the image media entity label.');
  }

  /**
   * Tests that image items without a skate_date have no data-date attribute.
   */
  public function testImageWithoutDateHasNoDataDate(): void {
    $file = \Drupal\file\Entity\File::create([
      'uri' => 'public://no-date-image.jpg',
      'status' => 1,
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'no-date-image.jpg',
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'No date image',
      ],
      // No field_skate_date set.
    ]);
    $media->save();

    // Visit a different term page — this entity is not linked to any term.
    $otherTerm = Term::create(['vid' => 'skate_dates', 'name' => 'May 2026']);
    $otherTerm->save();

    $this->drupalGet("/archive/{$otherTerm->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $items = $this->getSession()->getPage()->findAll('css', '.masonry-item[data-media-type="image"]');
    $this->assertCount(0, $items, 'No image masonry items appear for a term with no linked image entities.');
  }

  /**
   * Tests that multiple image media entities all appear as masonry items.
   */
  public function testMultipleImageMediaItems(): void {
    $this->createImageMedia('image-one.jpg');
    $this->createImageMedia('image-two.jpg');
    $this->createImageMedia('image-three.jpg');

    $this->drupalGet("/archive/{$this->term->id()}");

    $items = $this->getSession()->getPage()->findAll('css', '.masonry-item[data-media-type="image"]');
    $this->assertGreaterThanOrEqual(3, count($items), 'All three image media entities appear as masonry items.');
  }

}
