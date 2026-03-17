<?php

namespace Drupal\videojs_media\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the VideoJS media entity type.
 */
class VideoJsMediaAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */

    // Administrative users can do anything.
    if ($account->hasPermission('administer videojs media')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $bundle = $entity->bundle();

    switch ($operation) {
      case 'view':
        // Check if entity is published.
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, "view unpublished $bundle videojs media")
            ->cachePerPermissions()
            ->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermission($account, "view $bundle videojs media")
          ->cachePerPermissions();

      case 'update':
        // Check if user can edit any or own entities.
        if ($account->hasPermission("edit any $bundle videojs media")) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($account->hasPermission("edit own $bundle videojs media") && ($account->id() == $entity->getOwnerId())) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        return AccessResult::neutral()->cachePerPermissions();

      case 'delete':
        // Check if user can delete any or own entities.
        if ($account->hasPermission("delete any $bundle videojs media")) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($account->hasPermission("delete own $bundle videojs media") && ($account->id() == $entity->getOwnerId())) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        return AccessResult::neutral()->cachePerPermissions();

      default:
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Administrative users can create any bundle.
    if ($account->hasPermission('administer videojs media')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Check bundle-specific create permission.
    if ($entity_bundle && $account->hasPermission("create $entity_bundle videojs media")) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return AccessResult::neutral()->cachePerPermissions();
  }

}
