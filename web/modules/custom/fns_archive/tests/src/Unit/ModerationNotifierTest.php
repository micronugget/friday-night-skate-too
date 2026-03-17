<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\fns_archive\Service\ModerationNotifier;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Tests the ModerationNotifier service.
 *
 * @group fns_archive
 * @group moderation
 * @coversDefaultClass \Drupal\fns_archive\Service\ModerationNotifier
 */
class ModerationNotifierTest extends UnitTestCase {

  /**
   * The moderation notifier service.
   *
   * @var \Drupal\fns_archive\Service\ModerationNotifier
   */
  protected $moderationNotifier;

  /**
   * Mock mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mailManager;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * Mock string translation.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * Mock moderation information.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moderationInfo;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mailManager = $this->createMock(MailManagerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->stringTranslation = $this->createMock(TranslationInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->moderationInfo = $this->createMock(ModerationInformationInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->stringTranslation->method('translate')
      ->willReturnCallback(fn($string) => $string);

    $this->currentUser->method('getPreferredLangcode')
      ->willReturn('en');
    $this->currentUser->method('getDisplayName')
      ->willReturn('Test Moderator');

    $this->moderationNotifier = new ModerationNotifier(
      $this->mailManager,
      $this->entityTypeManager,
      $this->currentUser,
      $this->stringTranslation,
      $this->loggerFactory,
      $this->moderationInfo
    );
  }

  /**
   * Tests notification on content submission.
   *
   * @covers ::notifyOnSubmission
   */
  public function testNotifyOnSubmission(): void {
    $entity = $this->createMockEntity('Test Content', 'test@example.com');
    $moderators = [
      $this->createMockUser('mod1@example.com'),
      $this->createMockUser('mod2@example.com'),
    ];

    $this->setupUserStorage($moderators);

    $callCount = 0;
    $emailsSent = [];
    $this->mailManager->expects($this->exactly(2))
      ->method('mail')
      ->willReturnCallback(function ($module, $key, $to, $langcode, $params) use (&$callCount, &$emailsSent) {
        $callCount++;
        $emailsSent[] = $to;
        $this->assertEquals('fns_archive', $module);
        $this->assertEquals('submission', $key);
        $this->assertContains($to, ['mod1@example.com', 'mod2@example.com']);
        $this->assertArrayHasKey('entity', $params);
        $this->assertArrayHasKey('author_name', $params);
        $this->assertArrayHasKey('url', $params);
        return ['result' => TRUE];
      });

    $result = $this->moderationNotifier->notifyOnSubmission($entity);
    $this->assertTrue($result);
    $this->assertCount(2, $emailsSent);
    $this->assertContains('mod1@example.com', $emailsSent);
    $this->assertContains('mod2@example.com', $emailsSent);
  }

  /**
   * Tests notification on content approval.
   *
   * @covers ::notifyOnApproval
   */
  public function testNotifyOnApproval(): void {
    $entity = $this->createMockEntity('Test Content', 'author@example.com');

    $this->mailManager->expects($this->once())
      ->method('mail')
      ->with(
        'fns_archive',
        'approval',
        'author@example.com',
        'en',
        $this->callback(function ($params) {
          return isset($params['entity']) &&
                 isset($params['moderator_name']) &&
                 isset($params['url']);
        })
      )
      ->willReturn(['result' => TRUE]);

    $result = $this->moderationNotifier->notifyOnApproval($entity);
    $this->assertTrue($result);
  }

  /**
   * Tests notification on content rejection.
   *
   * @covers ::notifyOnRejection
   */
  public function testNotifyOnRejection(): void {
    $entity = $this->createMockEntity('Test Content', 'author@example.com');

    $this->mailManager->expects($this->once())
      ->method('mail')
      ->with(
        'fns_archive',
        'rejection',
        'author@example.com',
        'en',
        $this->callback(function ($params) {
          return isset($params['entity']) &&
                 isset($params['moderator_name']) &&
                 isset($params['reason']) &&
                 $params['reason'] === 'Needs better images';
        })
      )
      ->willReturn(['result' => TRUE]);

    $result = $this->moderationNotifier->notifyOnRejection($entity, 'Needs better images');
    $this->assertTrue($result);
  }

  /**
   * Tests that no email is sent when author email is empty.
   *
   * @covers ::notifyOnApproval
   */
  public function testNotifySkipsEmptyEmail(): void {
    $entity = $this->createMockEntity('Test Content', '');

    $this->mailManager->expects($this->never())
      ->method('mail');

    $result = $this->moderationNotifier->notifyOnApproval($entity);
    $this->assertFalse($result);
  }

  /**
   * Tests notification failure when mail sending fails.
   *
   * @covers ::notifyOnApproval
   */
  public function testNotifyReturnsFalseOnMailFailure(): void {
    $entity = $this->createMockEntity('Test Content', 'author@example.com');

    $this->mailManager->expects($this->once())
      ->method('mail')
      ->willReturn(['result' => FALSE]);

    $result = $this->moderationNotifier->notifyOnApproval($entity);
    $this->assertFalse($result);
  }

  /**
   * Tests notification returns false when no moderators found.
   *
   * @covers ::notifyOnSubmission
   */
  public function testNotifySubmissionReturnsFalseWhenNoModerators(): void {
    $entity = $this->createMockEntity('Test Content', 'test@example.com');
    $this->setupUserStorage([]);

    $this->mailManager->expects($this->never())
      ->method('mail');

    $result = $this->moderationNotifier->notifyOnSubmission($entity);
    $this->assertFalse($result);
  }

  /**
   * Creates a mock entity for testing.
   *
   * @param string $label
   *   The entity label.
   * @param string $authorEmail
   *   The author's email.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Entity\ContentEntityInterface
   *   The mock entity.
   */
  protected function createMockEntity(string $label, string $authorEmail) {
    $entity = $this->createMock(ContentEntityInterface::class);
    $owner = $this->createMock(UserInterface::class);

    $owner->method('getEmail')->willReturn($authorEmail);
    $owner->method('getDisplayName')->willReturn('Test Author');

    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('https://example.com/node/1');

    $entity->method('label')->willReturn($label);
    $entity->method('getOwner')->willReturn($owner);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn(1);
    $entity->method('toUrl')->willReturn($url);

    return $entity;
  }

  /**
   * Creates a mock user.
   *
   * @param string $email
   *   The user's email.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\user\UserInterface
   *   The mock user.
   */
  protected function createMockUser(string $email) {
    $user = $this->createMock(UserInterface::class);
    $user->method('getEmail')->willReturn($email);
    return $user;
  }

  /**
   * Sets up the user storage to return moderators.
   *
   * @param array $moderators
   *   Array of mock moderator users.
   */
  protected function setupUserStorage(array $moderators): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $query = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['condition', 'accessCheck', 'execute'])
      ->getMock();

    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn(array_keys($moderators));

    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($moderators);

    $this->entityTypeManager->method('getStorage')
      ->with('user')
      ->willReturn($storage);
  }

}
