<?php

declare(strict_types=1);

namespace Drupal\fns_archive\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\content_moderation\ModerationInformationInterface;

/**
 * Service for sending moderation workflow notifications.
 */
class ModerationNotifier {

  use StringTranslationTrait;

  /**
   * Constructs a ModerationNotifier object.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInfo
   *   The moderation information service.
   */
  public function __construct(
    protected MailManagerInterface $mailManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    TranslationInterface $stringTranslation,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected ModerationInformationInterface $moderationInfo,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Sends notification when content is submitted for review.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   *
   * @return bool
   *   TRUE if notifications were sent successfully, FALSE otherwise.
   */
  public function notifyOnSubmission(ContentEntityInterface $entity): bool {
    $moderators = $this->getModerators();
    if (empty($moderators)) {
      $this->loggerFactory->get('fns_archive')->warning(
        'No moderators found to notify for @entity_type @entity_id',
        [
          '@entity_type' => $entity->getEntityTypeId(),
          '@entity_id' => $entity->id(),
        ]
      );
      return FALSE;
    }

    $author = $entity->getOwner();
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();

    $params = [
      'entity' => $entity,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_label' => $entity->label(),
      'author_name' => $author->getDisplayName(),
      'url' => $url,
      'message' => $this->t('New content "@title" has been submitted for review by @author.', [
        '@title' => $entity->label(),
        '@author' => $author->getDisplayName(),
      ]),
    ];

    $success = TRUE;
    foreach ($moderators as $moderator) {
      if (!$this->sendMail($moderator->getEmail(), 'submission', $params)) {
        $success = FALSE;
      }
    }

    if ($success) {
      $this->loggerFactory->get('fns_archive')->info(
        'Moderation notification sent for @entity_type @entity_id to @count moderators',
        [
          '@entity_type' => $entity->getEntityTypeId(),
          '@entity_id' => $entity->id(),
          '@count' => count($moderators),
        ]
      );
    }

    return $success;
  }

  /**
   * Sends notification when content is published.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   *
   * @return bool
   *   TRUE if notification was sent successfully, FALSE otherwise.
   */
  public function notifyOnApproval(ContentEntityInterface $entity): bool {
    $author = $entity->getOwner();
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $moderator = $this->currentUser->getDisplayName();

    $params = [
      'entity' => $entity,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_label' => $entity->label(),
      'moderator_name' => $moderator,
      'url' => $url,
      'message' => $this->t('Your content "@title" has been approved and published by @moderator.', [
        '@title' => $entity->label(),
        '@moderator' => $moderator,
      ]),
    ];

    $success = $this->sendMail($author->getEmail(), 'approval', $params);

    if ($success) {
      $this->loggerFactory->get('fns_archive')->info(
        'Approval notification sent for @entity_type @entity_id to @author',
        [
          '@entity_type' => $entity->getEntityTypeId(),
          '@entity_id' => $entity->id(),
          '@author' => $author->getDisplayName(),
        ]
      );
    }

    return $success;
  }

  /**
   * Sends notification when content is rejected/sent back to draft.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being moderated.
   * @param string $reason
   *   The reason for rejection.
   *
   * @return bool
   *   TRUE if notification was sent successfully, FALSE otherwise.
   */
  public function notifyOnRejection(ContentEntityInterface $entity, string $reason = ''): bool {
    $author = $entity->getOwner();
    $url = $entity->toUrl('edit-form', ['absolute' => TRUE])->toString();
    $moderator = $this->currentUser->getDisplayName();

    $params = [
      'entity' => $entity,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_label' => $entity->label(),
      'moderator_name' => $moderator,
      'reason' => $reason,
      'url' => $url,
      'message' => $this->t('Your content "@title" has been sent back to draft by @moderator.', [
        '@title' => $entity->label(),
        '@moderator' => $moderator,
      ]),
    ];

    $success = $this->sendMail($author->getEmail(), 'rejection', $params);

    if ($success) {
      $this->loggerFactory->get('fns_archive')->info(
        'Rejection notification sent for @entity_type @entity_id to @author',
        [
          '@entity_type' => $entity->getEntityTypeId(),
          '@entity_id' => $entity->id(),
          '@author' => $author->getDisplayName(),
        ]
      );
    }

    return $success;
  }

  /**
   * Gets all users with the moderator role.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of moderator users.
   */
  protected function getModerators(): array {
    try {
      $userStorage = $this->entityTypeManager->getStorage('user');
      $query = $userStorage->getQuery()
        ->condition('status', 1)
        ->condition('roles', 'moderator')
        ->accessCheck(FALSE);
      $uids = $query->execute();

      return $userStorage->loadMultiple($uids);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('fns_archive')->error(
        'Failed to load moderators: @message',
        ['@message' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Sends an email notification.
   *
   * @param string $to
   *   The recipient email address.
   * @param string $key
   *   The mail key (submission, approval, rejection).
   * @param array $params
   *   The mail parameters.
   *
   * @return bool
   *   TRUE if email was sent successfully, FALSE otherwise.
   */
  protected function sendMail(string $to, string $key, array $params): bool {
    if (empty($to)) {
      return FALSE;
    }

    $langcode = $this->currentUser->getPreferredLangcode();
    $send = TRUE;

    try {
      $result = $this->mailManager->mail('fns_archive', $key, $to, $langcode, $params, NULL, $send);
      return !empty($result['result']);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('fns_archive')->error(
        'Failed to send @key email to @to: @message',
        [
          '@key' => $key,
          '@to' => $to,
          '@message' => $e->getMessage(),
        ]
      );
      return FALSE;
    }
  }

}
