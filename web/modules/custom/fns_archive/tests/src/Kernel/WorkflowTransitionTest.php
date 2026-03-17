<?php

declare(strict_types=1);

namespace Drupal\Tests\fns_archive\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests workflow transitions for archive media content.
 *
 * @group fns_archive
 * @group moderation
 */
class WorkflowTransitionTest extends KernelTestBase {

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
    'workflows',
    'content_moderation',
    'fns_archive',
  ];

  /**
   * Test user with skater role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $skater;

  /**
   * Test user with moderator role.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $moderator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'system']);
    $this->installConfig(['workflows', 'content_moderation']);
    $this->installConfig(['fns_archive']);

    // Create content type if not exists.
    if (!NodeType::load('archive_media')) {
      NodeType::create([
        'type' => 'archive_media',
        'name' => 'Archive Media',
      ])->save();
    }

    // Create roles.
    if (!Role::load('skater')) {
      Role::create(['id' => 'skater', 'label' => 'Skater'])->save();
    }
    if (!Role::load('moderator')) {
      Role::create(['id' => 'moderator', 'label' => 'Moderator'])->save();
    }

    // Create test users.
    $this->skater = User::create([
      'name' => 'test_skater',
      'mail' => 'skater@example.com',
      'status' => 1,
      'roles' => ['skater'],
    ]);
    $this->skater->save();

    $this->moderator = User::create([
      'name' => 'test_moderator',
      'mail' => 'moderator@example.com',
      'status' => 1,
      'roles' => ['moderator'],
    ]);
    $this->moderator->save();
  }

  /**
   * Tests that new content starts in draft state.
   */
  public function testNewContentStartsInDraft(): void {
    $node = Node::create([
      'type' => 'archive_media',
      'title' => 'Test Content',
      'uid' => $this->skater->id(),
    ]);
    $node->save();

    $this->assertEquals('draft', $node->moderation_state->value);
  }

  /**
   * Tests transition from draft to review.
   */
  public function testDraftToReviewTransition(): void {
    $node = Node::create([
      'type' => 'archive_media',
      'title' => 'Test Content',
      'uid' => $this->skater->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node->moderation_state = 'review';
    $node->save();

    $this->assertEquals('review', $node->moderation_state->value);
  }

  /**
   * Tests transition from review to published.
   */
  public function testReviewToPublishedTransition(): void {
    $node = Node::create([
      'type' => 'archive_media',
      'title' => 'Test Content',
      'uid' => $this->skater->id(),
      'moderation_state' => 'review',
    ]);
    $node->save();

    $this->container->get('current_user')->setAccount($this->moderator);

    $node->moderation_state = 'published';
    $node->save();

    $this->assertEquals('published', $node->moderation_state->value);
    $this->assertTrue($node->isPublished());
  }

  /**
   * Tests transition from review back to draft.
   */
  public function testReviewToDraftTransition(): void {
    $node = Node::create([
      'type' => 'archive_media',
      'title' => 'Test Content',
      'uid' => $this->skater->id(),
      'moderation_state' => 'review',
    ]);
    $node->save();

    $this->container->get('current_user')->setAccount($this->moderator);

    $node->moderation_state = 'draft';
    $node->revision_log = 'Needs better images';
    $node->save();

    $this->assertEquals('draft', $node->moderation_state->value);
    $this->assertFalse($node->isPublished());
  }

  /**
   * Tests transition from published to archived.
   */
  public function testPublishedToArchivedTransition(): void {
    $node = Node::create([
      'type' => 'archive_media',
      'title' => 'Test Content',
      'uid' => $this->skater->id(),
      'moderation_state' => 'published',
      'status' => 1,
    ]);
    $node->save();

    $this->container->get('current_user')->setAccount($this->moderator);

    $node->moderation_state = 'archived';
    $node->save();

    $this->assertEquals('archived', $node->moderation_state->value);
    $this->assertFalse($node->isPublished());
  }

  /**
   * Tests restoring content from archived to published.
   */
  public function testArchivedToPublishedRestore(): void {
    $node = Node::create([
      'type' => 'archive_media',
      'title' => 'Test Content',
      'uid' => $this->skater->id(),
      'moderation_state' => 'archived',
    ]);
    $node->save();

    $this->container->get('current_user')->setAccount($this->moderator);

    $node->moderation_state = 'published';
    $node->save();

    $this->assertEquals('published', $node->moderation_state->value);
    $this->assertTrue($node->isPublished());
  }

  /**
   * Tests that workflow creates revisions for each state change.
   */
  public function testWorkflowCreatesRevisions(): void {
    $node = Node::create([
      'type' => 'archive_media',
      'title' => 'Test Content',
      'uid' => $this->skater->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $initial_revision_id = $node->getRevisionId();

    $node->moderation_state = 'review';
    $node->save();
    $review_revision_id = $node->getRevisionId();

    $this->container->get('current_user')->setAccount($this->moderator);
    $node->moderation_state = 'published';
    $node->save();
    $published_revision_id = $node->getRevisionId();

    $this->assertNotEquals($initial_revision_id, $review_revision_id);
    $this->assertNotEquals($review_revision_id, $published_revision_id);
    $this->assertGreaterThan($initial_revision_id, $review_revision_id);
    $this->assertGreaterThan($review_revision_id, $published_revision_id);
  }

}
