<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the VideoJS Media block plugin.
 *
 * Covers: block placement, entity rendering via block, missing entity handling,
 * and block access control respecting the entity's publish status.
 *
 * @group videojs_media
 *
 * @coversDefaultClass \Drupal\videojs_media\Plugin\Block\VideoJsMediaBlock
 */
#[RunTestsInSeparateProcesses]
class VideoJsMediaBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'block',
    'file',
    'image',
    'text',
    'options',
    'file_upload_secure_validator',
  ];

  /**
   * A user with block and media administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer blocks',
      'administer videojs media',
      'access administration pages',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that the block plugin renders a published entity on the page.
   *
   * Verifies that the entity's outer wrapper element is present and that the
   * entity label appears in the block output (non-page view mode shows the
   * label in an <h2>).
   */
  public function testBlockRendersPublishedEntity(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Block Rendered Video',
        'field_remote_url' => 'https://example.com/video.mp4',
        'status' => 1,
      ]);
    $entity->save();

    $this->drupalPlaceBlock('videojs_media', [
      'videojs_media_id' => $entity->id(),
      'view_mode' => 'default',
      'label' => 'VideoJS Block',
      'label_display' => 'visible',
    ]);

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    // The block label is rendered by Drupal's block system.
    $this->assertSession()->pageTextContains('VideoJS Block');
    // The entity label appears in the <h2> (non-page rendering).
    $this->assertSession()->pageTextContains('Block Rendered Video');
    // The template's outer <article> element carries the videojs-media class.
    $this->assertSession()->elementExists('css', 'article.videojs-media');
  }

  /**
   * Tests that the block renders without crashing when entity ID is missing.
   *
   * The block must return an empty render array and not crash the page.
   */
  public function testBlockWithMissingEntityDoesNotCrashPage(): void {
    $this->drupalPlaceBlock('videojs_media', [
      'videojs_media_id' => 999999,
      'view_mode' => 'default',
      'label' => 'Missing entity block',
    ]);

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the block configuration form has the required form fields.
   *
   * @covers \Drupal\videojs_media\Plugin\Block\VideoJsMediaBlock::blockForm
   */
  public function testBlockConfigFormHasEntityAutocomplete(): void {
    $this->drupalGet('/admin/structure/block/add/videojs_media/' . $this->defaultTheme);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('settings[videojs_media_id]');
    $this->assertSession()->fieldExists('settings[view_mode]');
    $this->assertSession()->fieldExists('settings[hide_title]');
  }

  /**
   * Tests that the block respects entity access control.
   *
   * An unpublished entity must not be rendered for a user who lacks the
   * 'view unpublished' permission.
   */
  public function testBlockDoesNotRenderInaccessibleEntity(): void {
    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Inaccessible Unpublished Video',
        'field_remote_url' => 'https://example.com/video.mp4',
        'status' => 0,
      ]);
    $entity->save();

    $this->drupalPlaceBlock('videojs_media', [
      'videojs_media_id' => $entity->id(),
      'view_mode' => 'default',
    ]);

    // Log in as a restricted user who can view published remote_video entities
    // but not unpublished ones.
    $restricted = $this->drupalCreateUser(['view remote_video videojs media']);
    $this->drupalLogin($restricted);

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    // The entity must not be rendered for this user.
    $this->assertSession()->pageTextNotContains('Inaccessible Unpublished Video');
  }

  /**
   * Tests that the block cache tag for the referenced entity is set.
   *
   * @covers \Drupal\videojs_media\Plugin\Block\VideoJsMediaBlock::getCacheTags
   */
  public function testBlockCacheTagIncludesEntityTag(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Cache Tag Test',
        'field_remote_url' => 'https://example.com/video.mp4',
        'status' => 1,
      ]);
    $entity->save();

    // Instantiate the block plugin directly to inspect its cache tags.
    /** @var \Drupal\videojs_media\Plugin\Block\VideoJsMediaBlock $block */
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('videojs_media', [
        'videojs_media_id' => $entity->id(),
        'view_mode' => 'default',
        'hide_title' => FALSE,
      ]);

    $cache_tags = $block->getCacheTags();
    $this->assertContains('videojs_media:' . $entity->id(), $cache_tags);
  }

}
