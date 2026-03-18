<?php

declare(strict_types=1);

namespace Drupal\Tests\skating_video_uploader\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\skating_video_uploader\Service\MetadataExtractor;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\videojs_media\VideoJsMediaInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;

/**
 * Tests the MetadataExtractor service.
 *
 * @group skating_video_uploader
 * @group metadata
 * @coversDefaultClass \Drupal\skating_video_uploader\Service\MetadataExtractor
 */
class MetadataExtractorTest extends UnitTestCase {

  /**
   * The metadata extractor service.
   *
   * @var \Drupal\skating_video_uploader\Service\MetadataExtractor
   */
  protected $metadataExtractor;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileSystem;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * Mock database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->database = $this->createMock(Connection::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->metadataExtractor = new MetadataExtractor(
      $this->entityTypeManager,
      $this->loggerFactory,
      $this->fileSystem,
      $this->database
    );
  }

  /**
   * Tests GPS location parsing.
   *
   * @covers ::parseGpsLocation
   * @dataProvider gpsLocationProvider
   */
  public function testParseGpsLocation($location, $expected) {
    $method = new \ReflectionMethod($this->metadataExtractor, 'parseGpsLocation');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->metadataExtractor, $location);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for GPS location parsing tests.
   */
  public function gpsLocationProvider() {
    return [
      'valid with altitude' => [
        '+37.7749-122.4194+100.5/',
        [
          'latitude' => 37.7749,
          'longitude' => -122.4194,
          'altitude' => 100.5,
        ],
      ],
      'valid without altitude' => [
        '+37.7749-122.4194/',
        [
          'latitude' => 37.7749,
          'longitude' => -122.4194,
        ],
      ],
      'negative coordinates' => [
        '-33.8688+151.2093/',
        [
          'latitude' => -33.8688,
          'longitude' => 151.2093,
        ],
      ],
      'invalid format' => [
        'invalid',
        [],
      ],
      'empty string' => [
        '',
        [],
      ],
    ];
  }

  /**
   * Tests extraction with missing file.
   *
   * @covers ::extractMetadata
   */
  public function testExtractMetadataWithMissingFile() {
    $videojs_media = $this->createMock(VideoJsMediaInterface::class);
    $videojs_media->method('id')->willReturn(1);
    $videojs_media->method('bundle')->willReturn('local_video');
    $videojs_media->method('hasField')->willReturn(FALSE);

    $result = $this->metadataExtractor->extractMetadata($videojs_media);
    $this->assertNull($result);
  }

  /**
   * Tests extraction with wrong bundle type.
   *
   * @covers ::extractMetadata
   */
  public function testExtractMetadataWithWrongBundle() {
    $videojs_media = $this->createMock(VideoJsMediaInterface::class);
    $videojs_media->method('id')->willReturn(1);
    $videojs_media->method('bundle')->willReturn('youtube');

    $result = $this->metadataExtractor->extractMetadata($videojs_media);
    $this->assertNull($result);
  }

  /**
   * Tests getting file from VideoJS Media entity.
   *
   * @covers ::getFileFromVideoJsMedia
   */
  public function testGetFileFromVideoJsMediaWithLocalVideo() {
    $file = $this->createMock(FileInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(123)->willReturn($file);

    $this->entityTypeManager->method('getStorage')->with('file')->willReturn($storage);

    $field_item_list = $this->createMock(FieldItemListInterface::class);
    $field_item_list->method('isEmpty')->willReturn(FALSE);
    $field_item_list->target_id = 123;

    $videojs_media = $this->createMock(VideoJsMediaInterface::class);
    $videojs_media->method('bundle')->willReturn('local_video');
    $videojs_media->method('hasField')->with('field_media_file')->willReturn(TRUE);
    $videojs_media->method('get')->with('field_media_file')->willReturn($field_item_list);

    $method = new \ReflectionMethod($this->metadataExtractor, 'getFileFromVideoJsMedia');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->metadataExtractor, $videojs_media);

    $this->assertInstanceOf(FileInterface::class, $result);
  }

  /**
   * Tests GPS coordinate conversion from EXIF format.
   *
   * @covers ::convertGpsCoordinate
   * @group metadata
   */
  public function testConvertGpsCoordinate() {
    $method = new \ReflectionMethod($this->metadataExtractor, 'convertGpsCoordinate');
    $method->setAccessible(TRUE);

    // Test typical GPS coordinate array from EXIF.
    $coordinate = ['37/1', '46/1', '2949/100'];
    $result = $method->invoke($this->metadataExtractor, $coordinate);

    // 37 + (46/60) + (29.49/3600) = 37.7749...
    $this->assertEqualsWithDelta(37.7749, $result, 0.0001);
  }

  /**
   * Tests GPS coordinate conversion with malformed data.
   *
   * @covers ::convertGpsCoordinate
   * @group metadata
   */
  public function testConvertGpsCoordinateMalformed() {
    $method = new \ReflectionMethod($this->metadataExtractor, 'convertGpsCoordinate');
    $method->setAccessible(TRUE);

    // Test with incomplete array.
    $coordinate = ['37/1', '46/1'];
    $result = $method->invoke($this->metadataExtractor, $coordinate);
    $this->assertEquals(0.0, $result);

    // Test with empty array.
    $coordinate = [];
    $result = $method->invoke($this->metadataExtractor, $coordinate);
    $this->assertEquals(0.0, $result);
  }

  /**
   * Tests EXIF rational number conversion.
   *
   * @covers ::convertExifRational
   * @dataProvider exifRationalProvider
   * @group metadata
   */
  public function testConvertExifRational($input, $expected) {
    $method = new \ReflectionMethod($this->metadataExtractor, 'convertExifRational');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->metadataExtractor, $input);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for EXIF rational conversion tests.
   */
  public function exifRationalProvider() {
    return [
      'rational string' => ['5/1', 5.0],
      'fraction' => ['1/2', 0.5],
      'complex fraction' => ['71/10', 7.1],
      'float value' => [3.14, 3.14],
      'integer value' => [42, 42.0],
      'zero denominator' => ['1/0', 0.0],
      'invalid string' => ['invalid', 0.0],
    ];
  }

  /**
   * Tests GPS extraction from EXIF data.
   *
   * @covers ::extractGpsFromExif
   * @group metadata
   */
  public function testExtractGpsFromExif() {
    $method = new \ReflectionMethod($this->metadataExtractor, 'extractGpsFromExif');
    $method->setAccessible(TRUE);

    // Test complete GPS data with altitude.
    $gps_data = [
      'GPSLatitude' => ['37/1', '46/1', '2949/100'],
      'GPSLatitudeRef' => 'N',
      'GPSLongitude' => ['122/1', '25/1', '876/100'],
      'GPSLongitudeRef' => 'W',
      'GPSAltitude' => '100/1',
      'GPSAltitudeRef' => '0',
    ];

    $result = $method->invoke($this->metadataExtractor, $gps_data);

    $this->assertArrayHasKey('latitude', $result);
    $this->assertArrayHasKey('longitude', $result);
    $this->assertArrayHasKey('altitude', $result);
    $this->assertGreaterThan(0, $result['latitude']);
    $this->assertLessThan(0, $result['longitude']);
    $this->assertEquals(100.0, $result['altitude']);
  }

  /**
   * Tests GPS extraction with southern and western hemispheres.
   *
   * @covers ::extractGpsFromExif
   * @group metadata
   */
  public function testExtractGpsFromExifSouthWest() {
    $method = new \ReflectionMethod($this->metadataExtractor, 'extractGpsFromExif');
    $method->setAccessible(TRUE);

    $gps_data = [
      'GPSLatitude' => ['33/1', '52/1', '1008/100'],
      'GPSLatitudeRef' => 'S',
      'GPSLongitude' => ['151/1', '12/1', '3348/100'],
      'GPSLongitudeRef' => 'E',
    ];

    $result = $method->invoke($this->metadataExtractor, $gps_data);

    $this->assertLessThan(0, $result['latitude']);
    $this->assertGreaterThan(0, $result['longitude']);
  }

  /**
   * Tests GPS extraction with missing data.
   *
   * @covers ::extractGpsFromExif
   * @group metadata
   */
  public function testExtractGpsFromExifMissingData() {
    $method = new \ReflectionMethod($this->metadataExtractor, 'extractGpsFromExif');
    $method->setAccessible(TRUE);

    // Test with incomplete GPS data.
    $gps_data = [
      'GPSLatitude' => ['37/1', '46/1', '2949/100'],
      // Missing GPSLatitudeRef.
    ];

    $result = $method->invoke($this->metadataExtractor, $gps_data);
    $this->assertEmpty($result);
  }

  /**
   * Tests extractImageMetadata with missing EXIF extension.
   *
   * @covers ::extractImageMetadata
   * @group metadata
   */
  public function testExtractImageMetadataNoExifExtension() {
    // This test verifies graceful degradation when EXIF is not available.
    // Since we can't disable the extension in a unit test, we're testing
    // the conceptual behavior. In a real environment without EXIF,
    // the method should return NULL.
    $file = $this->createMock(FileInterface::class);

    // If EXIF is available, skip this test.
    if (function_exists('exif_read_data')) {
      $this->markTestSkipped('EXIF extension is available; cannot test missing extension scenario');
    }

    $result = $this->metadataExtractor->extractImageMetadata($file);
    $this->assertNull($result);
  }

  /**
   * Tests extractImageMetadata with non-existent file.
   *
   * @covers ::extractImageMetadata
   * @group metadata
   */
  public function testExtractImageMetadataFileNotFound() {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('public://nonexistent.jpg');

    $this->fileSystem->method('realpath')->willReturn('/nonexistent/path/file.jpg');

    $result = $this->metadataExtractor->extractImageMetadata($file);
    $this->assertNull($result);
  }

  /**
   * Tests extractVideoMetadata method.
   *
   * @covers ::extractVideoMetadata
   * @group metadata
   */
  public function testExtractVideoMetadata() {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('private://test.mp4');

    $this->fileSystem->method('realpath')->willReturn('/nonexistent/test.mp4');

    // Since we can't actually run ffprobe in unit tests, we expect NULL.
    $result = $this->metadataExtractor->extractVideoMetadata($file);
    $this->assertNull($result);
  }

  /**
   * Tests storeMetadata method.
   *
   * @covers ::storeMetadata
   * @group metadata
   */
  public function testStoreMetadata() {
    $metadata = [
      'latitude' => 37.7749,
      'longitude' => -122.4194,
      'timestamp' => '2024:01:15 10:30:00',
    ];

    $field_item_list = $this->createMock(FieldItemListInterface::class);

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_metadata')->willReturn(TRUE);
    $node->expects($this->once())
      ->method('set')
      ->with('field_metadata', $this->callback(function ($value) {
        return is_string($value) && json_decode($value) !== NULL;
      }));
    $node->method('get')->with('field_metadata')->willReturn($field_item_list);

    $result = $this->metadataExtractor->storeMetadata($node, $metadata);
    $this->assertTrue($result);
  }

  /**
   * Tests storeMetadata with missing field.
   *
   * @covers ::storeMetadata
   * @group metadata
   */
  public function testStoreMetadataNoField() {
    $metadata = ['test' => 'data'];

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_metadata')->willReturn(FALSE);

    $result = $this->metadataExtractor->storeMetadata($node, $metadata);
    $this->assertFalse($result);
  }

}
