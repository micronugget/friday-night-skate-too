<?php

declare(strict_types=1);

namespace Drupal\Tests\skating_video_uploader\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the YouTube settings form and OAuth route accessibility.
 *
 * The live OAuth flow and actual YouTube upload require real Google API
 * credentials and must be verified manually per the acceptance criteria
 * in Issue #15.
 *
 * @group skating_video_uploader
 * @group youtube_settings
 */
class YouTubeSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'user',
    'skating_video_uploader',
  ];

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * An unprivileged user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $regularUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
    ]);

    $this->regularUser = $this->drupalCreateUser([
      'access content',
    ]);
  }

  /**
   * Tests that the settings form is accessible to admins.
   */
  public function testSettingsFormAccessibleToAdmin(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/media/skating-video-uploader');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('YouTube API Settings');
  }

  /**
   * Tests that the settings form is denied to unprivileged users.
   */
  public function testSettingsFormDeniedToRegularUser(): void {
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('/admin/config/media/skating-video-uploader');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the settings form is denied to anonymous users.
   */
  public function testSettingsFormDeniedToAnonymous(): void {
    $this->drupalGet('/admin/config/media/skating-video-uploader');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the settings form renders expected fields.
   */
  public function testSettingsFormFields(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/media/skating-video-uploader');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('youtube_client_id');
    $this->assertSession()->fieldExists('youtube_client_secret');
    $this->assertSession()->fieldExists('youtube_redirect_uri');
    $this->assertSession()->fieldExists('metadata_consent_text');
  }

  /**
   * Tests that the settings form saves values correctly.
   */
  public function testSettingsFormSavesValues(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/media/skating-video-uploader');

    $edit = [
      'youtube_client_id' => 'test-client-id-12345',
      'youtube_client_secret' => 'test-client-secret-abcde',
      'youtube_redirect_uri' => 'https://example.com/admin/config/media/skating-video-uploader/youtube/oauth-callback',
      'metadata_consent_text' => 'I consent to upload this video.',
    ];
    $this->submitForm($edit, 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('skating_video_uploader.settings');
    $this->assertEquals('test-client-id-12345', $config->get('youtube_client_id'));
    $this->assertEquals('test-client-secret-abcde', $config->get('youtube_client_secret'));
    $this->assertEquals(
      'https://example.com/admin/config/media/skating-video-uploader/youtube/oauth-callback',
      $config->get('youtube_redirect_uri')
    );
    $this->assertEquals('I consent to upload this video.', $config->get('metadata_consent_text'));
  }

  /**
   * Tests that the OAuth callback route exists and requires admin permission.
   */
  public function testOauthCallbackRouteRequiresAdmin(): void {
    // Anonymous access should be denied.
    $this->drupalGet('/admin/config/media/skating-video-uploader/youtube/oauth-callback');
    $this->assertSession()->statusCodeEquals(403);

    // Regular user should be denied.
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('/admin/config/media/skating-video-uploader/youtube/oauth-callback');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the authenticate button is disabled when credentials are empty.
   */
  public function testAuthenticateButtonDisabledWithoutCredentials(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/media/skating-video-uploader');

    // With no credentials saved, the authenticate button should be disabled.
    $button = $this->getSession()->getPage()->findButton('Authenticate with YouTube');
    $this->assertNotNull($button, 'Authenticate with YouTube button exists.');
    $this->assertNotEmpty($button->getAttribute('disabled'), 'Authenticate button is disabled when credentials are not set.');
  }

  /**
   * Tests that the authenticate button is enabled after credentials are saved.
   */
  public function testAuthenticateButtonEnabledWithCredentials(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/media/skating-video-uploader');

    $edit = [
      'youtube_client_id' => 'test-client-id-12345',
      'youtube_client_secret' => 'test-client-secret-abcde',
      'youtube_redirect_uri' => 'https://example.com/admin/config/media/skating-video-uploader/youtube/oauth-callback',
      'metadata_consent_text' => 'I consent.',
    ];
    $this->submitForm($edit, 'Save configuration');

    // Reload the form — button should now be enabled.
    $this->drupalGet('/admin/config/media/skating-video-uploader');
    $button = $this->getSession()->getPage()->findButton('Authenticate with YouTube');
    $this->assertNotNull($button, 'Authenticate with YouTube button exists.');
    $this->assertNull($button->getAttribute('disabled'), 'Authenticate button is enabled when credentials are set.');
  }

}
