# Friday Night Skate v2 — Agent Guidelines

**Friday Night Skate v2** (`fridaynightskate2`) is a **fresh DrupalCMS 2 install** — the migration *destination* for the Friday Night Skate site (v1). It is a complete rebuild on the DrupalCMS 2 scaffold with Canvas page builder and the `fridaynightskate` Radix 6 subtheme (Starry Night edition) as the visual layer.

## Quick-Start Checklist

Before acting on any task:
- [ ] Read this file fully before writing any code.
- [ ] For custom module work, read `.junie/instructions/custom-modules.md`.
- [ ] For theme changes, read `.junie/instructions/theme-fridaynightskate.md`.
- [ ] Read `.junie/terminal-guide.md` for reliable terminal command patterns.
- [ ] All `ddev` commands run inside the v2 directory only — never in v1.
- [ ] Run `ddev drush cex` before committing any config-touching change.

---

## Migration Context

> **⚠️ Read this section before starting any issue or task.**

**v2 is the migration destination.** It is a greenfield DrupalCMS 2 install. All new development happens here. The source being migrated *from* — **Friday Night Skate v1** — lives at:

```
/home/lee/ams_projects/2025/week-21/v2/fridaynightskate/
```

v1 DDEV site: `https://fridaynightskate.ddev.site` | PHP 8.3 | Drupal 11 | DrupalCMS 1.2.3

### Migration Rules

| Rule | Detail |
|------|--------|
| **v1 is READ-ONLY** | Never modify, commit to, or run destructive commands against v1. It is the migration *source* — a reference only. |
| **v2 is the active codebase** | All code, config, and commits go into v2 (`/home/lee/ams_projects/2026/week-10/v1/fridaynightskate2/`). |
| **Copying FROM v1 is allowed** | Read any v1 file to understand the old implementation, copy code/config/templates into v2, or diff v1 vs v2 when resolving an issue. |
| **No `ddev` commands in v1** | Do not run `ddev drush`, `ddev composer`, or any write command inside v1's directory. All `ddev` commands run in v2 only. |

### Why v1 Exists as a Reference

v1 contains proven implementations of `videojs_media`, `fns_archive`, `skating_video_uploader`, and the `fridaynightskate` Radix 6 subtheme (Starry Night edition / Bootstrap 5). Historical config exports and issue documentation are useful for tracing why specific decisions were made.

### Branching Strategy

All issues use a standard GitHub flow:

```bash
git checkout master && git pull origin master && git checkout -b issue/$N-<slug>
```

PR target: `master` on GitHub via `gh pr create --base master`.

**Branch naming:** `issue/$N-<slug>` where `$N` is the issue number and `<slug>` is a short kebab-case title.

---

### Per-Issue Checklist

Before writing any code for an issue:
1. **Read the issue** in `ISSUES.md` to understand the scope and acceptance criteria.
2. **Check v1** — locate the equivalent file(s) in v1 to understand the existing implementation.
3. **Diff v1 vs v2** — identify what is missing, changed, or needs porting.
4. **Work only in v2.**
5. **Do not alter v1** under any circumstance.
6. **Branch off `master`** for all issues.

---

## Environment Setup

**All CLI commands must be prefixed with `ddev`. No exceptions.**

| Command | Purpose |
|---------|---------|
| `ddev drush cr` | Clear caches — required after hook/service/module/template changes |
| `ddev drush cex` | Export config to code — **always run before committing** |
| `ddev drush cim -y` | Import config from code |
| `ddev composer require …` | Install PHP packages |
| `ddev phpunit` | Run PHPUnit test suite |
| `ddev exec vendor/bin/phpcs --standard=Drupal …` | PHP CodeSniffer |
| `ddev exec vendor/bin/phpstan …` | Static analysis |
| `ddev exec "cd web/themes/custom/fridaynightskate && npm run dev"` | Compile theme assets (Radix 6 / webpack.mix.js) |
| `ddev drush uli --uri=https://fridaynightskate2.ddev.site` | Admin login link |

**DDEV site URL:** `https://fridaynightskate2.ddev.site` | PHP 8.3 | Drupal 11 | DrupalCMS 2.0.2 | MariaDB 10.11

Read `.junie/terminal-guide.md` for reliable terminal command patterns (always use `2>&1`, echo markers, `| head -50` for verbose output).

---

## Architecture

### Custom Modules (`web/modules/custom/`)

| Module | Purpose |
|--------|---------|
| `videojs_media` | Custom VideoJS-based media player; multiple view modes and bundle types |
| `fns_archive` | `archive_media` content type, Views (archive_by_date, moderation_dashboard, my_archive_content), MetadataExtractor service (GPS EXIF + video timecode) |
| `skating_video_uploader` | YouTube OAuth 2.0 upload flow; credentials stored via Key module |

### Theme (`web/themes/custom/fridaynightskate/`)

**Active theme:** `fridaynightskate` — Radix 6.x / Bootstrap 5 / webpack.mix.js — Starry Night Edition

- Build output: `web/themes/custom/fridaynightskate/build/`
- Always build from inside theme: `ddev exec "cd web/themes/custom/fridaynightskate && npm run dev"`
- Masonry archive grid + Swiper modal viewer are JS-driven from the theme
- Canvas is the page-building layer; `fridaynightskate` is the visual layer — keep Mercury installed

### Key Contrib Modules

| Module | Purpose |
|--------|---------|
| `drupal/radix` | Base theme for `fridaynightskate` |
| `drupal/geofield` | GPS coordinate fields on `archive_media` |
| `drupal/webform` | Forms |
| `google/apiclient` | YouTube API client for `skating_video_uploader` |
| `php-ffmpeg/php-ffmpeg` | Video metadata extraction in `fns_archive` |
| Canvas (DrupalCMS 2) | Page builder layer |

---

## Code Standards

### PHP
- `declare(strict_types=1);` at the top of **every** new PHP file — non-negotiable.
- Follow [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards).
- Services use constructor injection. Never call `\Drupal::service()` inside a service class.
- All code inside `web/modules/custom/` and `web/themes/custom/` — never modify `web/core/` or `vendor/`.

### Configuration Management
- Config belongs in code. After any UI configuration change, run `ddev drush cex` and commit.
- After importing config: always `ddev drush cr`.

### Testing
- Run: `ddev phpunit` (uses `phpunit.xml` at project root)
- `SIMPLETEST_BASE_URL=https://fridaynightskate2.ddev.site`, `SIMPLETEST_DB=mysql://db:db@db/db`
- New functionality should include Kernel or Functional tests under the relevant module's `tests/src/`.
- Test class location: `Kernel/` for service tests; `Functional/` for browser/form tests.
- Both Kernel and Functional tests must run inside DDEV (not bare PHP).

### Pre-commit Gates
```bash
ddev phpunit                                                          # all tests pass
ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/     # no CS errors
ddev drush cex                                                        # config exported and committed
```

---

## Domain Instructions

Read these before working in their respective areas:

| Area | File |
|------|------|
| Custom modules (`videojs_media`, `fns_archive`, `skating_video_uploader`) | `.junie/instructions/custom-modules.md` |
| `fridaynightskate` theme | `.junie/instructions/theme-fridaynightskate.md` |

---

## Custom Slash Commands

| Command | Usage | Purpose |
|---------|-------|---------|
| `/close-issue` | `/close-issue issue=3` | Full flow: read issue → branch → implement → test → commit → push → PR → close |
