<?php

declare(strict_types=1);

namespace Drupal\fns_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Look up the v2 node ID for a legacy route slug via the migrate map table.
 *
 * Wraps the core `migrate_lookup` service with graceful "unknown slug"
 * handling: when the slug has no entry in the `fns_route` migration map the
 * plugin logs a notice and returns NULL instead of failing the whole row.
 *
 * Configuration:
 *   migration: Migration ID to look up against. Default: `fns_route`.
 *
 * Example:
 * @code
 * field_route/target_id:
 *   plugin: fns_route_slug_lookup
 *   source: route_slug
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "fns_route_slug_lookup"
 * )
 */
class RouteSlugLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  protected const DEFAULT_MIGRATION = 'fns_route';

  /**
   * Constructs a RouteSlugLookup process plugin.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\migrate\MigrateLookupInterface $migrateLookup
   *   The migrate lookup service.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The current migration.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected MigrateLookupInterface $migrateLookup,
    protected MigrationInterface $migration,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('migrate.lookup'),
      $migration,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    $migrationId = (string) ($this->configuration['migration'] ?? self::DEFAULT_MIGRATION);

    try {
      $result = $this->migrateLookup->lookup([$migrationId], [(string) $value]);
    }
    catch (\Exception $e) {
      $migrate_executable->saveMessage(
        sprintf('fns_route_slug_lookup: lookup failed for slug "%s": %s', $value, $e->getMessage())
      );
      return NULL;
    }

    if (empty($result)) {
      $migrate_executable->saveMessage(
        sprintf('fns_route_slug_lookup: no v2 node found for route slug "%s" — field left empty.', $value)
      );
      return NULL;
    }

    // lookup() returns an array of destination ID arrays; grab the first nid.
    return reset($result[0]);
  }

}
