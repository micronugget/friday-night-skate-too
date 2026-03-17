<?php

namespace Drupal\videojs_media\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for VideoJS media type forms.
 */
class VideoJsMediaTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity_type = $this->entity;

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add VideoJS media type');
    }
    else {
      $form['#title'] = $this->t('Edit %label VideoJS media type', ['%label' => $entity_type->label()]);
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $entity_type->label(),
      '#description' => $this->t('The human-readable name of this VideoJS media type.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => ['Drupal\videojs_media\Entity\VideoJsMediaType', 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this VideoJS media type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#default_value' => $entity_type->getDescription(),
      '#description' => $this->t('Describe this VideoJS media type.'),
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save VideoJS media type');
    $actions['delete']['#value'] = $this->t('Delete VideoJS media type');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity_type = $this->entity;
    $result = $entity_type->save();

    $message_args = ['%label' => $entity_type->label()];
    $logger_args = [
      '%label' => $entity_type->label(),
      'link' => $entity_type->toLink($this->t('View'), 'collection')->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created new VideoJS media type %label.', $message_args));
        $this->logger('videojs_media')->notice('Created new VideoJS media type %label.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('Updated VideoJS media type %label.', $message_args));
        $this->logger('videojs_media')->notice('Updated VideoJS media type %label.', $logger_args);
        break;
    }

    $form_state->setRedirectUrl($entity_type->toUrl('collection'));

    return $result;
  }

}
