<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Verifies that the player JS contains VHS pause/play suspend handlers.
 *
 * The actual suspend logic is JavaScript-only; this Kernel test validates
 * that the player library asset (player.js) includes the expected handler
 * function names, confirming the code was not accidentally removed.
 *
 * @group videojs_media
 */
class VhsSuspendHandlerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'file',
    'image',
    'options',
    'file_upload_secure_validator',
    'link',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('videojs_media');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['videojs_media']);
  }

  /**
   * Returns the absolute path to player.js.
   */
  protected function getPlayerJsPath(): string {
    $module_path = $this->container->get('module_handler')
      ->getModule('videojs_media')
      ->getPath();
    return DRUPAL_ROOT . '/' . $module_path . '/components/player/player.js';
  }

  /**
   * Tests that player.js contains the VHS pause suspend handler.
   */
  public function testPlayerJsContainsVhsPauseHandler(): void {
    $js = file_get_contents($this->getPlayerJsPath());
    $this->assertNotFalse($js, 'player.js must be readable.');
    $this->assertStringContainsString(
      'vhsSuspendHandler',
      $js,
      'player.js must define the vhsSuspendHandler for VHS pause suspension.'
    );
  }

  /**
   * Tests that player.js contains the VHS play resume handler.
   */
  public function testPlayerJsContainsVhsResumeHandler(): void {
    $js = file_get_contents($this->getPlayerJsPath());
    $this->assertNotFalse($js, 'player.js must be readable.');
    $this->assertStringContainsString(
      'vhsResumeHandler',
      $js,
      'player.js must define the vhsResumeHandler for VHS play resumption.'
    );
  }

  /**
   * Tests that the VHS guard protects non-VHS sources from errors.
   */
  public function testPlayerJsContainsVhsGuard(): void {
    $js = file_get_contents($this->getPlayerJsPath());
    $this->assertNotFalse($js, 'player.js must be readable.');
    $this->assertStringContainsString(
      'player.tech(true)',
      $js,
      'player.js must guard VHS access with player.tech(true) to protect non-VHS sources.'
    );
    $this->assertStringContainsString(
      'masterPlaylistController_',
      $js,
      'player.js must reference masterPlaylistController_ for VHS segment loader control.'
    );
  }

}
