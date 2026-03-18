<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests the VideoJsMedia list page.
 *
 * @group videojs_media
 */
class VideoJsMediaListTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer videojs media',
      'access content',
    ]);
  }

  /**
   * Tests the VideoJsMedia collection page.
   */
  public function testListPage(): void {
    $this->drupalLogin($this->adminUser);

    // Visit the collection page.
    $this->drupalGet('/admin/content/videojs-media');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('VideoJS Media');
  }

  /**
   * Tests list page displays entities.
   */
  public function testListPageDisplaysEntities(): void {
    // Create test entities.
    $entities = [];
    foreach (['local_video', 'youtube', 'remote_audio'] as $bundle) {
      $entity = VideoJsMedia::create([
        'type' => $bundle,
        'name' => "Test {$bundle} Media",
        'status' => TRUE,
      ]);
      $entity->save();
      $entities[] = $entity;
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/content/videojs-media');

    // Check that entities are listed.
    foreach ($entities as $entity) {
      $this->assertSession()->pageTextContains($entity->getName());
    }
  }

  /**
   * Tests list page filtering by bundle.
   */
  public function testListPageFilterByBundle(): void {
    // Create entities of different types.
    VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Local Video Item',
      'status' => TRUE,
    ])->save();

    VideoJsMedia::create([
      'type' => 'youtube',
      'name' => 'YouTube Item',
      'status' => TRUE,
    ])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/content/videojs-media');

    // Both items should appear.
    $this->assertSession()->pageTextContains('Local Video Item');
    $this->assertSession()->pageTextContains('YouTube Item');
  }

  /**
   * Tests access denied for users without permission.
   */
  public function testListPageAccessDenied(): void {
    $regular_user = $this->drupalCreateUser();
    $this->drupalLogin($regular_user);

    // Visit the collection page.
    $this->drupalGet('/admin/content/videojs-media');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests empty list page.
   */
  public function testEmptyListPage(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/content/videojs-media');

    $this->assertSession()->statusCodeEquals(200);
    // Should show some text indicating no content.
    $this->assertSession()->pageTextNotContains('Test Media');
  }

}
