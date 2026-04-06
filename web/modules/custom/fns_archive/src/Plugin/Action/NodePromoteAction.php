<?php

declare(strict_types=1);

namespace Drupal\fns_archive\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Stub action for node_promote_action removed from Drupal 11 core.
 */
#[Action(
  id: 'node_promote_action',
  label: new TranslatableMarkup('Promote content to front page'),
  type: 'node'
)]
class NodePromoteAction extends ActionBase {

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
