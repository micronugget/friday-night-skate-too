<?php

namespace Drupal\videojs_media\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the VideoJS media entity edit forms.
 */
class VideoJsMediaForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();
    $entity_type = $entity->getEntityType();

    $message_args = [
      '%label' => $entity->toLink($entity->label())->toString(),
      '@entity_type' => $entity_type->getSingularLabel(),
    ];
    $logger_args = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New @entity_type %label has been created.', $message_args));
        $this->logger('videojs_media')->notice('Created new @entity_type %label', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The @entity_type %label has been updated.', $message_args));
        $this->logger('videojs_media')->notice('Updated @entity_type %label.', $logger_args);
        break;
    }

    $form_state->setRedirect('entity.videojs_media.canonical', ['videojs_media' => $entity->id()]);

    return $result;
  }

}
