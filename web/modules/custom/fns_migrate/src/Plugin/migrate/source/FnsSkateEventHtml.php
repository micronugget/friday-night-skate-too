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
 * Source plugin for legacy fridaynightskate.com `/skate` event pages.
 *
 * Crawls the paginated skate-event index, then visits each detail page and
 * yields a row with the canonical fields used by the `event` content type
 * migration: `{id, slug, url, title, event_date, event_time, route_slug,
 * meeting_point, body_html}`.
 *
 * HTTP traffic is delegated to the shared {@see FnsHttpClient} so every
 * response is cached on disk under `private://fns_migrate_cache/skates/` and
 * transient errors are retried with exponential backoff.
 *
 * Hard guard: any URL containing `/live` or any page embedding the live-tracker
 * JS is silently skipped — live-tracker content is out of scope for migration.
 *
 * Configuration keys (all optional, sensible defaults):
 *   base_url:    Origin of the legacy site. Default
 *                'https://fridaynightskate.com'.
 *   index_path:  Path of the paginated index. Default '/skate'.
 *   page_query:  Query parameter used for pagination. Default 'page'.
 *   first_page:  First page number. Default 1.
 *   max_pages:   Hard cap on pages to walk (safety net). Default 50.
 *
 * @MigrateSource(
 *   id = "fns_skate_event_html",
 *   source_module = "fns_migrate"
 * )
 */
class FnsSkateEventHtml extends SourcePluginBase implements ContainerFactoryPluginInterface {

  protected const DEFAULT_BASE_URL = 'https://fridaynightskate.com';
  protected const DEFAULT_INDEX_PATH = '/skate';
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
   * Constructs a FnsSkateEventHtml source plugin.
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
    return 'fns_skate_event_html:' . $this->baseUrl . $this->indexPath;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id' => $this->t('Stable identifier (slug or numeric ID).'),
      'slug' => $this->t('URL slug for the skate event.'),
      'url' => $this->t('Absolute URL of the legacy detail page.'),
      'title' => $this->t('Event title.'),
      'event_date' => $this->t('Event date string (YYYY-MM-DD or as found on page).'),
      'event_time' => $this->t('Event start time string (HH:MM or as found on page).'),
      'route_slug' => $this->t('Slug of the linked route, if any.'),
      'meeting_point' => $this->t('Meeting point description.'),
      'body_html' => $this->t('Body HTML of the event description.'),
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
   * Pages containing `/live` in their URL or embedding the live-tracker JS
   * are silently skipped — live-tracker content is out of scope for migration.
   * See issues-blogger-content-migration.md for rationale.
   */
  protected function initializeIterator(): \Iterator {
    $seen = [];
    $page = $this->firstPage;
    $walked = 0;

    while ($walked < $this->maxPages) {
      $indexUrl = $this->buildIndexUrl($page);
      $cacheKey = 'index-' . $page;
      $html = $this->httpClient->fetch($indexUrl, 'skates', $cacheKey);
      $links = $this->extractEventLinks($html);
      if ($links === []) {
        break;
      }

      $newOnPage = 0;
      foreach ($links as $detailUrl) {
        // Out of scope: live tracker — see issues-blogger-content-migration.md.
        if ($this->isLiveTrackerUrl($detailUrl)) {
          continue;
        }

        $slug = $this->slugFromUrl($detailUrl);
        if ($slug === '' || isset($seen[$slug])) {
          continue;
        }
        $seen[$slug] = TRUE;
        $newOnPage++;

        $detailHtml = $this->httpClient->fetch($detailUrl, 'skates', $slug);

        // Out of scope: live tracker — see issues-blogger-content-migration.md.
        if ($this->isLiveTrackerPage($detailHtml)) {
          continue;
        }

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
   * Return TRUE when the URL contains the live-tracker path segment.
   */
  protected function isLiveTrackerUrl(string $url): bool {
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    return str_contains($path, '/live');
  }

  /**
   * Return TRUE when the page HTML embeds the live-tracker JS widget.
   */
  protected function isLiveTrackerPage(string $html): bool {
    return str_contains($html, 'live-tracker') || str_contains($html, 'livetracker');
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
   * Extract absolute URLs of event detail pages from an index page.
   *
   * @return string[]
   *   Absolute URLs in document order, deduplicated.
   */
  protected function extractEventLinks(string $html): array {
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
   * Return TRUE when the URL points at an event detail page.
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
   * Parse a skate event detail page into a row.
   *
   * @return array<string, mixed>|null
   *   Parsed row array, or NULL when the page lacks a usable title.
   */
  protected function parseDetail(string $url, string $slug, string $html): ?array {
    $crawler = new Crawler($html, $url);

    $title = $this->firstText($crawler, ['h1.event-title', 'article h1', 'h1']);
    if ($title === '') {
      return NULL;
    }

    return [
      'id' => $slug,
      'slug' => $slug,
      'url' => $url,
      'title' => $title,
      'event_date' => $this->extractEventDate($crawler),
      'event_time' => $this->extractEventTime($crawler),
      'route_slug' => $this->extractRouteSlug($crawler),
      'meeting_point' => $this->firstText($crawler, [
        '.event-meeting-point',
        '[data-field="meeting_point"]',
        '.meeting-point',
      ]),
      'body_html' => $this->firstHtml($crawler, [
        '.event-body',
        'article .body',
        'article',
      ]),
    ];
  }

  /**
   * Extract the event date from the page.
   *
   * Tries structured date elements first, then falls back to text patterns.
   */
  protected function extractEventDate(Crawler $crawler): string {
    // Try <time datetime="..."> first.
    $time = $crawler->filter('time[datetime]');
    if ($time->count() > 0) {
      $dt = (string) $time->first()->attr('datetime');
      if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $dt, $m) === 1) {
        return $m[1];
      }
    }
    // Try labelled text elements.
    $text = $this->firstText($crawler, [
      '.event-date',
      '[data-field="event_date"]',
      '.date',
    ]);
    if ($text !== '' && preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $m) === 1) {
      return $m[1];
    }
    return $text;
  }

  /**
   * Extract the event start time from the page.
   */
  protected function extractEventTime(Crawler $crawler): string {
    $time = $crawler->filter('time[datetime]');
    if ($time->count() > 0) {
      $dt = (string) $time->first()->attr('datetime');
      if (preg_match('/T(\d{2}:\d{2})/', $dt, $m) === 1) {
        return $m[1];
      }
    }
    $text = $this->firstText($crawler, [
      '.event-time',
      '[data-field="event_time"]',
      '.time',
    ]);
    if ($text !== '' && preg_match('/(\d{1,2}:\d{2})/', $text, $m) === 1) {
      return $m[1];
    }
    return $text;
  }

  /**
   * Extract the slug of the linked route, if any.
   */
  protected function extractRouteSlug(Crawler $crawler): string {
    $link = $crawler->filter('a[href*="/routes/"]');
    if ($link->count() > 0) {
      $href = (string) $link->first()->attr('href');
      return $this->slugFromUrl($href);
    }
    return '';
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

}
