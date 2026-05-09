<?php

declare(strict_types=1);

namespace Drupal\fns_migrate\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolve a managed-file id to a real filesystem path.
 *
 * The legacy route migration produces managed File entities for downloaded
 * GPX tracks; the {@see GpxToGeofield} plugin needs to read those files via
 * `file_get_contents`, which only works with a real filesystem path. This
 * plugin loads the File entity referenced by an integer fid and resolves its
 * URI through the file_system service.
 *
 * Returns NULL when the input is empty or the entity cannot be loaded; that
 * lets `skip_on_empty` further down the pipeline short-circuit cleanly.
 *
 * Example:
 * @code
 * _gpx_path:
 *   -
 *     plugin: migration_lookup
 *     migration: fns_route_gpx_files
 *     source: slug
 *   -
 *     plugin: file_id_to_realpath
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "file_id_to_realpath"
 * )
 */
class FileIdToRealpath extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected FileSystemInterface $fileSystem,
    protected $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || $value === '' || $value === 0 || $value === '0') {
      return NULL;
    }
    $fid = is_array($value) ? reset($value) : $value;
    if (!is_numeric($fid)) {
      return NULL;
    }

    $file = $this->entityTypeManager->getStorage('file')->load((int) $fid);
    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $real = $this->fileSystem->realpath($file->getFileUri());
    return $real !== FALSE ? $real : NULL;
  }

}
