<?php

declare(strict_types=1);

namespace Drupal\Tests\videojs_media\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the VideoJS Media admin interface.
 *
 * Covers: collection page, type list, add page, add/edit/delete forms,
 * canonical entity page, and authentication enforcement.
 *
 * @group videojs_media
 */
class VideoJsMediaAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'videojs_media',
    'block',
    'field_ui',
    'sdc',
    'file',
    'image',
    'text',
    'options',
    'file_upload_secure_validator',
  ];

  /**
   * An administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer videojs media',
      'administer videojs media types',
      'access administration pages',
      'access content',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the VideoJS Media content collection page.
   */
  public function testCollectionPageLoads(): void {
    $this->drupalGet('/admin/content/videojs-media');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the collection page is inaccessible to anonymous users.
   */
  public function testCollectionPageRequiresAuthentication(): void {
    $this->drupalLogout();
    $this->drupalGet('/admin/content/videojs-media');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the VideoJS Media type overview page lists all default bundles.
   */
  public function testTypeListPageLoads(): void {
    $this->drupalGet('/admin/structure/videojs-media/types');
    $this->assertSession()->statusCodeEquals(200);

    foreach (['Local Video', 'Local Audio', 'Remote Video', 'Remote Audio', 'YouTube'] as $label) {
      $this->assertSession()->pageTextContains($label);
    }
  }

  /**
   * Tests the add-media page lists all available bundle types as links.
   */
  public function testAddPageListsBundles(): void {
    $this->drupalGet('/videojs-media/add');
    $this->assertSession()->statusCodeEquals(200);

    foreach (['Local Video', 'Local Audio', 'Remote Video', 'Remote Audio', 'YouTube'] as $label) {
      $this->assertSession()->linkExists($label);
    }
  }

  /**
   * Tests that each bundle's add form loads and shows a name field.
   *
   * @dataProvider bundleProvider
   */
  public function testAddFormLoads(string $bundle): void {
    $this->drupalGet("/videojs-media/add/$bundle");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('name[0][value]');
  }

  /**
   * Tests the remote_video bundle's add form shows the remote URL field.
   */
  public function testRemoteVideoAddFormHasUrlField(): void {
    $this->drupalGet('/videojs-media/add/remote_video');
    $this->assertSession()->fieldExists('field_remote_url[0][value]');
  }

  /**
   * Tests the youtube bundle's add form shows the YouTube URL field.
   */
  public function testYoutubeAddFormHasYoutubeUrlField(): void {
    $this->drupalGet('/videojs-media/add/youtube');
    $this->assertSession()->fieldExists('field_youtube_url[0][value]');
  }

  /**
   * Tests creating a remote_video entity via the add form.
   */
  public function testCreateRemoteVideoEntity(): void {
    $this->drupalGet('/videojs-media/add/remote_video');
    $this->submitForm([
      'name[0][value]' => 'My Remote Video',
      'field_remote_url[0][value]' => 'https://example.com/video.mp4',
    ], 'Save');
    $this->assertSession()->pageTextContains('New VideoJS media My Remote Video has been created.');
  }

  /**
   * Tests creating a remote_audio entity via the add form.
   */
  public function testCreateRemoteAudioEntity(): void {
    $this->drupalGet('/videojs-media/add/remote_audio');
    $this->submitForm([
      'name[0][value]' => 'My Podcast',
      'field_remote_url[0][value]' => 'https://example.com/podcast.mp3',
    ], 'Save');
    $this->assertSession()->pageTextContains('New VideoJS media My Podcast has been created.');
  }

  /**
   * Tests creating a youtube entity via the add form.
   */
  public function testCreateYoutubeEntity(): void {
    $this->drupalGet('/videojs-media/add/youtube');
    $this->submitForm([
      'name[0][value]' => 'My YouTube Video',
      'field_youtube_url[0][value]' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ], 'Save');
    $this->assertSession()->pageTextContains('New VideoJS media My YouTube Video has been created.');
  }

  /**
   * Tests editing an existing entity navigates to the edit form correctly.
   */
  public function testEditFormLoads(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Original Title',
        'field_remote_url' => 'https://example.com/video.mp4',
        'status' => 1,
      ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('name[0][value]', 'Original Title');
  }

  /**
   * Tests that submitting the edit form saves the updated name.
   */
  public function testEditFormSavesChanges(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Original',
        'field_remote_url' => 'https://example.com/video.mp4',
        'status' => 1,
      ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}/edit");
    $this->submitForm(['name[0][value]' => 'Renamed'], 'Save');
    $this->assertSession()->pageTextContains('has been updated.');
  }

  /**
   * Tests that the canonical entity page renders without error.
   */
  public function testCanonicalPageLoads(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'remote_video',
        'name' => 'Canonical Test',
        'field_remote_url' => 'https://example.com/video.mp4',
        'status' => 1,
      ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the entity delete form and confirms the success message.
   */
  public function testDeleteForm(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('videojs_media')
      ->create([
        'type' => 'youtube',
        'name' => 'Delete Me',
        'field_youtube_url' => 'https://www.youtube.com/watch?v=test',
        'status' => 1,
      ]);
    $entity->save();

    $this->drupalGet("/videojs-media/{$entity->id()}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('has been deleted.');
  }

  /**
   * Data provider returning all five bundle machine names.
   *
   * @return array
   *   Data sets keyed by bundle name.
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
