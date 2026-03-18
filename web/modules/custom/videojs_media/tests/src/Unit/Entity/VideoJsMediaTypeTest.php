<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Unit\Entity;

use Drupal\Tests\UnitTestCase;
use Drupal\videojs_media\Entity\VideoJsMediaType;

/**
 * Tests the VideoJsMediaType entity methods.
 *
 * @coversDefaultClass \Drupal\videojs_media\Entity\VideoJsMediaType
 * @group videojs_media
 */
class VideoJsMediaTypeTest extends UnitTestCase {

  /**
   * Tests getDescription() and setDescription().
   *
   * @covers ::getDescription
   * @covers ::setDescription
   */
  public function testGetSetDescription(): void {
    $entity = $this->getMockBuilder(VideoJsMediaType::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();

    // Use reflection to set the protected description property.
    $reflection = new \ReflectionClass($entity);
    $property = $reflection->getProperty('description');
    $property->setAccessible(TRUE);
    $property->setValue($entity, 'Test description');

    $this->assertEquals('Test description', $entity->getDescription());

    // Test setDescription().
    $result = $entity->setDescription('New description');
    $this->assertSame($entity, $result);
    $this->assertEquals('New description', $entity->getDescription());
  }

}
