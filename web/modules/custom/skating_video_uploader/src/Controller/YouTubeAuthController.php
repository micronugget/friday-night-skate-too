<?php

declare(strict_types=1);

namespace Drupal\skating_video_uploader\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for handling YouTube OAuth authentication.
 */
class YouTubeAuthController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new YouTubeAuthController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    MessengerInterface $messenger,
  ) {
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('messenger')
    );
  }

  /**
   * Handles the OAuth callback from Google.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function handleOauthCallback() {
    $request = $this->requestStack->getCurrentRequest();
    $code = $request->query->get('code');

    if (empty($code)) {
      $error = $request->query->get('error');
      $this->messenger->addError($this->t('Authentication failed: @error', ['@error' => $error]));
      return new RedirectResponse(Url::fromRoute('skating_video_uploader.youtube_settings')->toString());
    }

    try {
      // Get the configuration.
      $config = $this->configFactory->get('skating_video_uploader.settings');
      $client_id = $config->get('youtube_client_id');
      $client_secret = $config->get('youtube_client_secret');
      $redirect_uri = $config->get('youtube_redirect_uri');

      if (empty($client_id) || empty($client_secret) || empty($redirect_uri)) {
        $this->messenger->addError($this->t('YouTube API credentials are not configured.'));
        return new RedirectResponse(Url::fromRoute('skating_video_uploader.youtube_settings')->toString());
      }

      // Create a Google client.
      $client = new \Google_Client();
      $client->setApplicationName('Friday Night Skate Club Video Uploader');
      $client->setClientId($client_id);
      $client->setClientSecret($client_secret);
      $client->setRedirectUri($redirect_uri);
      $client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
      $client->setAccessType('offline');

      // Exchange the authorization code for an access token.
      $token = $client->fetchAccessTokenWithAuthCode($code);

      if (isset($token['error'])) {
        $this->messenger->addError($this->t('Error fetching access token: @error', ['@error' => $token['error']]));
        return new RedirectResponse(Url::fromRoute('skating_video_uploader.youtube_settings')->toString());
      }

      // Save the access token and refresh token to configuration.
      $config = $this->configFactory->getEditable('skating_video_uploader.settings');
      $config->set('youtube_access_token', json_encode($token));
      if (isset($token['refresh_token'])) {
        $config->set('youtube_refresh_token', $token['refresh_token']);
      }
      $config->save();

      $this->messenger->addStatus($this->t('Successfully authenticated with YouTube.'));
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Error during authentication: @error', ['@error' => $e->getMessage()]));
    }

    return new RedirectResponse(Url::fromRoute('skating_video_uploader.youtube_settings')->toString());
  }

}
