<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Tests VideoJsMedia entity forms.
 *
 * @group videojs_media
 */
class VideoJsMediaFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with create permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authorizedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create user with permissions for all bundles.
    $permissions = [
      'access content',
    ];
    foreach (['local_video', 'local_audio', 'remote_video', 'remote_audio', 'youtube'] as $bundle) {
      $permissions[] = "create {$bundle} videojs media";
      $permissions[] = "edit own {$bundle} videojs media";
      $permissions[] = "view {$bundle} videojs media";
    }

    $this->authorizedUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Tests accessing the add form for each bundle.
   *
   * @dataProvider bundleProvider
   */
  public function testAddForm(string $bundle): void {
    $this->drupalLogin($this->authorizedUser);

    // Access the add form.
    $this->drupalGet("/videojs-media/add/{$bundle}");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('name[0][value]');
    $this->assertSession()->buttonExists('Save');
  }

  /**
   * Tests creating an entity via the form.
   *
   * @dataProvider bundleProvider
   */
  public function testCreateEntityViaForm(string $bundle): void {
    $this->drupalLogin($this->authorizedUser);

    // Submit the add form.
    $this->drupalGet("/videojs-media/add/{$bundle}");
    $this->submitForm([
      'name[0][value]' => "Test {$bundle} via form",
    ], 'Save');

    // Should redirect and show message.
    $this->assertSession()->pageTextContains("Test {$bundle} via form");
  }

  /**
   * Tests accessing the edit form.
   */
  public function testEditForm(): void {
    // Create entity.
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Original Name',
      'status' => TRUE,
      'uid' => $this->authorizedUser->id(),
    ]);
    $entity->save();

    $this->drupalLogin($this->authorizedUser);

    // Access edit form.
    $this->drupalGet("/videojs-media/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('name[0][value]', 'Original Name');
  }

  /**
   * Tests updating an entity via the form.
   */
  public function testUpdateEntityViaForm(): void {
    // Create entity.
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'Original Name',
      'status' => TRUE,
      'uid' => $this->authorizedUser->id(),
    ]);
    $entity->save();

    $this->drupalLogin($this->authorizedUser);

    // Update via form.
    $this->drupalGet("/videojs-media/{$entity->id()}/edit");
    $this->submitForm([
      'name[0][value]' => 'Updated Name',
    ], 'Save');

    $this->assertSession()->pageTextContains('Updated Name');

    // Verify in database.
    $updated_entity = VideoJsMedia::load($entity->id());
    $this->assertEquals('Updated Name', $updated_entity->getName());
  }

  /**
   * Tests form validation for required fields.
   */
  public function testFormValidation(): void {
    $this->drupalLogin($this->authorizedUser);

    // Submit form without required name field.
    $this->drupalGet('/videojs-media/add/local_video');
    $this->submitForm([], 'Save');

    // Should show validation error.
    $this->assertSession()->pageTextContains('field is required');
  }

  /**
   * Tests delete form.
   */
  public function testDeleteForm(): void {
    // Create entity.
    $entity = VideoJsMedia::create([
      'type' => 'local_video',
      'name' => 'To Be Deleted',
      'status' => TRUE,
      'uid' => $this->authorizedUser->id(),
    ]);
    $entity->save();
    $id = $entity->id();

    // Grant delete permission.
    $user = $this->drupalCreateUser([
      'delete own local_video videojs media',
      'access content',
    ]);
    $entity->setOwnerId($user->id());
    $entity->save();

    $this->drupalLogin($user);

    // Access delete form.
    $this->drupalGet("/videojs-media/{$id}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to delete');

    // Confirm deletion.
    $this->submitForm([], 'Delete');

    // Verify entity is deleted.
    $deleted_entity = VideoJsMedia::load($id);
    $this->assertNull($deleted_entity);
  }

  /**
   * Tests access denied for users without permission.
   */
  public function testFormAccessDenied(): void {
    $regular_user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($regular_user);

    // Try to access add form without permission.
    $this->drupalGet('/videojs-media/add/local_video');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Data provider for bundle types.
   */
  public static function bundleProvider(): array {
    return [
      'local_video' => ['local_video'],
      'local_audio' => ['local_audio'],
      'remote_video' => ['remote_video'],
      'remote_audio' => ['remote_audio'],
      'youtube' => ['youtube'],
    ];
  }

}
