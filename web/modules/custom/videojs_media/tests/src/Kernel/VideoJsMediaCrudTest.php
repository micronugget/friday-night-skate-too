<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests CRUD operations for VideoJsMedia entities.
 *
 * @group videojs_media
 */
class VideoJsMediaCrudTest extends KernelTestBase {

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
    $this->installConfig(['videojs_media']);
  }

  /**
   * Tests creating VideoJsMedia entities for all bundles.
   *
   * @dataProvider bundleProvider
   */
  public function testCreateEntity(string $bundle): void {
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => "Test {$bundle} Media",
      'status' => TRUE,
    ]);

    $this->assertInstanceOf(VideoJsMedia::class, $entity);
    $this->assertEquals($bundle, $entity->bundle());
    $this->assertEquals("Test {$bundle} Media", $entity->getName());
    $this->assertTrue($entity->isPublished());
    $this->assertTrue($entity->isNew());
  }

  /**
   * Tests saving VideoJsMedia entities.
   *
   * @dataProvider bundleProvider
   */
  public function testSaveEntity(string $bundle): void {
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => "Test {$bundle} Media",
      'status' => TRUE,
    ]);

    $result = $entity->save();
    $this->assertEquals(SAVED_NEW, $result);
    $this->assertFalse($entity->isNew());
    $this->assertNotEmpty($entity->id());
  }

  /**
   * Tests loading VideoJsMedia entities.
   *
   * @dataProvider bundleProvider
   */
  public function testLoadEntity(string $bundle): void {
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => "Test {$bundle} Media",
      'status' => TRUE,
    ]);
    $entity->save();
    $id = $entity->id();

    // Load the entity.
    $loaded_entity = VideoJsMedia::load($id);
    $this->assertInstanceOf(VideoJsMedia::class, $loaded_entity);
    $this->assertEquals($id, $loaded_entity->id());
    $this->assertEquals("Test {$bundle} Media", $loaded_entity->getName());
    $this->assertEquals($bundle, $loaded_entity->bundle());
  }

  /**
   * Tests updating VideoJsMedia entities.
   *
   * @dataProvider bundleProvider
   */
  public function testUpdateEntity(string $bundle): void {
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => "Test {$bundle} Media",
      'status' => TRUE,
    ]);
    $entity->save();

    // Update the entity.
    $entity->setName("Updated {$bundle} Media");
    $entity->setPublished(FALSE);
    $result = $entity->save();

    $this->assertEquals(SAVED_UPDATED, $result);
    $this->assertEquals("Updated {$bundle} Media", $entity->getName());
    $this->assertFalse($entity->isPublished());

    // Reload and verify.
    $loaded_entity = VideoJsMedia::load($entity->id());
    $this->assertEquals("Updated {$bundle} Media", $loaded_entity->getName());
    $this->assertFalse($loaded_entity->isPublished());
  }

  /**
   * Tests deleting VideoJsMedia entities.
   *
   * @dataProvider bundleProvider
   */
  public function testDeleteEntity(string $bundle): void {
    $entity = VideoJsMedia::create([
      'type' => $bundle,
      'name' => "Test {$bundle} Media",
      'status' => TRUE,
    ]);
    $entity->save();
    $id = $entity->id();

    // Delete the entity.
    $entity->delete();

    // Verify entity is deleted.
    $loaded_entity = VideoJsMedia::load($id);
    $this->assertNull($loaded_entity);
  }

  /**
   * Tests loading multiple VideoJsMedia entities.
   */
  public function testLoadMultipleEntities(): void {
    $entities = [];
    foreach (['local_video', 'youtube', 'remote_audio'] as $bundle) {
      $entity = VideoJsMedia::create([
        'type' => $bundle,
        'name' => "Test {$bundle} Media",
        'status' => TRUE,
      ]);
      $entity->save();
      $entities[$bundle] = $entity->id();
    }

    // Load multiple entities.
    $loaded_entities = VideoJsMedia::loadMultiple(array_values($entities));
    $this->assertCount(3, $loaded_entities);

    foreach ($loaded_entities as $loaded_entity) {
      $this->assertInstanceOf(VideoJsMedia::class, $loaded_entity);
    }
  }

  /**
   * Data provider for bundle types.
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
