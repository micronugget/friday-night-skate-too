<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Views;

/**
 * Tests the Archive by Date view configuration.
 *
 * @group fns_archive
 * @group views
 */
class ArchiveByDateViewTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'taxonomy',
    'datetime',
    'content_moderation',
    'workflows',
    'views',
    'fns_archive',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'taxonomy', 'views', 'fns_archive']);
  }

  /**
   * Tests that the archive_by_date view exists.
   */
  public function testViewExists(): void {
    $view = Views::getView('archive_by_date');
    $this->assertNotNull($view, 'Archive by Date view exists');
    $this->assertEquals('archive_by_date', $view->id(), 'View ID is correct');
  }

  /**
   * Tests that the view has correct display configuration.
   */
  public function testViewDisplayConfiguration(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');

    $this->assertEquals('Archive', $view->getTitle(), 'View title is correct');
    $this->assertEquals('default', $view->current_display, 'Display is default');
  }

  /**
   * Tests that the view has correct base table.
   */
  public function testViewBaseTable(): void {
    $view = Views::getView('archive_by_date');
    $this->assertEquals('node_field_data', $view->storage->get('base_table'), 'Base table is node_field_data');
  }

  /**
   * Tests that the view has contextual filter for taxonomy term.
   */
  public function testContextualFilter(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');
    $view->initHandlers();

    $arguments = $view->display_handler->getHandlers('argument');
    $this->assertArrayHasKey('field_skate_date_target_id', $arguments, 'Contextual filter exists for skate date');

    $argument = $arguments['field_skate_date_target_id'];
    $this->assertEquals('taxonomy_index_tid', $argument->getPluginId(), 'Contextual filter is taxonomy term ID');
    $this->assertEquals('empty', $argument->options['default_action'], 'Default action is empty when no argument');
  }

  /**
   * Tests that the view filters by content type.
   */
  public function testContentTypeFilter(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');
    $view->initHandlers();

    $filters = $view->display_handler->getHandlers('filter');
    $this->assertArrayHasKey('type', $filters, 'Content type filter exists');

    $type_filter = $filters['type'];
    $this->assertArrayHasKey('archive_media', $type_filter->options['value'], 'Filter includes archive_media content type');
  }

  /**
   * Tests that the view filters by published status.
   */
  public function testPublishedFilter(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');
    $view->initHandlers();

    $filters = $view->display_handler->getHandlers('filter');
    $this->assertArrayHasKey('status', $filters, 'Published status filter exists');

    $status_filter = $filters['status'];
    $this->assertEquals('1', $status_filter->options['value'], 'Filter only shows published content');
  }

  /**
   * Tests that the view filters by moderation state.
   */
  public function testModerationStateFilter(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');
    $view->initHandlers();

    $filters = $view->display_handler->getHandlers('filter');
    $this->assertArrayHasKey('moderation_state', $filters, 'Moderation state filter exists');

    $moderation_filter = $filters['moderation_state'];
    $this->assertArrayHasKey('published', $moderation_filter->options['value'], 'Filter only shows published moderation state');
  }

  /**
   * Tests that the view uses entity row plugin with thumbnail view mode.
   */
  public function testRowPluginConfiguration(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');
    $view->initStyle();

    $row_plugin = $view->style_plugin->row_plugin;
    $this->assertEquals('entity:node', $row_plugin->getPluginId(), 'Row plugin is entity:node');
    $this->assertEquals('thumbnail', $row_plugin->options['view_mode'], 'View mode is thumbnail');
  }

  /**
   * Tests that the view has correct sort order.
   */
  public function testSortOrder(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');
    $view->initHandlers();

    $sorts = $view->display_handler->getHandlers('sort');
    $this->assertArrayHasKey('field_timestamp_value', $sorts, 'Sort by timestamp exists');

    $sort = $sorts['field_timestamp_value'];
    $this->assertEquals('DESC', $sort->options['order'], 'Sort order is descending (newest first)');
  }

  /**
   * Tests that the view has correct access permissions.
   */
  public function testAccessPermissions(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');

    $access_plugin = $view->display_handler->getPlugin('access');
    $this->assertEquals('perm', $access_plugin->getPluginId(), 'Access plugin is permission-based');
    $this->assertEquals('access content', $access_plugin->options['perm'], 'Required permission is "access content"');
  }

  /**
   * Tests that the view has pager configuration.
   */
  public function testPagerConfiguration(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');

    $pager_plugin = $view->display_handler->getPlugin('pager');
    $this->assertEquals('full', $pager_plugin->getPluginId(), 'Pager plugin is full pager');
    $this->assertEquals(50, $pager_plugin->options['items_per_page'], 'Items per page is 50');
  }

  /**
   * Tests that the view has empty state configuration.
   */
  public function testEmptyStateConfiguration(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');
    $view->initHandlers();

    $empty_handlers = $view->display_handler->getHandlers('empty');
    $this->assertNotEmpty($empty_handlers, 'View has empty state handlers');
    $this->assertArrayHasKey('area_text_custom', $empty_handlers, 'Custom text area exists for empty state');
  }

  /**
   * Tests that the view has masonry row classes.
   */
  public function testMasonryRowClass(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');

    $style_options = $view->display_handler->getOption('style');
    $this->assertEquals('masonry-item', $style_options['options']['row_class'], 'Row class is masonry-item');
    $this->assertFalse($style_options['options']['default_row_class'], 'Default row class is disabled');
  }

  /**
   * Tests that the view can be executed without errors.
   */
  public function testViewExecution(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');

    // Execute without contextual filter argument.
    $view->execute();

    $this->assertIsArray($view->result, 'View execution returns array');
    $this->assertEmpty($view->result, 'View returns no results without nodes');
  }

  /**
   * Tests that the view handles invalid contextual filter gracefully.
   */
  public function testInvalidContextualFilter(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');

    // Execute with invalid term ID.
    $view->setArguments([99999]);
    $view->execute();

    $this->assertIsArray($view->result, 'View handles invalid term ID gracefully');
    $this->assertEmpty($view->result, 'View returns no results for invalid term');
  }

  /**
   * Tests view cache configuration.
   */
  public function testCacheConfiguration(): void {
    $view = Views::getView('archive_by_date');
    $view->setDisplay('default');

    $cache_plugin = $view->display_handler->getPlugin('cache');
    $this->assertEquals('tag', $cache_plugin->getPluginId(), 'Cache plugin is tag-based');
  }

}
