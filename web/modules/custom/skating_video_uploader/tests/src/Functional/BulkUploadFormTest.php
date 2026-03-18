<?php

declare(strict_types=1);

namespace Drupal\Tests\skating_video_uploader\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests the bulk upload form.
 *
 * @group skating_video_uploader
 * @group upload_form
 */
class BulkUploadFormTest extends BrowserTestBase {

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
    'file',
    'taxonomy',
    'user',
    'skating_video_uploader',
    'fns_archive',
  ];

  /**
   * A user with permissions to upload media.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $uploaderUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a user with upload permissions.
    $this->uploaderUser = $this->drupalCreateUser([
      'create archive_media content',
      'access content',
    ]);
  }

  /**
   * Test that the upload form is accessible.
   */
  public function testFormAccess(): void {
    // Anonymous users should not have access.
    $this->drupalGet('/skate/upload');
    $this->assertSession()->statusCodeEquals(403);

    // Authenticated user with permission should have access.
    $this->drupalLogin($this->uploaderUser);
    $this->drupalGet('/skate/upload');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Upload Media');
  }

  /**
   * Test step 1: File selection.
   */
  public function testStepOneFileSelection(): void {
    $this->drupalLogin($this->uploaderUser);
    $this->drupalGet('/skate/upload');

    // Check that step 1 elements are present.
    $this->assertSession()->pageTextContains('Select Files');
    $this->assertSession()->fieldExists('files[0]');
    $this->assertSession()->fieldExists('youtube_urls');
    $this->assertSession()->buttonExists('Next: Extract Metadata');
  }

  /**
   * Test step 1 validation: At least one file or URL required.
   */
  public function testStepOneValidation(): void {
    $this->drupalLogin($this->uploaderUser);
    $this->drupalGet('/skate/upload');

    // Try to submit without any files or URLs.
    $this->submitForm([], 'Next: Extract Metadata');
    $this->assertSession()->pageTextContains('Please upload at least one file or provide a YouTube URL');
  }

  /**
   * Test YouTube URL validation.
   */
  public function testYouTubeUrlValidation(): void {
    $this->drupalLogin($this->uploaderUser);
    $this->drupalGet('/skate/upload');

    // Submit with an invalid YouTube URL.
    $edit = [
      'youtube_urls' => 'https://invalid-url.com/video',
    ];
    $this->submitForm($edit, 'Next: Extract Metadata');
    $this->assertSession()->pageTextContains('Invalid YouTube URL');
  }

  /**
   * Test valid YouTube URLs.
   */
  public function testValidYouTubeUrls(): void {
    $this->drupalLogin($this->uploaderUser);
    $this->drupalGet('/skate/upload');

    $valid_urls = [
      'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      'https://youtu.be/dQw4w9WgXcQ',
      'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
    ];

    foreach ($valid_urls as $url) {
      $this->drupalGet('/skate/upload');
      $edit = [
        'youtube_urls' => $url,
      ];
      $this->submitForm($edit, 'Next: Extract Metadata');
      $this->assertSession()->pageTextNotContains('Invalid YouTube URL');
    }
  }

  /**
   * Test multi-step form progression.
   */
  public function testMultiStepProgression(): void {
    $this->drupalLogin($this->uploaderUser);

    // Create a skate date term.
    $vocabulary = Vocabulary::load('skate_dates');
    if (!$vocabulary) {
      $vocabulary = Vocabulary::create([
        'vid' => 'skate_dates',
        'name' => 'Skate Dates',
      ]);
      $vocabulary->save();
    }

    $term = Term::create([
      'vid' => 'skate_dates',
      'name' => '2025-01-29 - Test Skate',
    ]);
    $term->save();

    // Start at step 1.
    $this->drupalGet('/skate/upload');
    $this->assertSession()->pageTextContains('Select Files');

    // Submit step 1 with YouTube URL.
    $edit = [
      'youtube_urls' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ];
    $this->submitForm($edit, 'Next: Extract Metadata');

    // Should now be on step 2.
    $this->assertSession()->pageTextContains('Extracting metadata');

    // Continue to step 3.
    $this->submitForm([], 'Next: Assign Date');
    $this->assertSession()->pageTextContains('Assign a skate date');
    $this->assertSession()->fieldExists('skate_date');
    $this->assertSession()->fieldExists('attribution');
  }

  /**
   * Test step 3 validation: Skate date required.
   */
  public function testStepThreeValidation(): void {
    $this->drupalLogin($this->uploaderUser);

    // Navigate through steps.
    $this->drupalGet('/skate/upload');
    $this->submitForm(['youtube_urls' => 'https://youtu.be/test123test'], 'Next: Extract Metadata');
    $this->submitForm([], 'Next: Assign Date');

    // Try to proceed without skate date.
    $this->submitForm([], 'Next: Review');
    $this->assertSession()->pageTextContains('Please select or create a skate date');
  }

  /**
   * Test back button functionality.
   */
  public function testBackButton(): void {
    $this->drupalLogin($this->uploaderUser);

    // Navigate to step 2.
    $this->drupalGet('/skate/upload');
    $this->submitForm(['youtube_urls' => 'https://youtu.be/test'], 'Next: Extract Metadata');
    $this->assertSession()->pageTextContains('Extracting metadata');

    // Click back button.
    $this->submitForm([], '← Back');
    $this->assertSession()->pageTextContains('Select Files');
    $this->assertSession()->fieldExists('youtube_urls');
  }

  /**
   * Test progress indicator display.
   */
  public function testProgressIndicator(): void {
    $this->drupalLogin($this->uploaderUser);
    $this->drupalGet('/skate/upload');

    // Check that all steps are displayed in progress indicator.
    $this->assertSession()->pageTextContains('Select Files');
    $this->assertSession()->pageTextContains('Extract Metadata');
    $this->assertSession()->pageTextContains('Assign Date');
    $this->assertSession()->pageTextContains('Review & Submit');
  }

}
