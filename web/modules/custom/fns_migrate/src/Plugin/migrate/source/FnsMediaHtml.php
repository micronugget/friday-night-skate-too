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
 * Source plugin for the legacy fridaynightskate.com `/media` page.
 *
 * The `/media` page is a mixed gallery. Each item is classified into one of
 * four bundle types so the migration YAML can route rows to the correct
 * destination entity:
 *
 *  - `image`        — Photo sets hosted on Facebook / external image hosts.
 *                     Destination: `media` entity, bundle `image`.
 *  - `youtube`      — YouTube video embeds or links.
 *                     Destination: `videojs_media` entity, bundle `videojs_youtube`.
 *  - `local_video`  — Locally-hosted video files (direct .mp4 / .webm URLs).
 *                     Destination: `videojs_media` entity, bundle `videojs_local_video`.
 *  - `press`        — External press/news articles.
 *                     Destination: `node`, bundle `article` (with field_legacy_url).
 *
 * If a video file is not publicly downloadable the row is still yielded with
 * bundle `remote_video` so editors can decide whether to ingest the bytes
 * later. The `media_url` field carries the remote URL in that case.
 *
 * HTTP traffic is delegated to the shared {@see FnsHttpClient} so every
 * response is cached on disk under `private://fns_migrate_cache/media/` and
 * transient errors are retried with exponential backoff.
 *
 * Configuration keys (all optional, sensible defaults):
 *   base_url:   Origin of the legacy site. Default 'https://fridaynightskate.com'.
 *   index_path: Path of the media page. Default '/media'.
 *
 * @MigrateSource(
 *   id = "fns_media_html",
 *   source_module = "fns_migrate"
 * )
 */
class FnsMediaHtml extends SourcePluginBase implements ContainerFactoryPluginInterface {

  protected const DEFAULT_BASE_URL = 'https://fridaynightskate.com';
  protected const DEFAULT_INDEX_PATH = '/media';

  /**
   * YouTube URL patterns used for bundle detection.
   */
  protected const YOUTUBE_PATTERNS = [
    'youtube.com/watch',
    'youtube.com/embed',
    'youtu.be/',
    'youtube.com/shorts',
  ];

  /**
   * Video file extensions used for local_video detection.
   */
  protected const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg', 'mov', 'avi'];

  /**
   * Resolved base URL (no trailing slash).
   */
  protected string $baseUrl;

  /**
   * Resolved index path (leading slash).
   */
  protected string $indexPath;

  /**
   * Constructs a FnsMediaHtml source plugin.
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
    return 'fns_media_html:' . $this->baseUrl . $this->indexPath;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'id'            => $this->t('Stable identifier derived from the item URL or title slug.'),
      'bundle'        => $this->t('Target bundle: image | youtube | local_video | remote_video | press.'),
      'title'         => $this->t('Item title or caption.'),
      'url'           => $this->t('Absolute URL of the source item (external link or detail page).'),
      'thumbnail_url' => $this->t('Absolute URL of the thumbnail / hero image.'),
      'media_url'     => $this->t('Direct media URL (video file or YouTube URL).'),
      'author'        => $this->t('Author / photographer credit, if present.'),
      'published_date' => $this->t('Publication date string (YYYY-MM-DD or as found on page).'),
      'source_label'  => $this->t('Human-readable source label (e.g. "Facebook", "AT5").'),
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
   * Fetches the /media index page and yields one row per gallery item.
   * Items are classified into bundles by inspecting their link targets and
   * thumbnail URLs. Every HTTP fetch goes through the cached FnsHttpClient.
   */
  protected function initializeIterator(): \Iterator {
    $indexUrl = $this->baseUrl . $this->indexPath;
    $html = $this->httpClient->fetch($indexUrl, 'media', 'index');
    yield from $this->parseMediaPage($html, $indexUrl);
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount(): int {
    return iterator_count($this->initializeIterator());
  }

  /**
   * Parse the /media page HTML and yield one row per gallery item.
   *
   * @param string $html
   *   Raw HTML of the media index page.
   * @param string $pageUrl
   *   Absolute URL of the page (used as base for relative hrefs).
   *
   * @return \Generator<array<string, mixed>>
   *   Yields associative row arrays.
   */
  protected function parseMediaPage(string $html, string $pageUrl): \Generator {
    $crawler = new Crawler($html, $pageUrl);

    // --- Photos / videos section ---
    // Gallery tiles: anchor elements wrapping a thumbnail image, linking to
    // external photo albums (Facebook, Flickr, etc.) or video pages.
    // Why: Symfony DomCrawler's each() does not support generators, so we
    // iterate the DOM nodes manually with foreach to be able to yield rows.
    $nodes = $crawler->filter('a[href]');
    $seen2 = [];
    foreach ($nodes as $domNode) {
      $node = new Crawler($domNode, $pageUrl);
      $href = $this->absolutize((string) $node->attr('href'));
      if ($href === '' || str_starts_with($href, $this->baseUrl)) {
        continue;
      }
      $img = $node->filter('img[src]');
      $thumbnailUrl = $img->count() > 0 ? $this->absolutize((string) $img->first()->attr('src')) : '';
      if ($thumbnailUrl === '') {
        continue;
      }
      $bundle = $this->resolveBundle($href);
      $title = $this->extractLinkTitle($node);
      $id = $this->makeId($href);
      if ($id === '' || isset($seen2[$id])) {
        continue;
      }
      $seen2[$id] = TRUE;
      yield [
        'id'             => $id,
        'bundle'         => $bundle,
        'title'          => $title,
        'url'            => $href,
        'thumbnail_url'  => $thumbnailUrl,
        'media_url'      => in_array($bundle, ['youtube', 'local_video', 'remote_video'], TRUE) ? $href : '',
        'author'         => $this->extractAuthor($node),
        'published_date' => $this->extractDate($node),
        'source_label'   => $this->extractSourceLabel($href),
      ];
    }

    // --- Press / news section ---
    // Press items: anchors WITHOUT a thumbnail image, linking to external
    // news articles. These become `article` nodes with field_legacy_url.
    // Why: the "In the news" section uses text-only links or links with a
    // separate image element outside the anchor.
    $pressNodes = $crawler->filter('a[href]');
    foreach ($pressNodes as $domNode) {
      $node = new Crawler($domNode, $pageUrl);
      $href = $this->absolutize((string) $node->attr('href'));
      if ($href === '' || str_starts_with($href, $this->baseUrl)) {
        continue;
      }
      // Skip already-yielded items (those with thumbnails).
      $id = $this->makeId($href);
      if ($id === '' || isset($seen2[$id])) {
        continue;
      }
      $title = trim($node->text(''));
      if ($title === '') {
        continue;
      }
      // Only yield if this looks like a press article (not a social media album).
      if ($this->resolveBundle($href) !== 'press') {
        continue;
      }
      $seen2[$id] = TRUE;
      yield [
        'id'             => $id,
        'bundle'         => 'press',
        'title'          => $title,
        'url'            => $href,
        'thumbnail_url'  => '',
        'media_url'      => '',
        'author'         => '',
        'published_date' => '',
        'source_label'   => $this->extractSourceLabel($href),
      ];
    }
  }

  /**
   * Resolve the target bundle for a given URL.
   *
   * Bundle resolution rules (in priority order):
   *  1. YouTube URL patterns → `youtube`.
   *  2. Direct video file extension → `local_video`.
   *  3. Facebook / Flickr / Instagram photo album → `image`.
   *  4. Everything else external → `press`.
   *
   * @param string $url
   *   Absolute URL to classify.
   *
   * @return string
   *   One of: image | youtube | local_video | remote_video | press.
   */
  public function resolveBundle(string $url): string {
    // Why: YouTube detection must come first because YouTube URLs sometimes
    // contain image-like query parameters.
    foreach (self::YOUTUBE_PATTERNS as $pattern) {
      if (str_contains($url, $pattern)) {
        return 'youtube';
      }
    }

    // Direct video file by extension.
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (in_array($ext, self::VIDEO_EXTENSIONS, TRUE)) {
      return 'local_video';
    }

    // Photo album hosts.
    if (
      str_contains($url, 'facebook.com') ||
      str_contains($url, 'fb.com') ||
      str_contains($url, 'flickr.com') ||
      str_contains($url, 'instagram.com') ||
      str_contains($url, 'photos.google.com') ||
      str_contains($url, 'imgur.com')
    ) {
      return 'image';
    }

    // Fallback: treat as press article.
    return 'press';
  }

  /**
   * Derive a stable ID from a URL.
   *
   * Uses the last meaningful path segment, falling back to a hash of the full
   * URL when the path is empty or ambiguous.
   *
   * @param string $url
   *   Absolute URL.
   *
   * @return string
   *   Stable string identifier, max 191 chars.
   */
  protected function makeId(string $url): string {
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    $query = parse_url($url, PHP_URL_QUERY) ?? '';
    $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
    $slug = $segments !== [] ? end($segments) : '';

    // Append a short query hash to avoid collisions on paginated album URLs
    // (e.g. facebook.com/media/set/?set=a.123 vs ?set=a.456).
    if ($query !== '') {
      $slug .= '-' . substr(md5($query), 0, 8);
    }

    if ($slug === '') {
      $slug = substr(md5($url), 0, 16);
    }

    return substr($slug, 0, 191);
  }

  /**
   * Extract a human-readable title from a link node.
   *
   * Tries heading elements inside the anchor first, then falls back to the
   * anchor's own text content.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $node
   *   Crawler scoped to the anchor element.
   *
   * @return string
   *   Trimmed title string, or '' when nothing useful is found.
   */
  protected function extractLinkTitle(Crawler $node): string {
    foreach (['h1', 'h2', 'h3', 'h4', '.title', '.caption'] as $selector) {
      $heading = $node->filter($selector);
      if ($heading->count() > 0) {
        $text = trim($heading->first()->text(''));
        if ($text !== '') {
          return $text;
        }
      }
    }
    $img = $node->filter('img[alt]');
    if ($img->count() > 0) {
      $alt = trim((string) $img->first()->attr('alt'));
      if ($alt !== '') {
        return $alt;
      }
    }
    return trim($node->text(''));
  }

  /**
   * Extract an author / photographer credit from the surrounding context.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $node
   *   Crawler scoped to the anchor element.
   *
   * @return string
   *   Author string, or '' when not found.
   */
  protected function extractAuthor(Crawler $node): string {
    foreach (['.author', '.photographer', '.credit', '[data-author]'] as $selector) {
      $el = $node->filter($selector);
      if ($el->count() > 0) {
        $text = trim($el->first()->text(''));
        if ($text !== '') {
          return $text;
        }
      }
    }
    return '';
  }

  /**
   * Extract a publication date from the surrounding context.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $node
   *   Crawler scoped to the anchor element.
   *
   * @return string
   *   Date string (YYYY-MM-DD preferred), or '' when not found.
   */
  protected function extractDate(Crawler $node): string {
    $time = $node->filter('time[datetime]');
    if ($time->count() > 0) {
      $dt = (string) $time->first()->attr('datetime');
      if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $dt, $m) === 1) {
        return $m[1];
      }
    }
    foreach (['.date', '.published', '[data-date]'] as $selector) {
      $el = $node->filter($selector);
      if ($el->count() > 0) {
        $text = trim($el->first()->text(''));
        if ($text !== '' && preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $m) === 1) {
          return $m[1];
        }
      }
    }
    return '';
  }

  /**
   * Derive a human-readable source label from the URL host.
   *
   * @param string $url
   *   Absolute URL.
   *
   * @return string
   *   Label such as "Facebook", "AT5", or the bare hostname.
   */
  protected function extractSourceLabel(string $url): string {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $map = [
      'facebook.com'    => 'Facebook',
      'fb.com'          => 'Facebook',
      'youtube.com'     => 'YouTube',
      'youtu.be'        => 'YouTube',
      'flickr.com'      => 'Flickr',
      'instagram.com'   => 'Instagram',
      'at5.nl'          => 'AT5',
      'telegraaf.nl'    => 'De Telegraaf',
      'ad.nl'           => 'AD',
      'nos.nl'          => 'NOS',
      'parool.nl'       => 'Het Parool',
    ];
    foreach ($map as $pattern => $label) {
      if (str_contains($host, $pattern)) {
        return $label;
      }
    }
    // Strip www. prefix for a clean fallback label.
    return preg_replace('/^www\./', '', $host) ?: $host;
  }

  /**
   * Resolve an href against the configured base URL.
   *
   * @param string $href
   *   Raw href attribute value.
   *
   * @return string
   *   Absolute URL, or '' for non-navigable hrefs.
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

}
