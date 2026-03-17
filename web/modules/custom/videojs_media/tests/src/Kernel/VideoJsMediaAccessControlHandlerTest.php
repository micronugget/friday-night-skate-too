<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\videojs_media\VideoJsMediaInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests VideoJsMedia access control for all bundles and operations.
 *
 * @group videojs_media
 *
 * @coversDefaultClass \Drupal\videojs_media\Access\VideoJsMediaAccessControlHandler
 */
#[RunTestsInSeparateProcesses]
class VideoJsMediaAccessControlHandlerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'file',
    'image',
    'options',
    'file_upload_secure_validator',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('videojs_media');
    $this->importVideoJsMediaBundleConfig();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Imports bundle type configs without form/view displays.
   */
  protected function importVideoJsMediaBundleConfig(): void {
    $module_path = $this->container->get('extension.list.module')
      ->getPath('videojs_media');
    $source = new FileStorage($module_path . '/config/install');
    $config_factory = $this->container->get('config.factory');

    foreach (['local_video', 'local_audio', 'remote_video', 'remote_audio', 'youtube'] as $bundle) {
      $data = $source->read("videojs_media.type.$bundle");
      $config_factory->getEditable("videojs_media.type.$bundle")
        ->setData($data)
        ->save();
    }
  }

  /**
   * Creates and saves a videojs_media entity owned by the given uid.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param int $uid
   *   The owner user ID.
   * @param bool $published
   *   Whether the entity should be published (default TRUE).
   *
   * @return \Drupal\videojs_media\VideoJsMediaInterface
   *   The saved entity.
   */
  protected function createVideoJsMedia(string $bundle, int|string $uid, bool $published = TRUE): VideoJsMediaInterface {
    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */
    $entity = $this->entityTypeManager
      ->getStorage('videojs_media')
      ->create([
        'type' => $bundle,
        'name' => "Test $bundle",
        'uid' => $uid,
        'status' => (int) $published,
      ]);
    $entity->save();
    return $entity;
  }

  /**
   * Tests that 'administer videojs media' grants full access on all operations.
   */
  public function testAdminPermissionGrantsFullAccess(): void {
    $admin = $this->createUser(['administer videojs media']);
    $entity = $this->createVideoJsMedia('local_video', $admin->id(), FALSE);

    $this->assertTrue($entity->access('view', $admin), 'Admin can view an unpublished entity.');
    $this->assertTrue($entity->access('update', $admin), 'Admin can update an entity.');
    $this->assertTrue($entity->access('delete', $admin), 'Admin can delete an entity.');
  }

  /**
   * Tests view access for published entities on each bundle.
   *
   * @dataProvider bundleProvider
   */
  public function testViewPublished(string $bundle): void {
    $owner = $this->createUser();
    $entity = $this->createVideoJsMedia($bundle, $owner->id(), TRUE);

    $viewer = $this->createUser(["view $bundle videojs media"]);
    $this->assertTrue(
      $entity->access('view', $viewer),
      "User with 'view $bundle videojs media' can view a published entity."
    );

    $no_access = $this->createUser();
    $this->assertFalse(
      $entity->access('view', $no_access),
      'User without view permission cannot view a published entity.'
    );
  }

  /**
   * Tests view access for unpublished entities on each bundle.
   *
   * @dataProvider bundleProvider
   */
  public function testViewUnpublished(string $bundle): void {
    $owner = $this->createUser();
    $entity = $this->createVideoJsMedia($bundle, $owner->id(), FALSE);

    $privileged = $this->createUser(["view unpublished $bundle videojs media"]);
    $this->assertTrue(
      $entity->access('view', $privileged),
      "User with 'view unpublished $bundle videojs media' can view an unpublished entity."
    );

    $published_only = $this->createUser(["view $bundle videojs media"]);
    $this->assertFalse(
      $entity->access('view', $published_only),
      'User with only the published-view permission cannot view an unpublished entity.'
    );
  }

  /**
   * Tests 'edit any' permission grants update access regardless of ownership.
   *
   * @dataProvider bundleProvider
   */
  public function testEditAny(string $bundle): void {
    $owner = $this->createUser();
    $entity = $this->createVideoJsMedia($bundle, $owner->id());

    $editor = $this->createUser(["edit any $bundle videojs media"]);
    $this->assertTrue($entity->access('update', $editor));
  }

  /**
   * Tests 'edit own' permission grants update access only on owned entities.
   *
   * @dataProvider bundleProvider
   */
  public function testEditOwn(string $bundle): void {
    $owner = $this->createUser(["edit own $bundle videojs media"]);
    $entity = $this->createVideoJsMedia($bundle, $owner->id());

    $this->assertTrue($entity->access('update', $owner), 'Owner can edit their own entity.');

    $other = $this->createUser(["edit own $bundle videojs media"]);
    $this->assertFalse($entity->access('update', $other), "Non-owner cannot edit someone else's entity.");

    $no_perm = $this->createUser();
    $this->assertFalse($entity->access('update', $no_perm), 'User without edit permission cannot update.');
  }

  /**
   * Tests 'delete any' permission grants delete access regardless of ownership.
   *
   * @dataProvider bundleProvider
   */
  public function testDeleteAny(string $bundle): void {
    $owner = $this->createUser();
    $entity = $this->createVideoJsMedia($bundle, $owner->id());

    $deleter = $this->createUser(["delete any $bundle videojs media"]);
    $this->assertTrue($entity->access('delete', $deleter));
  }

  /**
   * Tests 'delete own' permission grants delete access only on owned entities.
   *
   * @dataProvider bundleProvider
   */
  public function testDeleteOwn(string $bundle): void {
    $owner = $this->createUser(["delete own $bundle videojs media"]);
    $entity = $this->createVideoJsMedia($bundle, $owner->id());

    $this->assertTrue($entity->access('delete', $owner), 'Owner can delete their own entity.');

    $other = $this->createUser(["delete own $bundle videojs media"]);
    $this->assertFalse($entity->access('delete', $other), "Non-owner cannot delete someone else's entity.");
  }

  /**
   * Tests create access per bundle via the access control handler.
   *
   * @dataProvider bundleProvider
   */
  public function testCreateAccess(string $bundle): void {
    $access_handler = $this->entityTypeManager
      ->getAccessControlHandler('videojs_media');

    $creator = $this->createUser(["create $bundle videojs media"]);
    $this->assertTrue($access_handler->createAccess($bundle, $creator));

    $no_create = $this->createUser();
    $this->assertFalse($access_handler->createAccess($bundle, $no_create));
  }

  /**
   * Tests that 'administer videojs media' grants create access on all bundles.
   */
  public function testAdminGrantsCreateAccessOnAllBundles(): void {
    $access_handler = $this->entityTypeManager
      ->getAccessControlHandler('videojs_media');

    $admin = $this->createUser(['administer videojs media']);

    foreach (['local_video', 'local_audio', 'remote_video', 'remote_audio', 'youtube'] as $bundle) {
      $this->assertTrue(
        $access_handler->createAccess($bundle, $admin),
        "Admin can create '$bundle' entities."
      );
    }
  }

  /**
   * Tests that bundle permissions do not cross bundle boundaries.
   */
  public function testPermissionsAreBundleScoped(): void {
    $owner = $this->createUser();
    $local_video = $this->createVideoJsMedia('local_video', $owner->id());
    $youtube = $this->createVideoJsMedia('youtube', $owner->id());

    $viewer = $this->createUser(['view local_video videojs media']);

    $this->assertTrue($local_video->access('view', $viewer));
    $this->assertFalse(
      $youtube->access('view', $viewer),
      "'view local_video' permission must not grant access to youtube entities."
    );
  }

  /**
   * Tests that an unsupported operation returns neutral access.
   *
   * @covers \Drupal\videojs_media\Access\VideoJsMediaAccessControlHandler::checkAccess
   */
  public function testUnsupportedOperationIsNeutral(): void {
    $account = $this->createUser();
    $entity = $this->createVideoJsMedia('local_video', $account->id());

    $result = $entity->access('unsupported_operation', $account, TRUE);
    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests the access result cacheability varies on permissions.
   */
  public function testAccessResultCachesPerPermissions(): void {
    $owner = $this->createUser();
    $entity = $this->createVideoJsMedia('remote_video', $owner->id(), TRUE);

    $viewer = $this->createUser(['view remote_video videojs media']);
    $result = $entity->access('view', $viewer, TRUE);

    $this->assertTrue($result->isAllowed());
    $this->assertContains('permissions', $result->getCacheContexts());
  }

  /**
   * Data provider returning all five bundle machine names.
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

}
