<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Unit\Entity;

use Drupal\Tests\UnitTestCase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests the VideoJsMedia entity methods.
 *
 * @coversDefaultClass \Drupal\videojs_media\Entity\VideoJsMedia
 * @group videojs_media
 */
class VideoJsMediaTest extends UnitTestCase {

  /**
   * Tests getName() and setName().
   *
   * @covers ::getName
   * @covers ::setName
   */
  public function testGetSetName(): void {
    $entity = $this->getMockBuilder(VideoJsMedia::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get', 'set'])
      ->getMock();

    // Test getName().
    $field_item_list = $this->createMock('\Drupal\Core\Field\FieldItemListInterface');
    $field_item_list->expects($this->once())
      ->method('__get')
      ->with('value')
      ->willReturn('Test Media Name');

    $entity->expects($this->once())
      ->method('get')
      ->with('name')
      ->willReturn($field_item_list);

    $this->assertEquals('Test Media Name', $entity->getName());

    // Test setName().
    $entity->expects($this->once())
      ->method('set')
      ->with('name', 'New Media Name')
      ->willReturn($entity);

    $result = $entity->setName('New Media Name');
    $this->assertSame($entity, $result);
  }

  /**
   * Tests getCreatedTime() and setCreatedTime().
   *
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   */
  public function testGetSetCreatedTime(): void {
    $entity = $this->getMockBuilder(VideoJsMedia::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get', 'set'])
      ->getMock();

    $timestamp = 1234567890;

    // Test getCreatedTime().
    $field_item_list = $this->createMock('\Drupal\Core\Field\FieldItemListInterface');
    $field_item_list->expects($this->once())
      ->method('__get')
      ->with('value')
      ->willReturn($timestamp);

    $entity->expects($this->once())
      ->method('get')
      ->with('created')
      ->willReturn($field_item_list);

    $this->assertEquals($timestamp, $entity->getCreatedTime());

    // Test setCreatedTime().
    $entity->expects($this->once())
      ->method('set')
      ->with('created', $timestamp)
      ->willReturn($entity);

    $result = $entity->setCreatedTime($timestamp);
    $this->assertSame($entity, $result);
  }

  /**
   * Tests isPublished() and setPublished().
   *
   * @covers ::isPublished
   * @covers ::setPublished
   * @dataProvider publishedStatusProvider
   */
  public function testPublishedStatus(bool $status): void {
    $entity = $this->getMockBuilder(VideoJsMedia::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get', 'set'])
      ->getMock();

    // Test isPublished().
    $field_item_list = $this->createMock('\Drupal\Core\Field\FieldItemListInterface');
    $field_item_list->expects($this->once())
      ->method('__get')
      ->with('value')
      ->willReturn($status ? 1 : 0);

    $entity->expects($this->once())
      ->method('get')
      ->with('status')
      ->willReturn($field_item_list);

    $this->assertEquals($status, $entity->isPublished());

    // Test setPublished().
    $entity->expects($this->once())
      ->method('set')
      ->with('status', $status)
      ->willReturn($entity);

    $result = $entity->setPublished($status);
    $this->assertSame($entity, $result);
  }

  /**
   * Data provider for published status tests.
   */
  public static function publishedStatusProvider(): array {
    return [
      'published' => [TRUE],
      'unpublished' => [FALSE],
    ];
  }

}
