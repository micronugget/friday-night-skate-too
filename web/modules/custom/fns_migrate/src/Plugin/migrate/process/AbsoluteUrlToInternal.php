<?php

declare(strict_types=1);

namespace Drupal\fns_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Rewrite absolute legacy-site URLs to site-relative paths in body HTML.
 *
 * Any `href` or `src` attribute value that begins with the configured
 * `legacy_base_url` (default `https://fridaynightskate.com`) is rewritten to
 * a root-relative path so that internal links survive the domain cutover.
 *
 * Example — given `legacy_base_url: https://fridaynightskate.com`:
 *   `https://fridaynightskate.com/routes/canal-classic`
 *   → `/routes/canal-classic`
 *
 * URLs pointing at other domains are left untouched.
 *
 * Configuration:
 *   legacy_base_url: Origin of the legacy site (no trailing slash).
 *                    Default: `https://fridaynightskate.com`.
 *
 * Example:
 * @code
 * body/value:
 *   plugin: absolute_url_to_internal
 *   source: body_html
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "absolute_url_to_internal"
 * )
 */
class AbsoluteUrlToInternal extends ProcessPluginBase {

  protected const DEFAULT_LEGACY_BASE = 'https://fridaynightskate.com';

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || $value === '') {
      return $value;
    }

    $base = rtrim(
      (string) ($this->configuration['legacy_base_url'] ?? self::DEFAULT_LEGACY_BASE),
      '/'
    );

    return $this->rewrite((string) $value, $base);
  }

  /**
   * Replace all occurrences of the legacy base URL with a root-relative path.
   *
   * Only rewrites values inside `href="…"` and `src="…"` attributes so that
   * the legacy origin does not accidentally get stripped from visible text.
   *
   * @param string $html
   *   Raw HTML body string.
   * @param string $base
   *   Legacy origin (no trailing slash), e.g. `https://fridaynightskate.com`.
   *
   * @return string
   *   HTML with legacy absolute URLs replaced by root-relative paths.
   */
  public function rewrite(string $html, string $base): string {
    $quoted = preg_quote($base, '/');
    // Match href="<legacy-url>…" and src="<legacy-url>…" (double or single
    // quotes).  Replace only the origin portion, preserving the path.
    return preg_replace(
      '/(?<=href="|href=\'|src="|src=\')' . $quoted . '(?=\/)/i',
      '',
      $html
    ) ?? $html;
  }

}
