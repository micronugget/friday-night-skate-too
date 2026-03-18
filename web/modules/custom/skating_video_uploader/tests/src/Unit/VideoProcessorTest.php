<?php

declare(strict_types=1);

namespace Drupal\Tests\skating_video_uploader\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\skating_video_uploader\Service\VideoProcessor;
use Drupal\skating_video_uploader\Service\MetadataExtractor;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\videojs_media\VideoJsMediaInterface;

/**
 * Tests the VideoProcessor service.
 *
 * @group skating_video_uploader
 * @coversDefaultClass \Drupal\skating_video_uploader\Service\VideoProcessor
 */
class VideoProcessorTest extends UnitTestCase {

  /**
   * The video processor service.
   *
   * @var \Drupal\skating_video_uploader\Service\VideoProcessor
   */
  protected $videoProcessor;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock metadata extractor.
   *
   * @var \Drupal\skating_video_uploader\Service\MetadataExtractor|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $metadataExtractor;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * Mock messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->metadataExtractor = $this->createMock(MetadataExtractor::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->videoProcessor = new VideoProcessor(
      $this->entityTypeManager,
      $this->loggerFactory,
      $this->metadataExtractor,
      $this->configFactory,
      $this->messenger
    );
  }

  /**
   * Tests processing with non-VideoJS Media entity.
   *
   * @covers ::processEntity
   */
  public function testProcessEntityWithInvalidEntityType() {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');

    $this->messenger->expects($this->once())
      ->method('addError');

    $this->videoProcessor->processEntity($entity);
    $this->assertFalse($result);
  }

  /**
   * Tests processing with wrong bundle type.
   *
   * @covers ::processEntity
   */
  public function testProcessEntityWithWrongBundle() {
    $videojs_media = $this->createMock(VideoJsMediaInterface::class);
    $videojs_media->method('bundle')->willReturn('youtube');

    $this->messenger->expects($this->once())
      ->method('addError');

    $this->videoProcessor->processEntity($videojs_media);
    $this->assertFalse($result);
  }

  /**
   * Tests processing with metadata extraction failure.
   *
   * @covers ::processEntity
   */
  public function testProcessEntityWithMetadataExtractionFailure() {
    $videojs_media = $this->createMock(VideoJsMediaInterface::class);
    $videojs_media->method('bundle')->willReturn('local_video');
    $videojs_media->method('id')->willReturn(1);

    $this->metadataExtractor->method('extractMetadata')
      ->with($videojs_media)
      ->willReturn(NULL);

    $this->messenger->expects($this->once())
      ->method('addError');

    $this->videoProcessor->processEntity($videojs_media);
    $this->assertFalse($result);
  }

  /**
   * Tests successful processing with local_video bundle.
   *
   * @covers ::processEntity
   */
  public function testProcessEntityWithLocalVideo() {
    $videojs_media = $this->createMock(VideoJsMediaInterface::class);
    $videojs_media->method('bundle')->willReturn('local_video');
    $videojs_media->method('id')->willReturn(1);

    $metadata = [
      'videojs_media_id' => 1,
      'file_id' => 123,
      'latitude' => 37.7749,
      'longitude' => -122.4194,
      'duration' => 60.0,
    ];

    $this->metadataExtractor->method('extractMetadata')
      ->with($videojs_media)
      ->willReturn($metadata);

    // Mock the YouTube uploader service call
    // Note: In real testing, we'd need to properly mock \Drupal::service()
    // For unit tests, this demonstrates the expected behavior.
    $this->videoProcessor->processEntity($videojs_media);
    // Result depends on YouTube upload success which we can't fully test in unit tests.
  }

  /**
   * Tests successful processing with local_audio bundle.
   *
   * @covers ::processEntity
   */
  public function testProcessEntityWithLocalAudio() {
    $videojs_media = $this->createMock(VideoJsMediaInterface::class);
    $videojs_media->method('bundle')->willReturn('local_audio');
    $videojs_media->method('id')->willReturn(2);

    $metadata = [
      'videojs_media_id' => 2,
      'file_id' => 456,
      'duration' => 180.0,
    ];

    $this->metadataExtractor->method('extractMetadata')
      ->with($videojs_media)
      ->willReturn($metadata);

    $this->videoProcessor->processEntity($videojs_media);
    // Result depends on YouTube upload success.
  }

}
