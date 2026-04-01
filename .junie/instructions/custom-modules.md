# Custom Modules — Coding Patterns
> Use when writing or modifying code in `web/modules/custom/` — porting modules from v1,
> adding new functionality, writing tests, or fixing bugs in the four custom modules.

## Module Overview

| Module | Purpose |
|--------|---------|
| `videojs_media` | Custom VideoJS-based media player; supports multiple view modes and bundle types |
| `fns_archive` | Archive content type, Views (archive_by_date, moderation_dashboard, my_archive_content), Masonry grid |
| `skating_video_uploader` | YouTube OAuth upload flow; stores API credentials via Key module |
| `fridaynightskate` | *(theme — see `.junie/instructions/theme-fridaynightskate.md`)* |

All modules live in `web/modules/custom/`. All code changes go into v2 only — never modify v1.

---

## Porting from v1

The v1 source lives at:
```
/home/lee/ams_projects/2025/week-21/v2/fridaynightskate/
```

**v1 is READ-ONLY.** Use it as a reference — read files, copy code into v2, diff implementations. Never run `ddev` commands or commit anything inside v1.

### Porting checklist (per module)
1. Copy the module directory from v1 into `web/modules/custom/` in v2.
2. Review `<module>.info.yml` — update `core_version_requirement` if needed.
3. Check all dependencies are available in v2's `composer.json`.
4. Run `ddev drush en <module> -y` and resolve any missing dependencies.
5. Run `ddev drush cr` and check for errors.
6. Port and run the module's test suite (see Testing section below).
7. Run PHPCS: `ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/<module>/`

---

## PHP Standards

- `declare(strict_types=1);` at the top of **every** new PHP file — non-negotiable.
- Follow [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards).
- Services use constructor injection. Never call `\Drupal::service()` inside a service class.
- Never modify `web/core/` or `vendor/`.

---

## videojs_media

Provides a custom media source plugin and field formatters for VideoJS player integration.

- View modes: `default`, `teaser`, `modal` (at minimum — check v1 for full list).
- Bundle types: local video files and potentially YouTube embeds.
- Templates live in `videojs_media/templates/`.
- JS behaviors attach the VideoJS player instance — always use `once()` to prevent double-init.
- After template or JS changes: `ddev drush cr`.

### Key patterns
- Field formatters extend `FormatterBase` — use `create()` factory for service injection.
- Player config (autoplay, controls, etc.) should be passed via `drupalSettings` — define the library with `core/drupalSettings` dependency.

---

## fns_archive

Provides the `archive_media` content type and associated Views and block layout.

### Views
| View | Machine name | Purpose |
|------|-------------|---------|
| Archive by date | `archive_by_date` | Main public archive grid |
| Moderation dashboard | `moderation_dashboard` | Editorial workflow overview |
| My archive content | `my_archive_content` | Per-user content list |

- Views config lives in `config/sync/` — export after any UI change: `ddev drush cex`.
- The archive grid uses Masonry — JS is in the `fridaynightskate` theme, not this module.
- Pathauto pattern for `archive_media` nodes: verify in `config/sync/pathauto.pattern.*.yml`.

### MetadataExtractor
- Extracts GPS EXIF from images and GPS/timecode from video files.
- Depends on `php-ffmpeg/php-ffmpeg` (ffmpeg/ffprobe must be available in DDEV container — see issue #2).
- Service ID: check v1's `fns_archive.services.yml` for the exact service definition.

---

## skating_video_uploader

Handles YouTube OAuth 2.0 upload flow for skating videos.

- API credentials stored via the **Key** module — never hardcode credentials.
- Key names: check v1's config for the exact key machine names used.
- OAuth tokens are stored per-user — check v1's token storage implementation.
- The upload form is a multi-step form — use `$form_state->set()`/`$form_state->get()` for step data.

### YouTube API
- Uses `google/apiclient` (`^2`) — already in `composer.json`.
- Scopes required: `https://www.googleapis.com/auth/youtube.upload`
- After credential changes, clear caches: `ddev drush cr`.

---

## Testing

### How to run
```bash
ddev phpunit
```

`phpunit.xml` at project root points test suites to `web/modules/custom/*/tests/`.

| Env var | Value |
|---------|-------|
| `SIMPLETEST_BASE_URL` | `https://fridaynightskate2.ddev.site` |
| `SIMPLETEST_DB` | `mysql://db:db@db/db` |

Both Kernel and Functional tests **must** run inside DDEV.

### Kernel vs Functional

| Question | Use |
|----------|-----|
| Testing a service method (MetadataExtractor, validator)? | `Kernel` |
| Testing a form, AJAX, rendered HTML? | `Functional` |
| Need a real browser session / assertSession? | `Functional` |

### Functional base class
```php
use Drupal\Tests\BrowserTestBase;
class MyTest extends BrowserTestBase {
  protected $defaultTheme = 'stark'; // always set this
```

---

## Pre-commit Gates

```bash
ddev phpunit                                                          # all tests pass
ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/     # no CS errors
ddev drush cex                                                        # config exported
```
