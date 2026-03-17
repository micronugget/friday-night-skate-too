<?php

namespace Drupal\videojs_media\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VideoJS Media block.
 */
#[Block(
  id: 'videojs_media',
  admin_label: new TranslatableMarkup('VideoJS Media'),
  category: new TranslatableMarkup('Media'),
)]
class VideoJsMediaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new VideoJsMediaBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('videojs_media');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'videojs_media_id' => NULL,
      'view_mode' => 'default',
      'hide_title' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['videojs_media_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('VideoJS Media'),
      '#description' => $this->t('Select a VideoJS Media item to display.'),
      '#target_type' => 'videojs_media',
      '#default_value' => $this->getDefaultEntity(),
      '#required' => TRUE,
    ];

    // Get available view modes.
    $view_modes = $this->entityTypeManager
      ->getStorage('entity_view_mode')
      ->loadByProperties(['targetEntityType' => 'videojs_media']);

    $view_mode_options = ['default' => $this->t('Default')];
    foreach ($view_modes as $view_mode) {
      // EntityViewMode ID is in format "entity_type.view_mode", extract
      // just the view mode part.
      $view_mode_id = $view_mode->id();
      $mode = substr($view_mode_id, strpos($view_mode_id, '.') + 1);
      $view_mode_options[$mode] = $view_mode->label();
    }

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#description' => $this->t('Select the view mode to use for rendering the VideoJS Media item.'),
      '#options' => $view_mode_options,
      '#default_value' => $this->configuration['view_mode'],
    ];

    $form['hide_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide title'),
      '#description' => $this->t('Check this option to hide the VideoJS Media title from displaying in the block.'),
      '#default_value' => $this->configuration['hide_title'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['videojs_media_id'] = $form_state->getValue('videojs_media_id');
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    $this->configuration['hide_title'] = $form_state->getValue('hide_title');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $videojs_media_id = $this->configuration['videojs_media_id'];
    $view_mode = $this->configuration['view_mode'] ?? 'default';

    if ($videojs_media_id) {
      try {
        $videojs_media = $this->entityTypeManager
          ->getStorage('videojs_media')
          ->load($videojs_media_id);

        if ($videojs_media && $videojs_media->access('view')) {
          $view_builder = $this->entityTypeManager->getViewBuilder('videojs_media');
          $build = $view_builder->view($videojs_media, $view_mode);

          // Hide the title if the option is enabled.
          if (!empty($this->configuration['hide_title'])) {
            $build['#videojs_media']->set('name', NULL);
            // Also set label_hidden in the build array for theme layer.
            $build['#label_hidden'] = TRUE;
          }
        }
      }
      catch (\Exception $e) {
        // Log error but don't break the page.
        $this->logger->error('Error loading VideoJS Media @id: @message', [
          '@id' => $videojs_media_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();

    // Add cache tag for the referenced entity.
    if ($videojs_media_id = $this->configuration['videojs_media_id']) {
      $tags[] = 'videojs_media:' . $videojs_media_id;
    }

    return $tags;
  }

  /**
   * Gets the default entity for the autocomplete field.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity or NULL.
   */
  protected function getDefaultEntity() {
    if (!empty($this->configuration['videojs_media_id'])) {
      return $this->entityTypeManager
        ->getStorage('videojs_media')
        ->load($this->configuration['videojs_media_id']);
    }
    return NULL;
  }

}
