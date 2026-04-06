<?php

declare(strict_types=1);

namespace Drupal\fns_archive\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Stub action for node_make_sticky_action removed from Drupal 11 core.
 *
 * This stub exists to satisfy config references in node module's config/install
 * that reference this plugin ID. The plugin was removed from core in Drupal 11
 * but the config/install YAML still ships with it.
 */
#[Action(
  id: 'node_make_sticky_action',
  label: new TranslatableMarkup('Make content sticky'),
  type: 'node'
)]
class NodeMakeStickyAction extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL): void {
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool {
    return FALSE;
  }

}
