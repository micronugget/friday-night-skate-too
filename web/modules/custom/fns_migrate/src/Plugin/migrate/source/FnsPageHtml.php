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
 * Source plugin for legacy fridaynightskate.com `/pages` pages.
 *
 * Crawls the paginated pages index, then visits each detail page and yields a
 * row with the canonical fields used by the `page` content type migration:
 * `{id, slug, url, title, hero_image_url, body_html}`.
 *
 * HTTP traffic is delegated to the shared {@see FnsHttpClient} so every
 * response is cached on disk under `private://fns_migrate_cache/pages/` and
 * transient errors are retried with exponential backoff.
 *
 * Configuration keys (all optional, sensible defaults):
 *   base_url:    Origin of the legacy site. Default
 *                'https://fridaynightskate.com'.
 *   index_path:  Path of the paginated index. Default '/pages'.
 *   page_query:  Query parameter used for pagination. Default 'page'.
 *   first_page:  First page number. Default 1.
 *   max_pages:   Hard cap on pages to walk (safety net). Default 50.
 *
 * @MigrateSource(
 *   id = "fns_page_html",
 *   source_module = "fns_migrate"
 * )
 */
class FnsPageHtml extends SourcePluginBase implements ContainerFactoryPluginInterface {

  protected const DEFAULT_BASE_URL = 'https://fridaynightskate.com';
  protected const DEFAULT_INDEX_PATH = '/pages';
  protected const DEFAULT_PAGE_QUERY = 'page';
  protected const DEFAULT_MAX_PAGES = 50;

  /**
   * Resolved base URL (no trailing slash).
   */
  protected string $baseUrl;

  /**
   * Resolved index path (leading slash, no trailing slash unless root).
   */
  protected string $indexPath;

  /**
   * Pagination query parameter name.
   */
  protected string $pageQuery;

  /**
   * First page number to request.
   */
  protected int $firstPage;

  /**
   * Hard cap on pages to walk.
   */
  protected int $maxPages;

  /**
   * Constructs a FnsPageHtml source plugin.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   * @param \Drupal\fns_migrate\Service\FnsHttpClient $httpClient
   *   The shared HTTP client with caching and backoff.
   */
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
    $this->pageQuery = (string) ($configuration['page_query'] ?? self::DEFAULT_PAGE_QUERY);
    $this->firstPage = (int) ($configuration['first_page'] ?? 1);
    $this->maxPages = (int) ($configuration['max_pages'] ?? self::DEFAULT_MAX_PAGES);
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
    return 'fns_page_html:' . $this->baseUrl . $this->indexPath;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id' => $this->t('Stable identifier (slug).'),
      'slug' => $this->t('URL slug for the page.'),
      'url' => $this->t('Absolute URL of the legacy detail page.'),
      'title' => $this->t('Page title.'),
      'hero_image_url' => $this->t('Absolute URL of the hero image.'),
      'body_html' => $this->t('Body HTML of the page.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type' => 'string',
        'max_length' => 191,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Walks the paginated index, then yields one parsed row per detail page.
   * Every HTTP fetch goes through the cached {@see FnsHttpClient}, so a second
   * run replays disk-backed responses and never hits the network.
   */
  protected function initializeIterator(): \Iterator {
    $seen = [];
    $page = $this->firstPage;
    $walked = 0;

    while ($walked < $this->maxPages) {
      $indexUrl = $this->buildIndexUrl($page);
      $cacheKey = 'index-' . $page;
      $html = $this->httpClient->fetch($indexUrl, 'pages', $cacheKey);
      $links = $this->extractPageLinks($html);
      if ($links === []) {
        break;
      }

      $newOnPage = 0;
      foreach ($links as $detailUrl) {
        $slug = $this->slugFromUrl($detailUrl);
        if ($slug === '' || isset($seen[$slug])) {
          continue;
        }
        $seen[$slug] = TRUE;
        $newOnPage++;

        $detailHtml = $this->httpClient->fetch($detailUrl, 'pages', $slug);
        $row = $this->parseDetail($detailUrl, $slug, $detailHtml);
        if ($row !== NULL) {
          yield $row;
        }
      }

      if ($newOnPage === 0) {
        break;
      }

      $page++;
      $walked++;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount(): int {
    return iterator_count($this->initializeIterator());
  }

  /**
   * Build the absolute URL for a paginated index page.
   */
  protected function buildIndexUrl(int $page): string {
    $url = $this->baseUrl . $this->indexPath;
    if ($page > $this->firstPage) {
      $separator = str_contains($url, '?') ? '&' : '?';
      $url .= $separator . rawurlencode($this->pageQuery) . '=' . $page;
    }
    return $url;
  }

  /**
   * Extract absolute URLs of page detail pages from an index page.
   *
   * @return string[]
   *   Absolute URLs in document order, deduplicated.
   */
  protected function extractPageLinks(string $html): array {
    $crawler = new Crawler($html, $this->baseUrl . '/');
    $urls = [];
    $crawler->filter('a[href]')->each(function (Crawler $node) use (&$urls): void {
      $href = (string) $node->attr('href');
      $abs = $this->absolutize($href);
      if ($abs !== '' && $this->isDetailUrl($abs)) {
        $urls[$abs] = TRUE;
      }
    });
    return array_keys($urls);
  }

  /**
   * Return TRUE when the URL points at a page detail page.
   */
  protected function isDetailUrl(string $url): bool {
    $prefix = $this->baseUrl . $this->indexPath . '/';
    if (!str_starts_with($url, $prefix)) {
      return FALSE;
    }
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $tail = substr($path, strlen($this->indexPath . '/'));
    return $tail !== '' && !str_contains($tail, '/');
  }

  /**
   * Resolve an href against the configured base URL.
   */
  protected function absolutize(string $href): string {
    $href = trim($href);
    if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'javascript:')) {
      return '';
    }
    if (preg_match('#^https?://#i', $href) === 1) {
      return $href;
    }
    if (str_starts_with($href, '//')) {
      return 'https:' . $href;
    }
    if (str_starts_with($href, '/')) {
      return $this->baseUrl . $href;
    }
    return $this->baseUrl . '/' . $href;
  }

  /**
   * Extract the slug (final path segment) from a detail URL.
   */
  protected function slugFromUrl(string $url): string {
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
    return $segments === [] ? '' : end($segments);
  }

  /**
   * Parse a page detail page into a row.
   *
   * @return array<string, mixed>|null
   *   Parsed row array, or NULL when the page lacks a usable title.
   */
  protected function parseDetail(string $url, string $slug, string $html): ?array {
    $crawler = new Crawler($html, $url);

    $title = $this->firstText($crawler, ['h1.page-title', 'article h1', 'h1']);
    if ($title === '') {
      return NULL;
    }

    return [
      'id' => $slug,
      'slug' => $slug,
      'url' => $url,
      'title' => $title,
      'hero_image_url' => $this->firstAttr($crawler, [
        '.page-hero img[src]',
        'article img[src]',
        'img[src]',
      ], 'src', TRUE),
      'body_html' => $this->firstHtml($crawler, [
        '.page-body',
        'article .body',
        'article',
      ]),
    ];
  }

  /**
   * Return the trimmed text of the first matching selector, or ''.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   Crawler scoped to the detail page.
   * @param string[] $selectors
   *   CSS selectors tried in order; the first non-empty match wins.
   */
  protected function firstText(Crawler $crawler, array $selectors): string {
    foreach ($selectors as $selector) {
      $node = $crawler->filter($selector);
      if ($node->count() > 0) {
        $text = trim($node->first()->text(''));
        if ($text !== '') {
          return $text;
        }
      }
    }
    return '';
  }

  /**
   * Return the inner HTML of the first matching selector, or ''.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   Crawler scoped to the detail page.
   * @param string[] $selectors
   *   CSS selectors tried in order; the first non-empty match wins.
   */
  protected function firstHtml(Crawler $crawler, array $selectors): string {
    foreach ($selectors as $selector) {
      $node = $crawler->filter($selector);
      if ($node->count() > 0) {
        $html = trim($node->first()->html(''));
        if ($html !== '') {
          return $html;
        }
      }
    }
    return '';
  }

  /**
   * Return an attribute of the first matching selector, optionally absolutized.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   Crawler scoped to the detail page.
   * @param string[] $selectors
   *   CSS selectors tried in order; the first non-empty attribute wins.
   * @param string $attr
   *   Name of the HTML attribute to read.
   * @param bool $absolutize
   *   When TRUE, resolve relative URLs against the configured base URL.
   */
  protected function firstAttr(Crawler $crawler, array $selectors, string $attr, bool $absolutize = FALSE): string {
    foreach ($selectors as $selector) {
      $node = $crawler->filter($selector);
      if ($node->count() > 0) {
        $value = (string) $node->first()->attr($attr);
        if ($value !== '') {
          return $absolutize ? $this->absolutize($value) : $value;
        }
      }
    }
    return '';
  }

}
