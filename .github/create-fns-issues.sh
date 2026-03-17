#!/usr/bin/env bash
# Creates all Friday Night Skate v2 migration issues from ISSUES.md
# Run from project root: bash .github/create-fns-issues.sh
set -euo pipefail

REPO="micronugget/friday-night-skate-too"
declare -A GH  # maps ISSUES.md ref → GitHub issue number

create() {
  local ref="$1" title="$2" labels="$3" body="$4"
  local url num
  url=$(gh issue create --repo "$REPO" --title "$title" --label "$labels" --body "$body" 2>&1)
  num=$(echo "$url" | grep -oE '[0-9]+$')
  GH[$ref]=$num
  echo "  Created ISSUES.md #${ref} → GitHub #${num}: ${title}"
}

echo "=== Creating FNS v2 migration issues ==="
echo ""

# ─── TASK ISSUES ────────────────────────────────────────────────────────────

create "0" \
  "[setup] Drop database and run fresh DrupalCMS 2 installation" \
  "setup,p1,ddev" \
'**ISSUES.md ref:** #0

The current v2 database contains Mercury demo content used for learning DrupalCMS 2/Canvas. Drop it and reinstall clean before development begins.

**Steps:**
1. `ddev drush sql:drop --yes`
2. Install using the Starter recipe as the clean baseline:
   ```bash
   ddev drush site:install cms --yes
   ddev drush recipe recipes/drupal_cms_starter
   ```
3. `ddev drush user:password admin "<secure-password>"`
4. Verify: `ddev launch` — Canvas `/home` page under Mercury.

**Notes:**
- Mercury stays installed even after switching to fridaynightskate (Canvas SDC safety net).
- The Starter recipe installs: admin UI, anti-spam, authentication, media, privacy, SEO basics, easy email, Mercury, Canvas.

**Acceptance criteria:**
- [ ] `ddev drush status` shows healthy Drupal installation
- [ ] `/home` loads Canvas home page under Mercury
- [ ] No demo content in database
- [ ] Admin login works'

create "1" \
  "[setup] Add missing Composer dependencies to v2" \
  "setup,p1" \
'**ISSUES.md ref:** #1

Add packages required by the custom modules and Radix subtheme that are not yet in v2.

```bash
ddev composer require \
  drupal/radix:^6 \
  drupal/geofield:^1 \
  drupal/file_upload_secure_validator:^2 \
  google/apiclient:^2 \
  php-ffmpeg/php-ffmpeg:^1
```

**Notes:**
- `drupal/pathauto` already present in contrib — skip.
- `drupal/radix ^6` is the base theme for the fridaynightskate subtheme.
- `drupal/geofield ^1` required by `fns_archive` for `field_gps_coordinates`.
- `drupal/file_upload_secure_validator ^2` required by `videojs_media`.
- `google/apiclient ^2` required by `skating_video_uploader` YouTube upload.
- `php-ffmpeg/php-ffmpeg ^1` required by `skating_video_uploader` VideoProcessor.
- Do NOT add `drupal/videojs_mediablock` — the custom `videojs_media` module replaces it.

**Acceptance criteria:**
- [ ] `ddev composer install` succeeds with no errors
- [ ] `vendor/drupal/radix`, `vendor/google/apiclient`, `vendor/php-ffmpeg` directories exist
- [ ] `web/themes/contrib/radix/` exists'

create "2" \
  "[ddev] Ensure ffmpeg/ffprobe available in DDEV container" \
  "ddev,p1" \
'**ISSUES.md ref:** #2

`skating_video_uploader` → `MetadataExtractor` invokes `ffprobe` for video GPS/timecode extraction. DDEV'\''s default PHP container does not include ffmpeg.

Add to `.ddev/config.yaml`:
```yaml
webimage_extra_packages:
  - ffmpeg
```
Then `ddev restart`.

**Acceptance criteria:**
- [ ] `ddev exec ffprobe -version` returns a valid version string
- [ ] `ddev exec ffmpeg -version` returns a valid version string'

create "3" \
  "[module] Port videojs_media custom module" \
  "module,p1" \
'**ISSUES.md ref:** #3

Port the custom `videojs_media` content-entity module from v1. This is a direct dependency of both `skating_video_uploader` and `fns_archive`.

**Source:** `v1/web/modules/custom/videojs_media/` → `web/modules/custom/videojs_media/`
Exclude `node_modules/` and `package-lock.json` from the copy.

**Steps:**
1. Copy module directory (exclude node_modules)
2. Update `videojs_media.info.yml` → `core_version_requirement: ^11.3`
3. `ddev drush pm:enable file_upload_secure_validator videojs_media -y`
4. `ddev drush updb -y && ddev drush cr`

**Module provides:**
- Custom content entity: `VideoJsMedia` with 5 bundle types: `local_video`, `local_audio`, `remote_video`, `remote_audio`, `youtube`
- 41 config files: entity types, field storage, view displays, view modes (default + teaser per bundle)
- Block plugin: `VideoJsMediaBlock`
- Access control handler

**Acceptance criteria:**
- [ ] Module enables without errors
- [ ] `/admin/structure/videojs-media-types` loads and shows 5 bundle types
- [ ] No errors in `ddev drush watchdog:show`'

create "4" \
  "[module] Port fns_archive custom module" \
  "module,p1" \
'**ISSUES.md ref:** #4

Port the core FNS module providing the `archive_media` content type, `skate_dates` taxonomy, Views, image styles, moderation workflow, and user roles.

**Source:** `v1/web/modules/custom/fns_archive/` → `web/modules/custom/fns_archive/`

**Steps:**
1. Copy module directory
2. Update `fns_archive.info.yml` → `core_version_requirement: ^11.3`
3. Enable prerequisites: `ddev drush pm:enable content_moderation workflows geofield pathauto responsive_image -y`
4. `ddev drush pm:enable fns_archive -y`
5. `ddev drush updb -y`

**Ships via config/install:**
- `node.type.archive_media` — the core content type
- `taxonomy.vocabulary.skate_dates`
- Field storage: `field_archive_media`, `field_gps_coordinates`, `field_metadata`, `field_skate_date`, `field_timestamp`, `field_uploader`
- Views: `archive_by_date`, `moderation_dashboard`, `my_archive_content`
- `workflows.workflow.archive_review`
- `user.role.moderator` and `user.role.skater`
- Image styles: `archive_full`, `archive_large`, `archive_medium`, `archive_thumbnail`
- `responsive_image.styles.archive_responsive`
- `pathauto.pattern.archive_media_pattern`

**Acceptance criteria:**
- [ ] `archive_media` content type visible at `/admin/structure/types`
- [ ] `skate_dates` vocabulary visible at `/admin/structure/taxonomy`
- [ ] All three Views visible at `/admin/structure/views`
- [ ] `archive_review` workflow visible at `/admin/config/workflow/workflows`
- [ ] `moderator` and `skater` roles visible at `/admin/people/roles`'

create "5" \
  "[module] Port skating_video_uploader custom module" \
  "module,p2" \
'**ISSUES.md ref:** #5

Port the GPS/timecode metadata extraction, bulk upload form, and YouTube upload module.

**Source:** `v1/web/modules/custom/skating_video_uploader/` → `web/modules/custom/skating_video_uploader/`

**Steps:**
1. Copy module directory
2. Update `skating_video_uploader.info.yml` → `core_version_requirement: ^11.3`
3. `ddev drush pm:enable skating_video_uploader -y`
4. `ddev drush updb -y`
5. Configure YouTube API at `/admin/config/skating-video-uploader/youtube-settings`

**Key services:**
- `MetadataExtractor` — GPS from EXIF (`exif_read_data()`) + video metadata via ffprobe
- `VideoProcessor` — pre-upload video processing
- `YouTubeUploader` — YouTube Data API v3 via `google/apiclient`
- `BulkUploadForm` — multi-file upload UI at `/skating-video-uploader/bulk-upload`
- `YouTubeAuthController` — OAuth callback at `/skating-video-uploader/youtube-auth`

**Acceptance criteria:**
- [ ] Module enables without errors
- [ ] `/admin/config/skating-video-uploader/youtube-settings` loads
- [ ] `/skating-video-uploader/bulk-upload` loads for authenticated users'

create "6" \
  "[theme] Copy fridaynightskate Radix subtheme to v2" \
  "theme,p1" \
'**ISSUES.md ref:** #6

Copy the Starry Night Radix 6 subtheme from v1 into v2 after Radix is installed via Composer (#1).

**Source:** `v1/web/themes/custom/fridaynightskate/` → `web/themes/custom/fridaynightskate/`
Exclude `node_modules/` from the copy.

**Steps:**
1. Confirm `web/themes/contrib/radix/` exists (after #1)
2. Copy theme directory (exclude node_modules)
3. `ddev drush theme:enable fridaynightskate -y`
4. The compiled `build/` directory from v1 is usable immediately — no npm build required to get the theme rendering.

**Notes:**
- `fridaynightskate.breakpoints.yml` must be included — it is referenced by `responsive_image.styles.archive_responsive` from fns_archive.
- Keep Mercury installed — do not uninstall it.

**Acceptance criteria:**
- [ ] `ddev drush theme:list` shows `fridaynightskate` as enabled
- [ ] No theme-related PHP errors in `ddev drush watchdog:show`'

create "7" \
  "[config] Set fridaynightskate as default theme; keep Gin as admin theme" \
  "config,p1" \
'**ISSUES.md ref:** #7

After the fresh Starter install (#0) the default theme is Mercury. Switch to fridaynightskate after enabling it in #6.

```bash
ddev drush config:set system.theme default fridaynightskate -y
ddev drush cr
```

**Notes:**
- Do NOT change `system.theme.admin` — keep `gin` as set by DrupalCMS 2.
- Do NOT uninstall Mercury — leave it installed (Canvas SDC safety).

**Acceptance criteria:**
- [ ] `/admin/appearance` shows `fridaynightskate` as the default theme
- [ ] Admin pages still use the Gin theme
- [ ] Front page renders with Starry Night aesthetic'

create "8" \
  "[theme] Audit Canvas page builder compatibility with Radix subtheme" \
  "theme,config,p2" \
'**ISSUES.md ref:** #8

Canvas renders component trees into the active theme'\''s `content` region. Verify the handshake between Canvas and the Radix subtheme works cleanly.

**Checklist:**
- [ ] `/home` (Canvas front page) renders correctly inside the Radix page template
- [ ] `page--front.html.twig` outputs `{{ page.content }}` as its primary slot — Starry Night aesthetics wrap around it, not replace it
- [ ] Canvas SDCs do not assume Mercury-specific CSS class names or layout tokens
- [ ] Review Canvas component enable/disable list at `/admin/structure/canvas/components` — disable anything inappropriate for public-facing pages
- [ ] Admin toolbar (Gin + Navigation) renders correctly over Radix theme
- [ ] Block layout: place blocks fresh against `fridaynightskate` regions (see #10)

**Notes:**
- The Starry Night animated canvas, palette, and fonts live entirely in the Radix subtheme — Canvas has no visibility into them and will not interfere.'

create "9" \
  "[config] Verify pathauto pattern for archive_media nodes" \
  "config,p2" \
'**ISSUES.md ref:** #9

`fns_archive` ships `pathauto.pattern.archive_media_pattern.yml` in `config/optional/`. Verify it is active after enabling `fns_archive`.

```bash
ddev drush config:get pathauto.pattern.archive_media_pattern
```

If not auto-imported:
```bash
ddev drush config:import --partial --source=web/modules/custom/fns_archive/config/optional -y
```

**Acceptance criteria:**
- [ ] `archive_media` nodes receive auto-generated aliases on save'

create "10" \
  "[config] Block layout — place blocks in fridaynightskate regions" \
  "config,p2" \
'**ISSUES.md ref:** #10

After switching the default theme, place all site blocks in the Radix theme'\''s regions via `/admin/structure/block`.

**Minimum block assignments:**

| Block | Region |
|---|---|
| Site branding | `navbar_branding` |
| Main navigation | `navbar_left` |
| User account menu | `navbar_right` |
| Status messages | `header` |
| Page title | `content` |
| Main page content | `content` |
| Primary admin actions | `content` |
| Footer menu | `footer` |

**Acceptance criteria:**
- [ ] Navigation renders in correct navbar regions
- [ ] Status messages display
- [ ] Footer menu renders
- [ ] No orphaned blocks from Mercury'

create "11" \
  "[test] Verify Views: archive_by_date, moderation_dashboard, my_archive_content" \
  "test,p2" \
'**ISSUES.md ref:** #11

All three Views are installed by `fns_archive`. Run functional checks on each.

**archive_by_date** (`/archive/<skate-date-term>`)
- [ ] Page loads at expected path
- [ ] Empty state renders without errors
- [ ] Masonry grid library (`fridaynightskate/masonry-archive`) attaches
- [ ] Taxonomy term URL argument filter works

**moderation_dashboard** (`/admin/content/moderation`)
- [ ] Accessible to `moderator` role only
- [ ] Shows pending/draft nodes for review

**my_archive_content** (`/user/{user}/archive`)
- [ ] Accessible to `skater` role
- [ ] Shows only current user'\''s submissions

**Acceptance criteria:**
- [ ] All three View pages load without PHP errors
- [ ] Access control is correct per role'

create "12" \
  "[test] Masonry archive grid and modal viewer JavaScript" \
  "test,p2" \
'**ISSUES.md ref:** #12

Verify the Masonry archive grid and AJAX modal viewer work end-to-end.

**Steps:**
1. Create at least one `archive_media` node with a Skate Date term
2. Navigate to the archive view page
3. Verify Masonry layout activates — items arrange in a Pinterest-style grid
4. Click an archive item — verify AJAX modal opens with VideoJS player
5. Close modal — verify focus returns and VideoJS instance is disposed
6. Test on mobile viewport — Masonry should degrade gracefully

**Notes:**
- `archive-masonry.js` uses Masonry.js + Swiper from npm (bundled by Laravel Mix — not CDN)
- `modal-viewer.js` init VideoJS after AJAX response is inserted into DOM
- `node--archive-media--thumbnail.html.twig` drives card markup — `.fns-archive-card` class and `data-node-id` attribute are a JS contract

**Acceptance criteria:**
- [ ] Masonry grid renders and reflows correctly
- [ ] Modal opens/closes without JS console errors
- [ ] VideoJS player plays in modal
- [ ] No VideoJS instance leaks on modal close'

create "13" \
  "[test] MetadataExtractor — GPS EXIF extraction from images" \
  "test,p2" \
'**ISSUES.md ref:** #13

`MetadataExtractor::extractFromImage()` uses PHP'\''s `exif_read_data()` to extract GPS from JPEG files. Pure PHP — no external binaries needed.

**Steps:**
1. Check EXIF extension: `ddev exec php -m | grep exif`
2. Upload a JPEG with GPS EXIF data via the Bulk Upload form
3. Verify `field_gps_coordinates` on the resulting `archive_media` node is populated
4. Verify `field_metadata` JSON blob is populated

**Notes:**
- PHP EXIF extension is typically enabled in DDEV PHP 8.3 image already
- Test images with GPS EXIF can be generated with `exiftool` on host

**Acceptance criteria:**
- [ ] `field_gps_coordinates` shows correct lat/lon after image upload
- [ ] `field_metadata` stores the full EXIF JSON blob'

create "14" \
  "[test] MetadataExtractor — GPS/timecode extraction from video files" \
  "test,p2" \
'**ISSUES.md ref:** #14

`MetadataExtractor` uses `ffprobe` to extract GPS location tags and timecode from video metadata.

**Steps:**
1. Confirm #2 is complete: `ddev exec ffprobe -version`
2. Upload a GoPro/DJI-style MP4 with embedded GPS tags via Bulk Upload
3. Verify `field_gps_coordinates` populated after video processing
4. Verify `field_timestamp` populated from video timecode

**Notes:**
- GoPro/DJI embed GPS in ISO 6709 format (e.g. `+35.6812+139.7671/`) in format_tags
- `MetadataExtractor.php` ~line 165 parses the `location` tag
- If no GPS-tagged video available, use `exiftool -geotag` to inject test GPS data

**Acceptance criteria:**
- [ ] GPS coordinates extracted from at least one test video
- [ ] No PHP fatal errors in `ddev drush watchdog:show` after video upload'

create "15" \
  "[test] skating_video_uploader — YouTube OAuth flow" \
  "test,p3" \
'**ISSUES.md ref:** #15

The YouTube uploader requires Google Cloud credentials. Configure and test the full OAuth flow.

**Prerequisites:**
- Google Cloud project with YouTube Data API v3 enabled
- OAuth 2.0 client credentials (Client ID + Secret)
- v2 dev domain added as authorized redirect URI

**Steps:**
1. Navigate to `/admin/config/skating-video-uploader/youtube-settings`
2. Enter Google OAuth Client ID and Client Secret
3. Initiate OAuth flow — verify redirect to Google consent screen
4. After authorization, verify token stored
5. Attempt test upload via `/skating-video-uploader/bulk-upload`

**Notes:**
- See #22 for storing credentials securely via the Key module instead of plain config

**Acceptance criteria:**
- [ ] OAuth flow completes without errors
- [ ] Test video uploads to YouTube
- [ ] YouTube video ID saved on the entity'

create "16" \
  "[test] VideoJS media player — all view modes and bundle types" \
  "test,p2" \
'**ISSUES.md ref:** #16

Verify all 5 VideoJS bundle types render correctly in both default and teaser view modes.

**Test matrix:**

| Bundle | Default | Teaser |
|---|---|---|
| `local_video` | [ ] | [ ] |
| `local_audio` | [ ] | [ ] |
| `remote_video` | [ ] | [ ] |
| `remote_audio` | [ ] | [ ] |
| `youtube` | [ ] | [ ] |

**Notes:**
- VideoJS JS/CSS is bundled within `videojs_media/assets/` — verify paths are correct after module copy
- Check `videojs_media.module` for any `hook_library_info_build()` path references

**Acceptance criteria:**
- [ ] All 10 combinations render without JS console errors
- [ ] VideoJS player controls display and respond to play/pause'

create "17" \
  "[config] DrupalCMS 2 recipe additions — install missing content type recipes" \
  "config,p2" \
'**ISSUES.md ref:** #17

v1 had several DrupalCMS content type recipes not included in the v2 Starter baseline. Decide which are needed and apply.

| Recipe | Action |
|---|---|
| `drupal_cms_page` | Apply — basic pages needed for Canvas |
| `drupal_cms_blog` | Apply if blog section wanted |
| `drupal_cms_events` | Apply if events section wanted |
| `drupal_cms_news` | Apply if news section wanted |
| `drupal_cms_person` | Apply if team/people section wanted |
| `drupal_cms_search` | Verify — may already be included via Starter sub-recipe |

```bash
ddev drush recipe recipes/drupal_cms_page
ddev drush updb -y && ddev drush cr
```

**Notes:**
- The `archive_media` content type is handled by `fns_archive` (#4) — it is NOT a DrupalCMS recipe.
- Each recipe application must be followed by `ddev drush updb -y && ddev drush cr`.

**Acceptance criteria:**
- [ ] Desired content types visible at `/admin/structure/types`
- [ ] Canvas page builder can create and edit pages using new content types'

create "18" \
  "[phpcs] Run Drupal coding standards against all ported custom modules" \
  "test,p2" \
'**ISSUES.md ref:** #18

After porting the three custom modules, run PHPCS to catch any issues.

```bash
echo "=== PHPCS ===" && \
vendor/bin/phpcs --standard=Drupal,DrupalPractice \
  web/modules/custom/videojs_media \
  web/modules/custom/fns_archive \
  web/modules/custom/skating_video_uploader \
  2>&1 | head -100
```

**Common issues to expect:**
- Missing PHPDoc on methods
- Deprecated `\Drupal::` static calls where DI is possible
- `t()` calls outside class context

**Acceptance criteria:**
- [ ] Zero PHPCS errors across all three modules
- [ ] Warning count documented for future cleanup'

create "19" \
  "[test] PHPUnit test suites for ported modules" \
  "test,p3" \
'**ISSUES.md ref:** #19

Port and run existing test suites from v1.

```bash
vendor/bin/phpunit web/modules/custom/fns_archive/tests/ --testdox
vendor/bin/phpunit web/modules/custom/videojs_media/tests/ --testdox
vendor/bin/phpunit web/modules/custom/skating_video_uploader/tests/ --testdox
```

**Notes:**
- `fns_archive` had moderation and responsive image tests (see v1 `TESTING.md`, `TESTING_MODERATION.md`, `TESTING_RESPONSIVE_IMAGES.md`)
- Update `phpunit.xml` to match v2 project paths if needed
- Fix failures caused by Drupal 11.3 API changes

**Acceptance criteria:**
- [ ] All previously-passing test cases continue to pass
- [ ] New test failures are either fixed or filed as separate issues'

create "20" \
  "[theme] Rebuild fridaynightskate frontend assets for v2" \
  "theme,p3" \
'**ISSUES.md ref:** #20

The compiled `build/` from v1 is functional immediately. This issue is to run a clean npm build in the v2 context to ensure no stale asset references.

```bash
cd web/themes/custom/fridaynightskate
npm install
npm run production
```

Check `webpack.mix.js` for any paths needing updates.

**Notes:**
- `package.json` uses `laravel-mix` + webpack. Node >=16, npm >=6 required.
- Bootstrap 5.3, Masonry.js, and Swiper are npm devDependencies — resolved from `node_modules/` after install.
- Do NOT commit `node_modules/` — ensure it is in `.gitignore`.

**Acceptance criteria:**
- [ ] `npm run production` exits with code 0
- [ ] `build/css/main.style.css` and `build/js/*.js` are regenerated
- [ ] No visual regressions compared to v1'

create "21" \
  "[config] Starry Night theme — verify CSS custom properties and breakpoints for v2" \
  "theme,config,p3" \
'**ISSUES.md ref:** #21

Verify the Starry Night palette and responsive image breakpoints work correctly in the v2 context.

**Steps:**
1. Verify `fridaynightskate.breakpoints.yml` was copied correctly
2. Confirm `responsive_image.styles.archive_responsive` references correct breakpoint names
3. Check Canvas home page and new DrupalCMS 2 pages render acceptably with the night sky colour palette
4. Adjust variables in `src/scss/base/_variables.scss` if needed, then rebuild

**Acceptance criteria:**
- [ ] No broken image style references in Drupal logs
- [ ] Site visual tone is Starry Night aesthetic throughout all page types'

create "22" \
  "[config] Key module — store YouTube API credentials securely" \
  "config,p3" \
'**ISSUES.md ref:** #22

v2 already has the `key` module installed. Store Google OAuth credentials as Key entities instead of plain Drupal config.

**Steps:**
1. Create Key entities at `/admin/config/system/keys`:
   - `youtube_client_id`
   - `youtube_client_secret`
2. Update `skating_video_uploader` → `YouTubeSettingsForm` to inject `\Drupal\key\KeyRepositoryInterface` and load credentials from Key entities instead of direct config storage
3. Ensure key values are never exported to `config/sync/`

**Acceptance criteria:**
- [ ] YouTube credentials are not stored in config YAML
- [ ] `key.key.youtube_client_id` and `key.key.youtube_client_secret` entities load correct values
- [ ] `YouTubeUploader` service retrieves credentials via Key module'

echo ""
echo "=== All task issues created ==="
echo ""

# ─── EPIC ISSUES ─────────────────────────────────────────────────────────────

echo "=== Creating Epic issues ==="
echo ""

gh issue create --repo "$REPO" \
  --title "Epic: Environment Setup & Fresh Install" \
  --label "epic,p1,setup,ddev" \
  --body "## Epic: Environment Setup & Fresh Install

Clean-slate DrupalCMS 2 installation with all dependencies in place before any custom code is ported.

### Sub-issues
- [ ] #${GH[0]} — Drop database and run fresh DrupalCMS 2 installation
- [ ] #${GH[1]} — Add missing Composer dependencies to v2
- [ ] #${GH[2]} — Ensure ffmpeg/ffprobe available in DDEV container

### Definition of Done
All three sub-issues closed. The result is a clean DrupalCMS 2 + Canvas + Mercury site with \`drupal/radix\`, \`drupal/geofield\`, \`drupal/file_upload_secure_validator\`, \`google/apiclient\`, and \`php-ffmpeg/php-ffmpeg\` in vendor, and ffmpeg available inside the DDEV container." \
  2>&1 | tee /dev/stderr | grep -oE '[0-9]+$' | xargs -I{} echo "  Created Epic 1 → GitHub #{}"

gh issue create --repo "$REPO" \
  --title "Epic: Custom Modules — Port from v1" \
  --label "epic,p1,module" \
  --body "## Epic: Custom Modules — Port from v1

Port the three custom modules from v1 in dependency order: \`videojs_media\` → \`fns_archive\` → \`skating_video_uploader\`.

### Sub-issues
- [ ] #${GH[3]} — Port \`videojs_media\` custom module
- [ ] #${GH[4]} — Port \`fns_archive\` custom module
- [ ] #${GH[5]} — Port \`skating_video_uploader\` custom module
- [ ] #${GH[17]} — Install missing DrupalCMS 2 content type recipes
- [ ] #${GH[9]} — Verify pathauto pattern for archive_media nodes

### Dependency order
\`videojs_media\` must be enabled before \`fns_archive\`, which must be enabled before \`skating_video_uploader\`.

### Definition of Done
All five sub-issues closed. Three custom modules enabled, archive_media content type exists, desired DrupalCMS recipe content types installed." \
  2>&1 | tee /dev/stderr | grep -oE '[0-9]+$' | xargs -I{} echo "  Created Epic 2 → GitHub #{}"

gh issue create --repo "$REPO" \
  --title "Epic: Starry Night Theme — Port & Canvas Integration" \
  --label "epic,p1,theme,config" \
  --body "## Epic: Starry Night Theme — Port & Canvas Integration

Copy the fridaynightskate Radix subtheme to v2, set it as default, and verify it works seamlessly with the Canvas page builder.

### Sub-issues
- [ ] #${GH[6]} — Copy fridaynightskate Radix subtheme to v2
- [ ] #${GH[7]} — Set fridaynightskate as default theme; keep Gin as admin theme
- [ ] #${GH[8]} — Audit Canvas page builder compatibility with Radix subtheme
- [ ] #${GH[10]} — Block layout — place blocks in fridaynightskate regions
- [ ] #${GH[20]} — Rebuild fridaynightskate frontend assets for v2
- [ ] #${GH[21]} — Verify CSS custom properties and breakpoints for v2

### Architecture note
Canvas is the page-building layer; fridaynightskate (Radix/Bootstrap 5) is the visual layer. \`page--front.html.twig\` must output \`{{ page.content }}\` as its primary slot so Canvas can render its component tree into it. Mercury stays installed — do not uninstall it.

### Definition of Done
All six sub-issues closed. Front page shows Starry Night aesthetic. Canvas edit mode functional. Block layout correct. npm build clean." \
  2>&1 | tee /dev/stderr | grep -oE '[0-9]+$' | xargs -I{} echo "  Created Epic 3 → GitHub #{}"

gh issue create --repo "$REPO" \
  --title "Epic: Testing — Modules, Theme, Media Metadata" \
  --label "epic,p2,test" \
  --body "## Epic: Testing — Modules, Theme, Media Metadata

End-to-end testing of all ported functionality: Views, archive grid, modal viewer, VideoJS player, GPS/timecode metadata extraction, and YouTube upload.

### Sub-issues
- [ ] #${GH[11]} — Verify Views: archive_by_date, moderation_dashboard, my_archive_content
- [ ] #${GH[12]} — Masonry archive grid and modal viewer JavaScript
- [ ] #${GH[13]} — MetadataExtractor — GPS EXIF extraction from images
- [ ] #${GH[14]} — MetadataExtractor — GPS/timecode extraction from video files
- [ ] #${GH[15]} — skating_video_uploader — YouTube OAuth flow
- [ ] #${GH[16]} — VideoJS media player — all view modes and bundle types
- [ ] #${GH[18]} — Run PHPCS against all ported custom modules
- [ ] #${GH[19]} — PHPUnit test suites for ported modules

### Definition of Done
All eight sub-issues closed. All three Views functional, Masonry + modal working, GPS extraction from images and video confirmed, PHPCS clean." \
  2>&1 | tee /dev/stderr | grep -oE '[0-9]+$' | xargs -I{} echo "  Created Epic 4 → GitHub #{}"

gh issue create --repo "$REPO" \
  --title "Epic: Security & Credentials Hardening" \
  --label "epic,p3,config,setup" \
  --body "## Epic: Security & Credentials Hardening

Store YouTube API credentials securely using the Key module instead of plain Drupal config.

### Sub-issues
- [ ] #${GH[22]} — Key module — store YouTube API credentials securely

### Definition of Done
Sub-issue #${GH[22]} closed. YouTube credentials stored as Key entities, not in config YAML, not exported to git." \
  2>&1 | tee /dev/stderr | grep -oE '[0-9]+$' | xargs -I{} echo "  Created Epic 5 → GitHub #{}"

echo ""
echo "=== All issues created successfully ==="
echo ""
echo "GitHub issue number map (ISSUES.md ref → GH#):"
for k in $(echo "${!GH[@]}" | tr ' ' '\n' | sort -n); do
  echo "  ISSUES.md #${k} → GH #${GH[$k]}"
done
