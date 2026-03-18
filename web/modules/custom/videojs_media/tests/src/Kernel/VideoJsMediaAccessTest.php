<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests access control for VideoJsMedia entities.
 *
 * @group videojs_media
 */
class VideoJsMediaAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'videojs_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('videojs_media');
    $this->installConfig(['user', 'videojs_media']);
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Tests admin access.
   */
  public function testAdminAccess(): void {
    // Create admin user.
    $admin = User::create([
      'name' => 'admin',
      'mail' => 'admin@example.com',
    ]);
    $admin->save();

    // Grant admin permission.
    $role = Role::create([
      'id' => 'admin_role',
      'label' => 'Admin Role',
    ]);
    $role->grantPermission('administer videojs media');
    $role->save();
    $admin->addRole('admin_role');
    $admin->save();

    // Create entity.
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Test Video',
      'status' => TRUE,
    ]);
    $entity->save();

    // Test all operations.
    $this->assertTrue($entity->access('view', $admin));
    $this->assertTrue($entity->access('update', $admin));
    $this->assertTrue($entity->access('delete', $admin));
  }

  /**
   * Tests view access for published entities.
   *
   * @dataProvider bundleProvider
   */
  public function testViewPublishedAccess(string $bundle): void {
    // Create user with view permission.
    $user = User::create([
      'name' => 'viewer',
      'mail' => 'viewer@example.com',
    ]);
    $user->save();

    $role = Role::create([
      'id' => 'viewer_role',
      'label' => 'Viewer Role',
    ]);
    $role->grantPermission("view {$bundle} videojs media");
    $role->save();
    $user->addRole('viewer_role');
    $user->save();

    // Create published entity.
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => 'Test Published Media',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->assertTrue($entity->access('view', $user));
  }

  /**
   * Tests view access for unpublished entities.
   *
   * @dataProvider bundleProvider
   */
  public function testViewUnpublishedAccess(string $bundle): void {
    // Create users.
    $user_with_permission = User::create([
      'name' => 'unpub_viewer',
      'mail' => 'unpub@example.com',
    ]);
    $user_with_permission->save();

    $role = Role::create([
      'id' => 'unpub_viewer_role',
      'label' => 'Unpublished Viewer Role',
    ]);
    $role->grantPermission("view unpublished {$bundle} videojs media");
    $role->save();
    $user_with_permission->addRole('unpub_viewer_role');
    $user_with_permission->save();

    $user_without_permission = User::create([
      'name' => 'regular_user',
      'mail' => 'regular@example.com',
    ]);
    $user_without_permission->save();

    // Create unpublished entity.
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => 'Test Unpublished Media',
      'status' => FALSE,
    ]);
    $entity->save();

    $this->assertTrue($entity->access('view', $user_with_permission));
    $this->assertFalse($entity->access('view', $user_without_permission));
  }

  /**
   * Tests edit own access.
   *
   * @dataProvider bundleProvider
   */
  public function testEditOwnAccess(string $bundle): void {
    // Create owner user.
    $owner = User::create([
      'name' => 'owner',
      'mail' => 'owner@example.com',
    ]);
    $owner->save();

    $role = Role::create([
      'id' => 'owner_role',
      'label' => 'Owner Role',
    ]);
    $role->grantPermission("edit own {$bundle} videojs media");
    $role->save();
    $owner->addRole('owner_role');
    $owner->save();

    // Create other user.
    $other_user = User::create([
      'name' => 'other',
      'mail' => 'other@example.com',
    ]);
    $other_user->save();
    $other_user->addRole('owner_role');
    $other_user->save();

    // Create entity owned by owner.
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => 'Test Media',
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $entity->save();

    // Owner can edit, other user cannot.
    $this->assertTrue($entity->access('update', $owner));
    $this->assertFalse($entity->access('update', $other_user));
  }

  /**
   * Tests edit any access.
   *
   * @dataProvider bundleProvider
   */
  public function testEditAnyAccess(string $bundle): void {
    // Create editor user.
    $editor = User::create([
      'name' => 'editor',
      'mail' => 'editor@example.com',
    ]);
    $editor->save();

    $role = Role::create([
      'id' => 'editor_role',
      'label' => 'Editor Role',
    ]);
    $role->grantPermission("edit any {$bundle} videojs media");
    $role->save();
    $editor->addRole('editor_role');
    $editor->save();

    // Create owner user.
    $owner = User::create([
      'name' => 'content_owner',
      'mail' => 'content_owner@example.com',
    ]);
    $owner->save();

    // Create entity owned by different user.
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => 'Test Media',
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $entity->save();

    // Editor can edit any entity.
    $this->assertTrue($entity->access('update', $editor));
  }

  /**
   * Tests delete own access.
   *
   * @dataProvider bundleProvider
   */
  public function testDeleteOwnAccess(string $bundle): void {
    // Create owner user.
    $owner = User::create([
      'name' => 'owner',
      'mail' => 'owner@example.com',
    ]);
    $owner->save();

    $role = Role::create([
      'id' => 'deleter_role',
      'label' => 'Deleter Role',
    ]);
    $role->grantPermission("delete own {$bundle} videojs media");
    $role->save();
    $owner->addRole('deleter_role');
    $owner->save();

    // Create other user.
    $other_user = User::create([
      'name' => 'other_user',
      'mail' => 'other_user@example.com',
    ]);
    $other_user->save();
    $other_user->addRole('deleter_role');
    $other_user->save();

    // Create entity owned by owner.
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => 'Test Media',
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $entity->save();

    // Owner can delete, other user cannot.
    $this->assertTrue($entity->access('delete', $owner));
    $this->assertFalse($entity->access('delete', $other_user));
  }

  /**
   * Tests delete any access.
   *
   * @dataProvider bundleProvider
   */
  public function testDeleteAnyAccess(string $bundle): void {
    // Create deleter user.
    $deleter = User::create([
      'name' => 'deleter',
      'mail' => 'deleter@example.com',
    ]);
    $deleter->save();

    $role = Role::create([
      'id' => 'any_deleter_role',
      'label' => 'Any Deleter Role',
    ]);
    $role->grantPermission("delete any {$bundle} videojs media");
    $role->save();
    $deleter->addRole('any_deleter_role');
    $deleter->save();

    // Create owner user.
    $owner = User::create([
      'name' => 'entity_owner',
      'mail' => 'entity_owner@example.com',
    ]);
    $owner->save();

    // Create entity owned by different user.
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => 'Test Media',
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $entity->save();

    // Deleter can delete any entity.
    $this->assertTrue($entity->access('delete', $deleter));
  }

  /**
   * Tests create access.
   *
   * @dataProvider bundleProvider
   */
  public function testCreateAccess(string $bundle): void {
    // Create user with create permission.
    $creator = User::create([
      'name' => 'creator',
      'mail' => 'creator@example.com',
    ]);
    $creator->save();

    $role = Role::create([
      'id' => 'creator_role',
      'label' => 'Creator Role',
    ]);
    $role->grantPermission("create {$bundle} videojs media");
    $role->save();
    $creator->addRole('creator_role');
    $creator->save();

    // Create user without create permission.
    $non_creator = User::create([
      'name' => 'non_creator',
      'mail' => 'non_creator@example.com',
    ]);
    $non_creator->save();

    // Test create access.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $access_handler = $entity_type_manager->getAccessControlHandler('videojs_media');

    $this->assertTrue($access_handler->createAccess($bundle, $creator));
    $this->assertFalse($access_handler->createAccess($bundle, $non_creator));
  }

  /**
   * Data provider for bundle types.
   */
  public static function bundleProvider(): array {
    return [
      'local_video' => ['local_video'],
      'youtube' => ['youtube'],
    ];
  }

}
