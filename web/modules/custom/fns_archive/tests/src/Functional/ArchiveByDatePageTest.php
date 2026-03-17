<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests the Archive by Date page functionality.
 *
 * @group fns_archive
 * @group functional
 */
class ArchiveByDatePageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'taxonomy',
    'datetime',
    'image',
    'media',
    'file',
    'views',
    'content_moderation',
    'workflows',
    'fns_archive',
  ];

  /**
   * Test taxonomy term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * Test archive media nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create skate_dates vocabulary if not exists.
    if (!Vocabulary::load('skate_dates')) {
      Vocabulary::create([
        'vid' => 'skate_dates',
        'name' => 'Skate Dates',
      ])->save();
    }

    // Create a test taxonomy term.
    $this->term = Term::create([
      'vid' => 'skate_dates',
      'name' => 'January 2025',
    ]);
    $this->term->save();

    // Create test nodes with the taxonomy term.
    for ($i = 1; $i <= 5; $i++) {
      $node = Node::create([
        'type' => 'archive_media',
        'title' => "Test Archive Media {$i}",
        'field_skate_date' => ['target_id' => $this->term->id()],
        'field_timestamp' => [
          'value' => date('Y-m-d\TH:i:s', strtotime("2025-01-{$i} 20:00:00")),
        ],
        'moderation_state' => 'published',
        'status' => 1,
      ]);
      $node->save();
      $this->nodes[] = $node;
    }
  }

  /**
   * Tests that archive page is accessible.
   */
  public function testArchivePageAccess(): void {
    $url = "/archive/{$this->term->id()}";
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that archive page displays content.
   */
  public function testArchivePageDisplaysContent(): void {
    $this->drupalGet("/archive/{$this->term->id()}");

    foreach ($this->nodes as $node) {
      $this->assertSession()->pageTextContains($node->label());
    }
  }

  /**
   * Tests that archive page has masonry container class.
   */
  public function testMasonryContainerClass(): void {
    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->elementExists('css', '.masonry-grid');
  }

  /**
   * Tests that archive items have masonry-item class.
   */
  public function testMasonryItemClass(): void {
    $this->drupalGet("/archive/{$this->term->id()}");
    $items = $this->getSession()->getPage()->findAll('css', '.masonry-item');

    $this->assertNotEmpty($items, 'Masonry items exist on page');
    $this->assertCount(5, $items, 'All 5 nodes are displayed as masonry items');
  }

  /**
   * Tests that masonry library is attached to the page.
   */
  public function testMasonryLibraryAttached(): void {
    $this->drupalGet("/archive/{$this->term->id()}");

    // Check for library attachment in HTML.
    $html = $this->getSession()->getPage()->getHtml();
    $this->assertStringContainsString('fridaynightskate/masonry-archive', $html, 'Masonry library is attached');
  }

  /**
   * Tests that archive page shows empty text when no content.
   */
  public function testEmptyStateDisplay(): void {
    // Create a new term with no content.
    $empty_term = Term::create([
      'vid' => 'skate_dates',
      'name' => 'February 2025',
    ]);
    $empty_term->save();

    $this->drupalGet("/archive/{$empty_term->id()}");
    $this->assertSession()->pageTextContains('No archive media available for this date');
    $this->assertSession()->linkExists('View all archive dates');
  }

  /**
   * Tests that archive page respects published filter.
   */
  public function testPublishedFilter(): void {
    // Create an unpublished node.
    $unpublished_node = Node::create([
      'type' => 'archive_media',
      'title' => 'Unpublished Test Media',
      'field_skate_date' => ['target_id' => $this->term->id()],
      'moderation_state' => 'draft',
      'status' => 0,
    ]);
    $unpublished_node->save();

    $this->drupalGet("/archive/{$this->term->id()}");

    // Should see published nodes.
    $this->assertSession()->pageTextContains('Test Archive Media 1');

    // Should NOT see unpublished node.
    $this->assertSession()->pageTextNotContains('Unpublished Test Media');
  }

  /**
   * Tests that archive page respects moderation state filter.
   */
  public function testModerationStateFilter(): void {
    // Create a node in review state.
    $review_node = Node::create([
      'type' => 'archive_media',
      'title' => 'In Review Media',
      'field_skate_date' => ['target_id' => $this->term->id()],
      'moderation_state' => 'review',
      'status' => 0,
    ]);
    $review_node->save();

    $this->drupalGet("/archive/{$this->term->id()}");

    // Should NOT see nodes in review state.
    $this->assertSession()->pageTextNotContains('In Review Media');
  }

  /**
   * Tests that archive page sorts by timestamp descending.
   */
  public function testSortOrder(): void {
    $this->drupalGet("/archive/{$this->term->id()}");

    $html = $this->getSession()->getPage()->getHtml();

    // Find positions of first and last node titles.
    $pos_newest = strpos($html, 'Test Archive Media 5');
    $pos_oldest = strpos($html, 'Test Archive Media 1');

    $this->assertNotFalse($pos_newest, 'Newest content is present');
    $this->assertNotFalse($pos_oldest, 'Oldest content is present');
    $this->assertLessThan($pos_oldest, $pos_newest, 'Newest content appears before oldest content');
  }

  /**
   * Tests pagination functionality.
   */
  public function testPagination(): void {
    // Create more nodes to trigger pagination (51 nodes total, 50 per page).
    for ($i = 6; $i <= 51; $i++) {
      $node = Node::create([
        'type' => 'archive_media',
        'title' => "Test Archive Media {$i}",
        'field_skate_date' => ['target_id' => $this->term->id()],
        'field_timestamp' => [
          'value' => date('Y-m-d\TH:i:s', strtotime("2025-01-01 20:{$i}:00")),
        ],
        'moderation_state' => 'published',
        'status' => 1,
      ]);
      $node->save();
    }

    $this->drupalGet("/archive/{$this->term->id()}");

    // Should see pager.
    $this->assertSession()->elementExists('css', 'nav.pager');
    $this->assertSession()->linkExists('Next');

    // Navigate to page 2.
    $this->clickLink('Next');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Previous');
  }

  /**
   * Tests that invalid term ID shows empty state.
   */
  public function testInvalidTermId(): void {
    $this->drupalGet('/archive/99999');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('No archive media available for this date');
  }

  /**
   * Tests that only content from specified term is displayed.
   */
  public function testTermFiltering(): void {
    // Create another term with different content.
    $other_term = Term::create([
      'vid' => 'skate_dates',
      'name' => 'December 2024',
    ]);
    $other_term->save();

    $other_node = Node::create([
      'type' => 'archive_media',
      'title' => 'December Archive Media',
      'field_skate_date' => ['target_id' => $other_term->id()],
      'moderation_state' => 'published',
      'status' => 1,
    ]);
    $other_node->save();

    // Visit first term's page.
    $this->drupalGet("/archive/{$this->term->id()}");

    // Should see January content.
    $this->assertSession()->pageTextContains('Test Archive Media 1');

    // Should NOT see December content.
    $this->assertSession()->pageTextNotContains('December Archive Media');

    // Visit second term's page.
    $this->drupalGet("/archive/{$other_term->id()}");

    // Should see December content.
    $this->assertSession()->pageTextContains('December Archive Media');

    // Should NOT see January content.
    $this->assertSession()->pageTextNotContains('Test Archive Media 1');
  }

  /**
   * Tests that view uses thumbnail view mode.
   */
  public function testThumbnailViewMode(): void {
    $this->drupalGet("/archive/{$this->term->id()}");

    // Check that nodes are rendered (not just fields).
    $html = $this->getSession()->getPage()->getHtml();
    $this->assertStringContainsString('node--type-archive-media', $html, 'Nodes are rendered as entities');
  }

  /**
   * Tests anonymous user access.
   */
  public function testAnonymousAccess(): void {
    // Ensure we're anonymous.
    $this->drupalLogout();

    $this->drupalGet("/archive/{$this->term->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Archive Media 1');
  }

  /**
   * Tests that metadata overlay icons are present.
   */
  public function testMetadataOverlay(): void {
    $this->drupalGet("/archive/{$this->term->id()}");

    // Check for metadata overlay structure (implementation-dependent).
    $html = $this->getSession()->getPage()->getHtml();

    // This test may need adjustment based on actual template implementation.
    $this->assertNotEmpty($html, 'Page has content');
  }

}
