<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\videojs_media\VideoJsMediaInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the VideoJsMedia content entity: creation, loading, updating, deletion.
 *
 * @group videojs_media
 *
 * @coversDefaultClass \Drupal\videojs_media\Entity\VideoJsMedia
 */
#[RunTestsInSeparateProcesses]
class VideoJsMediaEntityTest extends EntityKernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('videojs_media');
    $this->importVideoJsMediaBundleConfig();
  }

  /**
   * Imports only the bundle type configs, skipping form/view displays.
   *
   * This avoids pulling in display-config dependencies (e.g. image styles) in
   * Kernel tests that do not render entities.
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
   * Tests entity creation, save, and load for all five bundles.
   *
   * @dataProvider bundleProvider
   */
  public function testCreateLoadBundle(string $bundle): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */
    $entity = $storage->create([
      'type' => $bundle,
      'name' => "Test $bundle item",
      'status' => 1,
    ]);
    $entity->save();
    $id = $entity->id();

    $this->assertNotNull($id, "Entity ID is set after save for bundle '$bundle'.");

    $storage->resetCache([$id]);
    $loaded = $storage->load($id);

    $this->assertNotNull($loaded, "Entity can be loaded after save for bundle '$bundle'.");
    $this->assertEquals("Test $bundle item", $loaded->getName());
    $this->assertEquals($bundle, $loaded->bundle());
    $this->assertTrue($loaded->isPublished());
  }

  /**
   * Tests entity deletion removes the entity from storage.
   */
  public function testDelete(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity = $storage->create([
      'type' => 'remote_video',
      'name' => 'To be deleted',
      'status' => 1,
    ]);
    $entity->save();
    $id = $entity->id();

    $entity->delete();

    $storage->resetCache([$id]);
    $this->assertNull($storage->load($id), 'Deleted entity is no longer loadable.');
  }

  /**
   * Tests the getName() and setName() methods.
   *
   * @covers ::getName
   * @covers ::setName
   */
  public function testNameMethods(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */
    $entity = $storage->create([
      'type' => 'local_video',
      'name' => 'Initial name',
    ]);

    $this->assertEquals('Initial name', $entity->getName());

    $entity->setName('Renamed');
    $this->assertEquals('Renamed', $entity->getName());

    $entity->save();
    $storage->resetCache([$entity->id()]);
    $this->assertEquals('Renamed', $storage->load($entity->id())->getName());
  }

  /**
   * Tests that label() returns the entity name.
   */
  public function testLabel(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity = $storage->create(['type' => 'youtube', 'name' => 'My YouTube Video']);
    $this->assertEquals('My YouTube Video', $entity->label());
  }

  /**
   * Tests published / unpublished status methods.
   *
   * @covers ::isPublished
   * @covers ::setPublished
   */
  public function testPublishedStatus(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity = $storage->create(['type' => 'local_audio', 'name' => 'Status test', 'status' => 1]);
    $this->assertTrue($entity->isPublished());

    $entity->setPublished(FALSE);
    $this->assertFalse($entity->isPublished());

    $entity->setPublished(TRUE);
    $this->assertTrue($entity->isPublished());

    $entity->save();
    $storage->resetCache([$entity->id()]);
    $this->assertTrue($storage->load($entity->id())->isPublished());
  }

  /**
   * Tests that an unpublished entity persists correctly through save/load.
   */
  public function testUnpublishedEntityPersists(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity = $storage->create(['type' => 'remote_audio', 'name' => 'Unpublished', 'status' => 0]);
    $entity->save();

    $storage->resetCache([$entity->id()]);
    $this->assertFalse($storage->load($entity->id())->isPublished());
  }

  /**
   * Tests the getCreatedTime() and setCreatedTime() methods.
   *
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   */
  public function testCreatedTimeMethods(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $timestamp = 1700000000;
    $entity = $storage->create([
      'type' => 'local_video',
      'name' => 'Timestamped',
      'created' => $timestamp,
    ]);
    $entity->save();

    $this->assertEquals($timestamp, $entity->getCreatedTime());

    $new_timestamp = 1800000000;
    $entity->setCreatedTime($new_timestamp);
    $this->assertEquals($new_timestamp, $entity->getCreatedTime());
  }

  /**
   * Tests retrieving and setting the entity owner.
   */
  public function testOwnership(): void {
    $owner = $this->createUser();
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */
    $entity = $storage->create([
      'type' => 'remote_video',
      'name' => 'Owned entity',
      'uid' => $owner->id(),
    ]);
    $entity->save();

    $this->assertEquals($owner->id(), $entity->getOwnerId());
    $this->assertEquals($owner->id(), $entity->getOwner()->id());
  }

  /**
   * Tests that the name field is required; an empty name triggers a violation.
   */
  public function testNameFieldIsRequired(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity = $storage->create(['type' => 'local_video', 'name' => '']);
    $violations = $entity->validate();

    $this->assertGreaterThan(0, $violations->count(), 'Blank name should produce a constraint violation.');

    $violation_paths = [];
    foreach ($violations as $violation) {
      $violation_paths[] = $violation->getPropertyPath();
    }
    $this->assertContains('name', $violation_paths, 'Violation is on the name field.');
  }

  /**
   * Tests that the entity satisfies the VideoJsMediaInterface contract.
   */
  public function testImplementsInterface(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity = $storage->create(['type' => 'local_video', 'name' => 'Interface check']);

    $this->assertInstanceOf(VideoJsMediaInterface::class, $entity);
    $this->assertInstanceOf(ContentEntityInterface::class, $entity);
    $this->assertInstanceOf(EntityChangedInterface::class, $entity);
    $this->assertInstanceOf(EntityOwnerInterface::class, $entity);
  }

  /**
   * Tests that multiple saved entities receive unique IDs.
   */
  public function testEntitiesHaveUniqueIds(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity1 = $storage->create(['type' => 'local_video', 'name' => 'First']);
    $entity1->save();

    $entity2 = $storage->create(['type' => 'youtube', 'name' => 'Second']);
    $entity2->save();

    $this->assertNotEquals($entity1->id(), $entity2->id());
  }

  /**
   * Tests that getChangedTime() is populated on save.
   */
  public function testChangedTimeIsSet(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media');

    $entity = $storage->create(['type' => 'remote_video', 'name' => 'Changed test']);
    $entity->save();

    $this->assertGreaterThan(0, $entity->getChangedTime());
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
