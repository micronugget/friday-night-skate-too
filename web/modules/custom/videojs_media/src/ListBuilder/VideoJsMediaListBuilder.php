<?php

namespace Drupal\videojs_media\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the VideoJS media entity type.
 */
class VideoJsMediaListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    $header['author'] = $this->t('Author');
    $header['status'] = $this->t('Status');
    $header['changed'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\videojs_media\VideoJsMediaInterface $entity */
    $row['id'] = $entity->id();
    $row['name'] = $entity->toLink($entity->getName());
    $row['type'] = $entity->get('type')->entity ? $entity->get('type')->entity->label() : '';
    $row['author']['data'] = [
      '#theme' => 'username',
      '#account' => $entity->getOwner(),
    ];
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    $row['changed'] = \Drupal::service('date.formatter')->format($entity->getChangedTime(), 'short');
    return $row + parent::buildRow($entity);
  }

}
