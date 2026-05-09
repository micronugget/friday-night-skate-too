<?php

declare(strict_types=1);

namespace Drupal\fns_migrate\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\fns_migrate\Service\FnsHttpClient;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Source plugin for legacy fridaynightskate.com `/routes/collections` page.
 *
 * Scrapes the collections index at `/routes/collections`, discovers each
 * collection's absolute URL, then fetches each detail page to extract the
 * `<meta name="description">` value. Yields one row per collection:
 * `{id, slug, url, name, description}`.
 *
 * HTTP traffic is delegated to the shared {@see FnsHttpClient} so every
 * response is cached on disk and transient errors are retried.
 *
 * Configuration keys (all optional):
 *   base_url:      Origin of the legacy site. Default
 *                  'https://fridaynightskate.com'.
 *   index_path:    Path of the collections index. Default
 *                  '/routes/collections'.
 *
 * @MigrateSource(
 *   id = "fns_route_collections_html",
 *   source_module = "fns_migrate"
 * )
 */
class FnsRouteCollectionsHtml extends SourcePluginBase implements ContainerFactoryPluginInterface {

  protected const DEFAULT_BASE_URL = 'https://fridaynightskate.com';
  protected const DEFAULT_INDEX_PATH = '/routes/collections';

  /**
   * Resolved base URL (no trailing slash).
   */
  protected string $baseUrl;

  /**
   * Resolved index path (leading slash).
   */
  protected string $indexPath;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    MigrationInterface $migration,
    protected FnsHttpClient $httpClient,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->baseUrl = rtrim((string) ($configuration['base_url'] ?? self::DEFAULT_BASE_URL), '/');
    $this->indexPath = '/' . ltrim((string) ($configuration['index_path'] ?? self::DEFAULT_INDEX_PATH), '/');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('fns_migrate.http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'fns_route_collections_html:' . $this->baseUrl . $this->indexPath;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id'          => $this->t('Stable identifier (slug).'),
      'slug'        => $this->t('URL slug for the collection.'),
      'url'         => $this->t('Absolute URL of the legacy collection page.'),
      'name'        => $this->t('Collection name (h1 on detail page).'),
      'description' => $this->t('Collection description (meta description on detail page).'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type'       => 'string',
        'max_length' => 191,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Iterator {
    $indexUrl = $this->baseUrl . $this->indexPath;
    $indexHtml = $this->httpClient->fetch($indexUrl, 'route_collections', 'index');
    $collectionUrls = $this->extractCollectionUrls($indexHtml);

    foreach ($collectionUrls as $url) {
      $slug = $this->slugFromUrl($url);
      if ($slug === '') {
        continue;
      }
      $detailHtml = $this->httpClient->fetch($url, 'route_collections', $slug);
      $row = $this->parseDetail($url, $slug, $detailHtml);
      if ($row !== NULL) {
        yield $row;
      }
    }
  }

  /**
   * Extract absolute collection URLs from the index page.
   *
   * @return string[]
   *   Absolute URLs in document order, deduplicated.
   */
  protected function extractCollectionUrls(string $html): array {
    $crawler = new Crawler($html, $this->baseUrl . '/');
    $prefix = $this->baseUrl . $this->indexPath . '/';
    $urls = [];
    $crawler->filter('a[href]')->each(function (Crawler $node) use (&$urls, $prefix): void {
      $href = trim((string) $node->attr('href'));
      // Absolutize protocol-relative or root-relative hrefs.
      if (str_starts_with($href, '//')) {
        $href = 'https:' . $href;
      }
      elseif (str_starts_with($href, '/')) {
        $href = $this->baseUrl . $href;
      }
      if (str_starts_with($href, $prefix)) {
        $tail = substr(parse_url($href, PHP_URL_PATH) ?: '', strlen($this->indexPath . '/'));
        // Only single-segment slugs (no further slashes).
        if ($tail !== '' && !str_contains($tail, '/')) {
          $urls[$href] = TRUE;
        }
      }
    });
    return array_keys($urls);
  }

  /**
   * Extract the slug (final path segment) from a collection URL.
   */
  protected function slugFromUrl(string $url): string {
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
    return $segments === [] ? '' : end($segments);
  }

  /**
   * Parse a collection detail page into a row array.
   */
  protected function parseDetail(string $url, string $slug, string $html): ?array {
    $crawler = new Crawler($html, $url);

    $name = '';
    $h1 = $crawler->filter('h1');
    if ($h1->count() > 0) {
      $name = trim($h1->first()->text(''));
    }
    if ($name === '') {
      return NULL;
    }

    $description = '';
    $meta = $crawler->filter('meta[name="description"]');
    if ($meta->count() > 0) {
      $description = html_entity_decode(trim((string) $meta->first()->attr('content')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return [
      'id'          => $slug,
      'slug'        => $slug,
      'url'         => $url,
      'name'        => $name,
      'description' => $description,
    ];
  }

}
