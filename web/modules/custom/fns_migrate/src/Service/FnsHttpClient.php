<?php

declare(strict_types=1);

namespace Drupal\fns_migrate\Service;

use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTP client wrapper for the Friday Night Skate legacy-site scraper.
 *
 * Wraps the core http_client with three behaviours required by the migration
 * source plugins (issue #M-5):
 *
 * - On-disk caching keyed by a caller-supplied cache key (typically the slug).
 *   Bodies are persisted under `private://fns_migrate_cache/{group}/{key}.html`
 *   so re-runs of the migration are deterministic and friendly to the source
 *   server.
 * - Exponential backoff on 429 / 5xx responses (and transient network
 *   failures), capped at a configurable retry count.
 * - Structured logging through `logger.channel.fns_migrate` for every cache
 *   miss, retry, and final failure.
 *
 * Honours the politeness policy declared in the issue: a fixed
 * `User-Agent: FridayNightSkate-Migrate/1.0` and a 1-second crawl delay
 * between cache-miss requests.
 */
class FnsHttpClient {

  /**
   * The User-Agent advertised by every outbound request.
   */
  public const USER_AGENT = 'FridayNightSkate-Migrate/1.0';

  /**
   * Crawl delay (seconds) applied between cache-miss requests.
   */
  public const CRAWL_DELAY_SECONDS = 1;

  /**
   * Maximum retry attempts for transient errors.
   */
  public const MAX_RETRIES = 4;

  /**
   * Base for exponential backoff (seconds): 1, 2, 4, 8 ….
   */
  public const BACKOFF_BASE_SECONDS = 1;

  /**
   * Root directory inside the private filesystem for cached responses.
   */
  public const CACHE_ROOT = 'private://fns_migrate_cache';

  /**
   * Sleep callback (seconds => void). Overridable in tests.
   *
   * @var callable
   */
  protected $sleep;

  /**
   * Timestamp (float seconds) of the last cache-miss network request.
   */
  protected float $lastNetworkRequestAt = 0.0;

  public function __construct(
    protected ClientInterface $httpClient,
    protected FileSystemInterface $fileSystem,
    protected LoggerInterface $logger,
  ) {
    $this->sleep = static function (int $seconds): void {
      if ($seconds > 0) {
        sleep($seconds);
      }
    };
  }

  /**
   * Override the sleep callback. Intended for tests.
   *
   * @param callable $sleep
   *   A callable accepting an int number of seconds.
   */
  public function setSleep(callable $sleep): void {
    $this->sleep = $sleep;
  }

  /**
   * Fetch a URL, returning the response body as a string.
   *
   * @param string $url
   *   Absolute URL to fetch.
   * @param string $group
   *   Cache subdirectory (e.g. "routes", "skates"). Used to namespace cached
   *   bodies under `private://fns_migrate_cache/{group}/`.
   * @param string $cacheKey
   *   Stable, filesystem-safe identifier (typically the page slug).
   *
   * @return string
   *   The HTML/body returned by the server (or replayed from cache).
   *
   * @throws \RuntimeException
   *   When the request fails after all retries.
   */
  public function fetch(string $url, string $group, string $cacheKey): string {
    $cachePath = $this->cachePath($group, $cacheKey);
    $cached = $this->readCache($cachePath);
    if ($cached !== NULL) {
      $this->logger->debug('Cache hit @url -> @path', [
        '@url' => $url,
        '@path' => $cachePath,
      ]);
      return $cached;
    }

    $this->throttle();
    $body = $this->requestWithRetries($url);
    $this->writeCache($cachePath, $body);
    $this->lastNetworkRequestAt = microtime(TRUE);

    return $body;
  }

  /**
   * Build the canonical cache path for a given group/key pair.
   */
  protected function cachePath(string $group, string $cacheKey): string {
    $safeGroup = preg_replace('/[^a-z0-9_\-]/i', '_', $group);
    $safeKey = preg_replace('/[^a-z0-9_\-]/i', '_', $cacheKey);
    return sprintf('%s/%s/%s.html', self::CACHE_ROOT, $safeGroup, $safeKey);
  }

  /**
   * Return cached body or NULL if missing.
   */
  protected function readCache(string $path): ?string {
    $real = $this->fileSystem->realpath($path);
    if ($real !== FALSE && $real !== '' && is_file($real)) {
      $contents = @file_get_contents($real);
      if ($contents !== FALSE) {
        return $contents;
      }
    }
    return NULL;
  }

  /**
   * Persist a body to disk, creating parent directories as needed.
   */
  protected function writeCache(string $path, string $body): void {
    $dir = dirname($path);
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($body, $path, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * Sleep enough to keep at least Crawl-delay between network requests.
   */
  protected function throttle(): void {
    if ($this->lastNetworkRequestAt <= 0.0) {
      return;
    }
    $elapsed = microtime(TRUE) - $this->lastNetworkRequestAt;
    $remaining = self::CRAWL_DELAY_SECONDS - $elapsed;
    if ($remaining > 0) {
      ($this->sleep)((int) ceil($remaining));
    }
  }

  /**
   * Issue a GET request with exponential backoff on 429 / 5xx / network errors.
   *
   * @return string
   *   The response body.
   *
   * @throws \RuntimeException
   *   When all retries are exhausted.
   */
  protected function requestWithRetries(string $url): string {
    $attempt = 0;
    $lastException = NULL;
    while ($attempt <= self::MAX_RETRIES) {
      try {
        $response = $this->httpClient->request('GET', $url, [
          'headers' => [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml',
          ],
          'http_errors' => TRUE,
          'connect_timeout' => 10,
          'timeout' => 30,
        ]);
        return (string) $response->getBody();
      }
      catch (BadResponseException $e) {
        $lastException = $e;
        $status = $e->getResponse()?->getStatusCode() ?? 0;
        if (!$this->isRetryable($status) || $attempt === self::MAX_RETRIES) {
          $this->logger->error('Giving up on @url after @n attempts: HTTP @status', [
            '@url' => $url,
            '@n' => $attempt + 1,
            '@status' => $status,
          ]);
          break;
        }
        $this->logRetry($url, $attempt, $status, $e->getResponse());
      }
      catch (ConnectException | GuzzleException $e) {
        $lastException = $e;
        if ($attempt === self::MAX_RETRIES) {
          $this->logger->error('Giving up on @url after @n attempts: @msg', [
            '@url' => $url,
            '@n' => $attempt + 1,
            '@msg' => $e->getMessage(),
          ]);
          break;
        }
        $this->logRetry($url, $attempt, 0, NULL);
      }

      ($this->sleep)($this->backoffSeconds($attempt));
      $attempt++;
    }

    throw new \RuntimeException(sprintf(
      'Failed to fetch %s after %d attempts: %s',
      $url,
      $attempt + 1,
      $lastException?->getMessage() ?? 'unknown error',
    ), 0, $lastException);
  }

  /**
   * True for HTTP statuses we should retry (429, any 5xx).
   */
  protected function isRetryable(int $status): bool {
    return $status === 429 || ($status >= 500 && $status < 600);
  }

  /**
   * Compute backoff delay for a given zero-based attempt index.
   */
  protected function backoffSeconds(int $attempt): int {
    return self::BACKOFF_BASE_SECONDS * (2 ** $attempt);
  }

  /**
   * Log a single retry, honouring Retry-After when present.
   */
  protected function logRetry(string $url, int $attempt, int $status, ?ResponseInterface $response): void {
    $retryAfter = $response?->getHeaderLine('Retry-After') ?: NULL;
    $this->logger->warning('Retrying @url (attempt @n, status @status, retry-after=@ra)', [
      '@url' => $url,
      '@n' => $attempt + 1,
      '@status' => $status ?: 'network',
      '@ra' => $retryAfter ?: 'n/a',
    ]);
  }

}
