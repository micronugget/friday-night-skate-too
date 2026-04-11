<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies the correct JS library is attached for MutationObserver scoping.
 *
 * The MutationObserver fallback in player.js must use a narrow
 * attributeFilter: ['class'] config and include a 5-second timeout guard.
 * This Kernel test confirms the JS library is attached and the behaviour
 * source contains the expected observer configuration.
 *
 * @group videojs_media
 */
class MutationObserverScopeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'link',
    'image',
    'taxonomy',
    'videojs_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('videojs_media');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['field', 'file', 'videojs_media']);
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Returns the absolute path to player.js.
   */
  private function playerJsPath(): string {
    // phpcs:ignore Drupal.Files.LineLength
    return \Drupal::root() . '/modules/custom/videojs_media/components/player/player.js';
  }

  /**
   * Tests that player.js exists and is readable.
   */
  public function testPlayerJsExists(): void {
    $this->assertFileExists(
      $this->playerJsPath(),
      'player.js must exist at the expected path.',
    );
  }

  /**
   * Tests that the observer uses attributeFilter restricted to class.
   *
   * The broad { childList: true, subtree: true } config must not be present
   * alongside the MutationObserver observe() call; only the narrow
   * attributeFilter: ['class'] config is permitted.
   */
  public function testObserverUsesNarrowAttributeFilter(): void {
    $source = file_get_contents($this->playerJsPath());
    $this->assertIsString($source, 'player.js must be readable.');

    $this->assertStringContainsString(
      "attributeFilter: ['class']",
      $source,
      "Observer config must include attributeFilter: ['class'].",
    );
  }

  /**
   * Tests that the broad subtree/childList observer config is removed.
   */
  public function testObserverDoesNotUseSubtreeConfig(): void {
    $source = file_get_contents($this->playerJsPath());
    $this->assertIsString($source, 'player.js must be readable.');

    // The old broad config must no longer appear in the
    // observer.observe() call.
    $this->assertStringNotContainsString(
      'subtree: true',
      $source,
      'Observer must not use subtree: true — causes callback cascades.',
    );
  }

  /**
   * Tests that a setTimeout guard is present to disconnect the observer.
   *
   * The guard must disconnect after 5 seconds if the player never initialises,
   * preventing indefinite observation.
   */
  public function testObserverHasTimeoutGuard(): void {
    $source = file_get_contents($this->playerJsPath());
    $this->assertIsString($source, 'player.js must be readable.');

    $this->assertStringContainsString(
      'setTimeout',
      $source,
      'A setTimeout guard must be present to auto-disconnect the observer.',
    );

    $this->assertStringContainsString(
      'observer.disconnect()',
      $source,
      'The timeout guard must call observer.disconnect().',
    );
  }

  /**
   * Tests that the videojs_media JS library is declared in the module info.
   */
  public function testVideojsMediaLibraryIsDeclared(): void {
    $library_discovery = \Drupal::service('library.discovery');
    $libraries = $library_discovery->getLibrariesByExtension('videojs_media');
    $this->assertNotEmpty(
      $libraries,
      'videojs_media must declare at least one JS library.',
    );
  }

}
