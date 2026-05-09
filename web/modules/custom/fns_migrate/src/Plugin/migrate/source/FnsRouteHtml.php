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
 * Source plugin for legacy fridaynightskate.com `/routes` pages.
 *
 * Crawls the paginated route index, then visits each detail page and yields a
 * row with the canonical fields used by the `route` content type migration:
 * `{id, slug, url, title, distance_km, difficulty, gpx_url, hero_image_url,
 *  body_html, collections}`.
 *
 * HTTP traffic is delegated to the shared {@see FnsHttpClient} so every
 * response is cached on disk under `private://fns_migrate_cache/routes/` and
 * transient errors are retried with exponential backoff.
 *
 * Configuration keys (all optional, sensible defaults):
 *   base_url:        Origin of the legacy site. Default
 *                    'https://fridaynightskate.com'.
 *   index_path:      Path of the paginated index. Default '/routes'.
 *   page_query:      Query parameter used for pagination. Default 'page'.
 *   first_page:      First page number. Default 1.
 *   max_pages:       Hard cap on pages to walk (safety net). Default 50.
 *
 * @MigrateSource(
 *   id = "fns_route_html",
 *   source_module = "fns_migrate"
 * )
 */
class FnsRouteHtml extends SourcePluginBase implements ContainerFactoryPluginInterface {

  protected const DEFAULT_BASE_URL = 'https://fridaynightskate.com';
  protected const DEFAULT_INDEX_PATH = '/routes';
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
    return 'fns_route_html:' . $this->baseUrl . $this->indexPath;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id' => $this->t('Stable identifier (slug).'),
      'slug' => $this->t('URL slug for the route.'),
      'url' => $this->t('Absolute URL of the legacy detail page.'),
      'title' => $this->t('Route title.'),
      'distance_km' => $this->t('Route distance in kilometres.'),
      'difficulty' => $this->t('Editorial difficulty label.'),
      'gpx_url' => $this->t('Absolute URL of the downloadable GPX file.'),
      'hero_image_url' => $this->t('Absolute URL of the hero image.'),
      'body_html' => $this->t('Body HTML of the route description.'),
      'collections' => $this->t('Array of collection labels (route_collection terms).'),
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
      $html = $this->httpClient->fetch($indexUrl, 'routes', $cacheKey);
      $links = $this->extractRouteLinks($html);
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

        $detailHtml = $this->httpClient->fetch($detailUrl, 'routes', $slug);
        $row = $this->parseDetail($detailUrl, $slug, $detailHtml);
        if ($row !== NULL) {
          yield $row;
        }
      }

      // If a page yielded no new slugs we've reached the end of pagination
      // (or are looping on a static "last page").
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
    // The legacy site does not advertise a total count; defer to iterator.
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
   * Extract absolute URLs of route detail pages from an index page.
   *
   * @return string[]
   *   Absolute URLs in document order, deduplicated.
   */
  protected function extractRouteLinks(string $html): array {
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
   * Return TRUE when the URL points at a route *detail* page.
   *
   * Excludes the index itself and any deeper pagination links.
   */
  protected function isDetailUrl(string $url): bool {
    $prefix = $this->baseUrl . $this->indexPath . '/';
    if (!str_starts_with($url, $prefix)) {
      return FALSE;
    }
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $tail = substr($path, strlen($this->indexPath . '/'));
    // A slug is a single path segment with no further slashes.
    if ($tail === '' || str_contains($tail, '/')) {
      return FALSE;
    }
    // Exclude known non-route landing pages that share the /routes/ prefix.
    $excluded = ['collections', 'create', 'new'];
    return !in_array(strtolower($tail), $excluded, TRUE);
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
   * Parse a route detail page into a row.
   *
   * The selectors below intentionally allow several fallbacks because the
   * legacy site mixes hand-authored and templated markup. Each accessor
   * returns NULL/empty rather than throwing, so a partially-broken page still
   * yields a usable row that the migration's process pipeline can validate.
   */
  protected function parseDetail(string $url, string $slug, string $html): ?array {
    $crawler = new Crawler($html, $url);

    $title = $this->firstText($crawler, ['h1.route-title', 'article h1', 'h1']);
    if ($title === '') {
      // Without a title we have nothing useful to migrate.
      return NULL;
    }

    return [
      'id' => $slug,
      'slug' => $slug,
      'url' => $url,
      'title' => $title,
      'distance_km' => $this->extractDistanceKm($crawler),
      'difficulty' => $this->extractDifficulty($crawler),
      'gpx_url' => $this->extractGpxUrl($crawler),
      'hero_image_url' => $this->extractHeroImageUrl($crawler),
      'body_html' => $this->firstHtml($crawler, [
        '.route-body',
        'article .body',
        '.route-description',
      ]),
      'collections' => $this->extractCollections($crawler),
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

  /**
   * Pull a kilometre value out of any `.route-distance` or labelled element.
   *
   * Accepts strings such as "12 km", "12.5km", "Distance: 7,3 km" and returns
   * a normalised float string (or '' when the value can't be parsed).
   */
  protected function extractDistanceKm(Crawler $crawler): string {
    // Modern markup: a generic <span>NN.NN km</span> sits in the meta row
    // immediately after the H1. Fallback to legacy `.route-distance` etc.
    $selectors = [
      'h1 + div span',
      '.route-distance',
      '[data-field="distance"]',
      '.distance',
      'main span',
    ];
    foreach ($selectors as $selector) {
      $node = $crawler->filter($selector);
      if ($node->count() === 0) {
        continue;
      }
      foreach ($node as $domNode) {
        $text = trim($domNode->textContent ?? '');
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*km/i', $text, $m) === 1) {
          return str_replace(',', '.', $m[1]);
        }
      }
    }
    return '';
  }

  /**
   * Extract a 1-5 difficulty integer from the modern markup.
   *
   * The legacy site renders `<span title="Difficulty 3/5">…<span>3/5</span>`,
   * so we read the title attribute first and the inner "N/5" text as fallback.
   */
  protected function extractDifficulty(Crawler $crawler): string {
    // Legacy textual markup: <span class="route-difficulty">Easy</span>.
    $legacy = $this->firstText($crawler, [
      '.route-difficulty',
      '[data-field="difficulty"]',
    ]);
    if ($legacy !== '') {
      return $legacy;
    }

    // Modern markup: title="Difficulty 3/5" on the wrapper span.
    $titled = $crawler->filter('[title^="Difficulty"]');
    if ($titled->count() > 0) {
      $title = (string) $titled->first()->attr('title');
      if (preg_match('/(\d+)\s*\/\s*5/', $title, $m) === 1) {
        return $m[1];
      }
    }

    // Fallback: a literal "N/5" span anywhere in the document.
    foreach ($crawler->filter('span') as $domNode) {
      $text = trim($domNode->textContent ?? '');
      if (preg_match('/^([1-5])\s*\/\s*5$/', $text, $m) === 1) {
        return $m[1];
      }
    }
    return '';
  }

  /**
   * Extract the GPX download URL.
   *
   * Modern markup exposes the URL on `[data-gpx]` and on a download anchor.
   * Falls back to the legacy `a[href$=.gpx]` selector.
   */
  protected function extractGpxUrl(Crawler $crawler): string {
    $selectors = [
      ['[data-gpx]', 'data-gpx'],
      ['a[href$=".gpx"]', 'href'],
      ['a.route-gpx[href]', 'href'],
    ];
    foreach ($selectors as [$selector, $attr]) {
      $node = $crawler->filter($selector);
      if ($node->count() > 0) {
        $value = (string) $node->first()->attr($attr);
        if ($value !== '') {
          return $this->absolutize($value);
        }
      }
    }
    return '';
  }

  /**
   * Extract the hero image URL (preferring the og:image meta tag).
   */
  protected function extractHeroImageUrl(Crawler $crawler): string {
    $og = $crawler->filter('meta[property="og:image"]');
    if ($og->count() > 0) {
      $content = (string) $og->first()->attr('content');
      if ($content !== '') {
        return $this->absolutize($content);
      }
    }
    return $this->firstAttr($crawler, [
      '.route-hero img[src]',
      'article img[src]',
      'main img[src]',
    ], 'src', TRUE);
  }

  /**
   * Extract the route-collection labels listed on the detail page.
   *
   * @return string[]
   *   Distinct, document-order list of collection labels.
   */
  protected function extractCollections(Crawler $crawler): array {
    $labels = [];
    $crawler->filter('.route-collections a, .route-collection-tag, [data-collection]')
      ->each(function (Crawler $node) use (&$labels): void {
        $label = trim($node->text(''));
        if ($label !== '' && !in_array($label, $labels, TRUE)) {
          $labels[] = $label;
        }
      });
    return $labels;
  }

}
