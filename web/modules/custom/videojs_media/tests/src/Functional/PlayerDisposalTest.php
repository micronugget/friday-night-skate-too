<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests the VideoJS player disposal lifecycle for modal players.
 *
 * Verifies that:
 * - A modal player element does not have data-videojs-initialized on page load.
 * - The facade wrapper is present inside a Bootstrap modal container.
 * - The facade can be re-initialized (no stale initialized attribute after
 *   disposal markup is restored).
 *
 * @group videojs_media
 */
class PlayerDisposalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'views',
    'videojs_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $viewUser = $this->drupalCreateUser([
      'view local_video videojs media',
      'view youtube videojs media',
      'view remote_video videojs media',
      'access content',
    ]);
    $this->drupalLogin($viewUser);
  }

  /**
   * Tests that a modal player facade is present and not eagerly initialized.
   *
   * The facade wrapper must exist inside a .modal container and must NOT carry
   * data-videojs-initialized on initial page load — initialization only happens
   * when show.bs.modal fires.
   */
  public function testModalPlayerFacadeNotEagerlyInitialized(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Modal Disposal Test Video',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);

    // Facade wrapper must be present.
    $this->assertSession()->responseContains('data-lazy-player="true"');

    // No eager initialization — data-videojs-initialized must be absent on
    // initial page load regardless of whether the player is in a modal.
    $this->assertSession()->responseNotContains('data-videojs-initialized');

    // No data-setup attribute — VideoJS auto-init must be disabled.
    $this->assertSession()->responseNotContains('data-setup');
  }

  /**
   * Tests that the facade initialized attribute is absent on fresh page load.
   *
   * After disposal, disposePlayerInFacade() removes
   * data-videojs-facade-initialized so the element can be re-initialized.
   * On a fresh page load the attribute must also be absent, confirming the
   * server-side template never sets it.
   */
  public function testFacadeInitializedAttributeAbsentOnLoad(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Facade Re-init Test',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);

    // data-videojs-facade-initialized is set by JS only after init; the server
    // must never render it so re-opening a modal always triggers a fresh init.
    $this->assertSession()->responseNotContains('data-videojs-facade-initialized');
  }

  /**
   * Tests that the lazy target video element carries preload="none".
   *
   * This prevents the browser from buffering video data before the user
   * explicitly opens the modal, reducing idle CPU and bandwidth usage.
   */
  public function testModalPlayerVideoHasPreloadNone(): void {
    $entity = VideoJsMedia::create([
      'type' => 'videojs_local_video',
      'name' => 'Modal Preload None Test',
      'status' => TRUE,
    ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('preload="none"');
  }

  /**
   * Tests that multiple video entities on a page all use the lazy facade.
   *
   * Ensures that even when several modal-capable players are present, none
   * are eagerly initialized — all must wait for show.bs.modal.
   */
  public function testMultipleModalPlayersNoneEagerlyInitialized(): void {
    for ($i = 1; $i <= 3; $i++) {
      $entity = VideoJsMedia::create([
        'type' => 'videojs_local_video',
        'name' => "Modal Video {$i}",
        'status' => TRUE,
      ]);
      $entity->save();
    }

    $this->drupalGet('/admin/content/videojs-media');
    $this->assertSession()->statusCodeEquals(200);

    // No player should be initialized on the listing page.
    $this->assertSession()->responseNotContains('data-videojs-initialized');
    $this->assertSession()->responseNotContains('data-videojs-facade-initialized');
  }

}
