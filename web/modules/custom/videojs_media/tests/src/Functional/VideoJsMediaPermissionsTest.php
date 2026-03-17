<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\VideoJsMediaInterface;

/**
 * Tests per-bundle permission enforcement in the VideoJS Media module.
 *
 * Covers the permissions defined in videojs_media.permissions.yml and their
 * enforcement by VideoJsMediaAccessControlHandler at the HTTP level.
 *
 * @group videojs_media
 */
class VideoJsMediaPermissionsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'sdc',
    'file',
    'image',
    'text',
    'options',
    'file_upload_secure_validator',
  ];

  /**
   * Tests that anonymous users receive a 403 on the canonical entity page.
   */
  public function testAnonymousUserCannotViewPublishedEntity(): void {
    $entity = $this->createPublishedEntity('remote_video');
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests bundle view permission allows viewing a published entity.
   *
   * @dataProvider bundleProvider
   */
  public function testBundleViewPermission(string $bundle): void {
    $entity = $this->createPublishedEntity($bundle);

    $viewer = $this->drupalCreateUser(["view $bundle videojs media"]);
    $this->drupalLogin($viewer);

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests a bundle view permission does not grant access to another bundle.
   */
  public function testViewPermissionDoesNotCrossBundles(): void {
    $local_video = $this->createPublishedEntity('local_video');
    $youtube = $this->createPublishedEntity('youtube');

    $viewer = $this->drupalCreateUser(['view local_video videojs media']);
    $this->drupalLogin($viewer);

    $this->drupalGet("/videojs-media/{$local_video->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet("/videojs-media/{$youtube->id()}");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that unpublished entities require the view-unpublished permission.
   */
  public function testViewUnpublishedPermission(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Unpublished Entity',
        'field_remote_url' => 'https://example.com/video.mp4',
        'status' => 0,
      ]);
    $entity->save();

    // User with only the published-view permission cannot access it.
    $view_published = $this->drupalCreateUser(['view remote_video videojs media']);
    $this->drupalLogin($view_published);
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(403);

    // User with view-unpublished permission can access it.
    $view_unpublished = $this->drupalCreateUser(['view unpublished remote_video videojs media']);
    $this->drupalLogin($view_unpublished);
    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the add form requires the 'create {bundle}' permission.
   */
  public function testCreatePermissionRequiredForAddForm(): void {
    $no_perms = $this->drupalCreateUser();
    $this->drupalLogin($no_perms);

    $this->drupalGet('/videojs-media/add/remote_video');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that 'create {bundle}' permission grants access to the add form.
   *
   * @dataProvider createBundleProvider
   */
  public function testCreatePermissionGrantsAddFormAccess(string $bundle): void {
    $creator = $this->drupalCreateUser(["create $bundle videojs media"]);
    $this->drupalLogin($creator);

    $this->drupalGet("/videojs-media/add/$bundle");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests 'edit any' grants edit form access for entities not owned by user.
   */
  public function testEditAnyPermission(): void {
    $owner = $this->drupalCreateUser();
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Owned by another user',
        'field_remote_url' => 'https://example.com/video.mp4',
        'uid' => $owner->id(),
        'status' => 1,
      ]);
    $entity->save();

    $editor = $this->drupalCreateUser(['edit any remote_video videojs media']);
    $this->drupalLogin($editor);

    $this->drupalGet("/videojs-media/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that 'edit own' only grants edit access to the owner.
   */
  public function testEditOwnPermission(): void {
    $owner = $this->drupalCreateUser(['edit own remote_video videojs media', 'view remote_video videojs media']);
    $other = $this->drupalCreateUser(['edit own remote_video videojs media', 'view remote_video videojs media']);

    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Owner only edit',
        'field_remote_url' => 'https://example.com/video.mp4',
        'uid' => $owner->id(),
        'status' => 1,
      ]);
    $entity->save();

    // Owner can access edit form.
    $this->drupalLogin($owner);
    $this->drupalGet("/videojs-media/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);

    // Non-owner cannot access edit form.
    $this->drupalLogin($other);
    $this->drupalGet("/videojs-media/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that 'administer videojs media' bypasses all fine-grained checks.
   */
  public function testAdminPermissionGrantsFullAccess(): void {
    $admin = $this->drupalCreateUser(['administer videojs media']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/videojs-media');
    $this->assertSession()->statusCodeEquals(200);

    foreach (['remote_video', 'youtube'] as $bundle) {
      $this->drupalGet("/videojs-media/add/$bundle");
      $this->assertSession()->statusCodeEquals(200);
    }
  }

  /**
   * Tests that the permissions page lists all expected per-bundle permissions.
   */
  public function testPermissionsPageListsBundlePermissions(): void {
    $admin = $this->drupalCreateUser([
      'administer permissions',
      'administer videojs media',
    ]);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/people/permissions');
    $this->assertSession()->statusCodeEquals(200);

    // Verify that a representative sample of bundle permissions are listed.
    $this->assertSession()->pageTextContains('View published Local Video VideoJS media');
    $this->assertSession()->pageTextContains('View published YouTube VideoJS media');
    $this->assertSession()->pageTextContains('Edit any Remote Video VideoJS media');
    $this->assertSession()->pageTextContains('Delete own Local Audio VideoJS media');
  }

  /**
   * Creates and saves a published entity for the given bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return \Drupal\videojs_media\VideoJsMediaInterface
   *   The saved, published entity.
   */
  protected function createPublishedEntity(string $bundle): VideoJsMediaInterface {
    $values = [
      'type' => $bundle,
      'name' => "Published $bundle",
      'status' => 1,
    ];

    if (in_array($bundle, ['remote_video', 'remote_audio'], TRUE)) {
      $values['field_remote_url'] = 'https://example.com/media.mp4';
    }
    elseif ($bundle === 'youtube') {
      $values['field_youtube_url'] = 'https://www.youtube.com/watch?v=test';
    }

    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create($values);
    $entity->save();

    return $entity;
  }

  /**
   * Data provider for view-permission tests over all bundles.
   *
   * @return array
   *   Data sets keyed by bundle name.
   */
  public static function bundleProvider(): array {
    return [
      'local_video' => ['local_video'],
      'local_audio' => ['local_audio'],
      'remote_video' => ['remote_video'],
      'remote_audio' => ['remote_audio'],
      'youtube' => ['youtube'],
    ];
  }

  /**
   * Data provider for create-permission tests (non-file-upload bundles).
   *
   * @return array
   *   Data sets keyed by bundle name (non-file-upload bundles).
   */
  public static function createBundleProvider(): array {
    return [
      'remote_video' => ['remote_video'],
      'remote_audio' => ['remote_audio'],
      'youtube' => ['youtube'],
    ];
  }

}
