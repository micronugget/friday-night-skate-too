<?php

declare(strict_types=1);

namespace Drupal\skating_video_uploader\Form;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure YouTube API settings for the Skating Video Uploader module.
 */
class YouTubeSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'skating_video_uploader_youtube_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['skating_video_uploader.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('skating_video_uploader.settings');

    $form['youtube_api'] = [
      '#type' => 'details',
      '#title' => $this->t('YouTube API Settings'),
      '#open' => TRUE,
      '#description' => $this->t('Configure the YouTube API credentials for video uploads. You need to create a project in the <a href="@google_console" target="_blank">Google Cloud Console</a> and enable the YouTube Data API v3.', [
        '@google_console' => 'https://console.cloud.google.com/',
      ]),
    ];

    $form['youtube_api']['youtube_client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('The client ID from your Google Cloud Console project.'),
      '#default_value' => $config->get('youtube_client_id'),
      '#required' => TRUE,
    ];

    $form['youtube_api']['youtube_client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('The client secret from your Google Cloud Console project.'),
      '#default_value' => $config->get('youtube_client_secret'),
      '#required' => TRUE,
    ];

    $form['youtube_api']['youtube_redirect_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect URI'),
      '#description' => $this->t('The redirect URI for OAuth authentication. This should be set to your site URL followed by "/admin/config/media/skating-video-uploader/youtube/oauth-callback".'),
      '#default_value' => $config->get('youtube_redirect_uri') ?: $this->getDefaultRedirectUri(),
      '#required' => TRUE,
    ];

    $form['youtube_api']['youtube_access_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('The OAuth access token. This will be automatically populated after authentication.'),
      '#default_value' => $config->get('youtube_access_token'),
      '#disabled' => TRUE,
    ];

    $form['youtube_api']['youtube_refresh_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Refresh Token'),
      '#description' => $this->t('The OAuth refresh token. This will be automatically populated after authentication.'),
      '#default_value' => $config->get('youtube_refresh_token'),
      '#disabled' => TRUE,
    ];

    $form['youtube_api']['authenticate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Authenticate with YouTube'),
      '#submit' => ['::authenticateWithYouTube'],
      '#disabled' => empty($config->get('youtube_client_id')) || empty($config->get('youtube_client_secret')),
    ];

    $form['metadata'] = [
      '#type' => 'details',
      '#title' => $this->t('Metadata Settings'),
      '#open' => TRUE,
    ];

    $form['metadata']['metadata_consent_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Consent Text'),
      '#description' => $this->t('The text to display for the consent checkbox when uploading videos.'),
      '#default_value' => $config->get('metadata_consent_text') ?: $this->getDefaultConsentText(),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('skating_video_uploader.settings')
      ->set('youtube_client_id', $form_state->getValue('youtube_client_id'))
      ->set('youtube_client_secret', $form_state->getValue('youtube_client_secret'))
      ->set('youtube_redirect_uri', $form_state->getValue('youtube_redirect_uri'))
      ->set('metadata_consent_text', $form_state->getValue('metadata_consent_text'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for the authenticate button.
   */
  public function authenticateWithYouTube(array &$form, FormStateInterface $form_state) {
    // Save the form values first.
    $this->config('skating_video_uploader.settings')
      ->set('youtube_client_id', $form_state->getValue('youtube_client_id'))
      ->set('youtube_client_secret', $form_state->getValue('youtube_client_secret'))
      ->set('youtube_redirect_uri', $form_state->getValue('youtube_redirect_uri'))
      ->save();

    // Create a Google client for authentication.
    $client = new \Google_Client();
    $client->setApplicationName('Friday Night Skate Club Video Uploader');
    $client->setClientId($form_state->getValue('youtube_client_id'));
    $client->setClientSecret($form_state->getValue('youtube_client_secret'));
    $client->setRedirectUri($form_state->getValue('youtube_redirect_uri'));
    $client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');

    // Generate the authentication URL and redirect the user.
    $auth_url = $client->createAuthUrl();
    $response = new RedirectResponse($auth_url);
    $response->send();
    exit;
  }

  /**
   * Gets the default redirect URI.
   *
   * @return string
   *   The default redirect URI.
   */
  protected function getDefaultRedirectUri() {
    global $base_url;
    return $base_url . '/admin/config/media/skating-video-uploader/youtube/oauth-callback';
  }

  /**
   * Gets the default consent text.
   *
   * @return string
   *   The default consent text.
   */
  protected function getDefaultConsentText() {
    return 'I consent to upload this video to YouTube and allow the collection of GPS and timecode metadata. The metadata will be stored on this website and will not be shared with third parties.';
  }

}
