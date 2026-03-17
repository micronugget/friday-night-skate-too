# Friday Night Skate v2 — Migration Issues

Migration of `fridaynightskate` (v1, DrupalCMS 1.2.3) to `fridaynightskate2` (v2, DrupalCMS 2.0.2 with Canvas).

**Current state of v2:** The `fridaynightskate2` repo was used as a learning/exploration
environment for DrupalCMS 2 and Canvas. It currently has the Mercury theme installed
with some demo content. **This state is intentionally temporary.** Before development
begins, the database will be dropped and the site reinstalled fresh (see #0).

**Target architecture:** DrupalCMS 2 + Canvas page builder as the foundation, with the
`fridaynightskate` Radix 6 subtheme (Starry Night edition) as the site's visual layer
instead of Mercury. Canvas provides the content authoring and page-building experience;
Radix provides the Bootstrap 5 frontend. They are complementary, not competing.

**Guiding principle:** A clean DrupalCMS 2 installation is the starting point. The
custom modules and Radix subtheme from v1 are ported forward on top of that clean base.
All changes happen in v2 only.

---

## Legend

- **Priority:** P1 (blocker), P2 (required), P3 (nice to have)
- **Labels:** `setup`, `module`, `theme`, `config`, `test`, `ddev`
- **Depends On:** issue numbers this issue must follow

---

## Issue List

---

### #0 — [setup] Drop database and run fresh DrupalCMS 2 installation

**Priority:** P1  
**Labels:** `setup`, `ddev`  

The current v2 database contains Mercury demo content that was used purely for
familiarisation with DrupalCMS 2 and Canvas. Drop it and reinstall clean before any
development work begins.

**Steps:**
1. Confirm Composer dependencies are committed/current: `ddev composer install`
2. Drop and recreate the database:
   ```bash
   ddev drush sql:drop --yes
   ```
3. Run the DrupalCMS 2 installer. Use the **Starter** template (Mercury-based) as the
   install-time baseline — it gives a clean Canvas + DrupalCMS 2 foundation without the
   Byte-specific blog/landing-page opinion. Mercury will then be replaced by the Radix
   subtheme in #6/#7.
   ```bash
   ddev drush site:install --existing-config --yes
   # OR if not using config sync:
   ddev drush site:install cms --yes
   ddev drush recipe recipes/drupal_cms_starter
   ```
4. Set an admin password: `ddev drush user:password admin "<secure-password>"`
5. Verify: `ddev launch` — site should load with Mercury as the default theme and the
   Canvas home page at `/home`.

**Notes:**
- The Starter recipe installs: admin UI, anti-spam, authentication, media, privacy,
  SEO basics, easy email, Mercury theme, Canvas, and core recommended config.
- The Mercury demo content that was in the DB is not ported — it was exploration
  material only. This is by design.
- After this issue is closed, the site is a clean DrupalCMS 2 + Canvas + Mercury
  baseline. All subsequent issues build on top of this clean state.
- Keep Mercury installed even after switching the default theme to `fridaynightskate`;
  it may be referenced internally by Canvas SDC components.

**Acceptance criteria:**
- `ddev drush status` shows a healthy Drupal installation.
- `/home` loads the Canvas home page under Mercury.
- No demo content exists in the database.
- Admin login works.

---

### #1 — [setup] Add missing Composer dependencies to v2

**Priority:** P1  
**Labels:** `setup`  

v1 used several packages that v2 does not yet have. These are required by the custom
modules and the Radix subtheme.

Add to `composer.json` → `require`:

```bash
ddev composer require \
  drupal/radix:^6 \
  drupal/geofield:^1 \
  drupal/file_upload_secure_validator:^2 \
  google/apiclient:^2 \
  php-ffmpeg/php-ffmpeg:^1
```

**Notes:**
- `drupal/pathauto` is already present in v2 contrib — skip.
- `drupal/radix` is the base theme for `fridaynightskate`. v1 used `^6.0.2` (Bootstrap 5 subtheme, starterkit generator).
- `drupal/geofield` is required by `fns_archive` for the `field_gps_coordinates` field.
- `drupal/file_upload_secure_validator` is required by the custom `videojs_media` module.
- `google/apiclient ^2` is required by `skating_video_uploader` for YouTube OAuth/upload.
- `php-ffmpeg/php-ffmpeg ^1` is required by `skating_video_uploader` → `VideoProcessor` service. Confirm actual usage in `VideoProcessor.php` — if only `ffprobe` CLI is invoked directly, this dependency may be removable in a future cleanup.
- `drupal/videojs_mediablock` (the contrib module) was in v1's composer but the custom `videojs_media` module does NOT depend on it; it was likely vestigial in v1. Do NOT add it to v2.

**Acceptance criteria:**
- `ddev composer install` succeeds with no errors.
- `vendor/drupal/radix`, `vendor/google/apiclient`, `vendor/php-ffmpeg` directories exist.

---

### #2 — [ddev] Ensure ffmpeg/ffprobe available in DDEV container

**Priority:** P1  
**Labels:** `ddev`  
**Depends On:** #1

`skating_video_uploader` → `VideoProcessor` and `MetadataExtractor` services may
invoke `ffprobe` for video metadata extraction. DDEV's default PHP container does not
include ffmpeg/ffprobe.

Add to `.ddev/config.yaml` (or a custom `.ddev/docker-compose.ffmpeg.yaml`):

```yaml
webimage_extra_packages:
  - ffmpeg
```

Then `ddev restart`.

**Acceptance criteria:**
- `ddev exec ffprobe -version` returns a valid version string.
- `ddev exec ffmpeg -version` returns a valid version string.

---

### #3 — [module] Port `videojs_media` custom module

**Priority:** P1  
**Labels:** `module`  
**Depends On:** #1

The `videojs_media` module is a custom full content-entity module providing VideoJS
media player types (`local_video`, `local_audio`, `remote_video`, `remote_audio`,
`youtube`). It is a direct dependency of `skating_video_uploader` and `fns_archive`.

**Steps:**
1. Copy `web/modules/custom/videojs_media/` from v1 into v2's `web/modules/custom/`.
2. Exclude `node_modules/` and `package-lock.json` from the copy (source assets only).
3. Update `videojs_media.info.yml` → `core_version_requirement: ^11.3` if needed.
4. Run `ddev drush cr` and verify the module is recognized: `ddev drush pm:list | grep videojs`.
5. Enable: `ddev drush pm:enable videojs_media -y`.
6. Run `ddev drush updb -y`.

**Key files:**
- `src/Entity/VideoJsMedia.php` — content entity definition
- `src/Entity/VideoJsMediaType.php` — bundle config entity
- `src/Plugin/Block/VideoJsMediaBlock.php` — block plugin
- `src/Access/VideoJsMediaAccessControlHandler.php`
- `config/install/` — 41 config files covering all entity types, field storage, view displays, view modes

**Notes:**
- The module does NOT depend on contrib `drupal/videojs_mediablock` — it is entirely self-contained.
- `file_upload_secure_validator` is in the `dependencies:` list in `videojs_media.info.yml`; ensure it is enabled after #1.

**Acceptance criteria:**
- Module enables without errors.
- `ddev drush config:get core.entity_type.videojs_media` shows entity type registered.
- `/admin/structure/videojs-media-types` loads and shows the 5 bundle types.

---

### #4 — [module] Port `fns_archive` custom module

**Priority:** P1  
**Labels:** `module`  
**Depends On:** #1, #3

`fns_archive` provides the `archive_media` node type (the core content type for the
site), the `skate_dates` taxonomy vocabulary, three Views, image styles, responsive
image styles, a content moderation workflow (`archive_review`), and the `moderator` /
`skater` user roles.

**Steps:**
1. Copy `web/modules/custom/fns_archive/` from v1 into v2's `web/modules/custom/`.
2. Update `fns_archive.info.yml` → `core_version_requirement: ^11.3`.
3. Enable prerequisites first: `ddev drush pm:enable content_moderation workflows geofield pathauto responsive_image -y`
4. Enable: `ddev drush pm:enable fns_archive -y`.
5. Run `ddev drush updb -y`.

**Ships via `config/install/`:**
- `node.type.archive_media` — content type
- `taxonomy.vocabulary.skate_dates` — vocabulary
- `field.storage.node.field_archive_media` (media reference)
- `field.storage.node.field_gps_coordinates` (geofield)
- `field.storage.node.field_metadata` (text/long)
- `field.storage.node.field_skate_date` (taxonomy term reference)
- `field.storage.node.field_timestamp` (datetime)
- `field.storage.node.field_uploader` (user reference)
- Entity form & view displays for default, teaser, thumbnail, modal view modes
- `views.view.archive_by_date` — Masonry grid archive filtered by Skate Date taxonomy
- `views.view.moderation_dashboard` — Moderator review queue
- `views.view.my_archive_content` — Per-user submission management
- `workflows.workflow.archive_review` — content moderation workflow
- `user.role.moderator` and `user.role.skater`
- Image styles: `archive_full`, `archive_large`, `archive_medium`, `archive_thumbnail`
- `responsive_image.styles.archive_responsive`
- `pathauto.pattern.archive_media_pattern`

**Notes:**
- The `archive_by_date` view depends on the `thumbnail` view mode — verify it is
  installed by checking `core.entity_view_mode.node.thumbnail` config.
- `geofield` must be enabled before `fns_archive` installs, otherwise the field
  storage install will fail.
- `src/Service/ModerationNotifier.php` fires on workflow transitions; verify the
  service definition in `fns_archive.services.yml` wires up correctly.

**Acceptance criteria:**
- `archive_media` content type visible at `/admin/structure/types`.
- `skate_dates` vocabulary visible at `/admin/structure/taxonomy`.
- All three Views visible at `/admin/structure/views`.
- `archive_review` workflow visible at `/admin/config/workflow/workflows`.
- `/admin/people/roles` shows `moderator` and `skater` roles.

---

### #5 — [module] Port `skating_video_uploader` custom module

**Priority:** P2  
**Labels:** `module`  
**Depends On:** #1, #2, #3

`skating_video_uploader` provides GPS + timecode metadata extraction from video/image
files, a bulk upload form, YouTube OAuth settings, and a YouTube upload service. It
depends directly on the custom `videojs_media` module.

**Steps:**
1. Copy `web/modules/custom/skating_video_uploader/` from v1 into v2.
2. Update `skating_video_uploader.info.yml` → `core_version_requirement: ^11.3`.
3. Enable: `ddev drush pm:enable skating_video_uploader -y`.
4. Run `ddev drush updb -y`.
5. Configure YouTube API credentials at `/admin/config/skating-video-uploader/youtube-settings`.

**Key services:**
- `src/Service/MetadataExtractor.php` — GPS from EXIF (PHP native `exif_read_data()`), video metadata via ffprobe
- `src/Service/VideoProcessor.php` — pre-upload video processing
- `src/Service/YouTubeUploader.php` — YouTube Data API v3 via `google/apiclient`
- `src/Form/YouTubeSettingsForm.php` — stores API key / OAuth client credentials
- `src/Form/BulkUploadForm.php` — multi-file upload UI
- `src/Controller/YouTubeAuthController.php` — OAuth callback handler

**Notes:**
- `google/apiclient ^2.15` must be in vendor (see #1).
- YouTube OAuth redirect URI must be registered in Google Cloud Console for the v2 dev
  domain (`https://<ddev-hostname>/skating-video-uploader/youtube-auth`).
- `MetadataExtractor` uses PHP's built-in `exif_read_data()` for image GPS — no FFmpeg
  needed for images.
- `MetadataExtractor` calls `ffprobe` for video GPS/timecode tags — requires #2.
- The config/install directory is empty (no shipped config); all config is runtime.

**Acceptance criteria:**
- Module enables without errors.
- `/admin/config/skating-video-uploader/youtube-settings` loads.
- `/skating-video-uploader/bulk-upload` loads for authenticated users.
- `MetadataExtractor::extractFromImage()` returns GPS coordinates for a test JPEG with GPS EXIF.

---

### #6 — [theme] Add `drupal/radix` and port `fridaynightskate` Radix subtheme

**Priority:** P1  
**Labels:** `theme`  
**Depends On:** #1

The `fridaynightskate` theme is a Radix 6 starterkit subtheme built on Bootstrap 5 with
a "Starry Night" aesthetic (Van Gogh-inspired). It uses webpack/laravel-mix as the build
toolchain. The compiled assets already exist in `build/`.

**Steps:**
1. After #1 installs `drupal/radix ^6`, verify `web/themes/contrib/radix/` exists.
2. Copy `web/themes/custom/fridaynightskate/` from v1 into v2's `web/themes/custom/`.
   - Exclude `node_modules/` from the copy.
3. Run `ddev drush theme:enable fridaynightskate -y`.
4. Set as default: update system.theme config (see #7) or run:
   ```bash
   ddev drush config:set system.theme default fridaynightskate -y
   ```
5. Run `ddev drush cr`.

**Theme asset pipeline (for future CSS/JS changes):**
```bash
cd web/themes/custom/fridaynightskate
npm install
npm run production
```
The `build/` directory from v1 contains already-compiled CSS/JS and can be used
immediately without running the build. Do NOT commit `node_modules/`.

**Key components:**
- `src/scss/components/_starry-night.scss` — animated star field background
- `src/scss/components/_archive-masonry.scss` — Masonry grid styles for archive view
- `src/scss/components/_modal-viewer.scss` — modal overlay for archive item detail
- `src/js/starry-night.js` — canvas-based animated starfield
- `src/js/archive-masonry.js` — Masonry.js + Swiper integration for archive grid
- `src/js/modal-viewer.js` — AJAX modal viewer for archive items
- 57 Twig templates (blocks, forms, menus, node, page, views, navigation, etc.)

**Notes:**
- The compiled `build/` directory from v1 is safe to copy directly; CSS references
  Google Fonts (Raleway, Cormorant Garamond) via external URL in libraries.
- `fridaynightskate.breakpoints.yml` defines responsive breakpoints used by the
  responsive image styles in `fns_archive` — copy this file as well.
- Keep `web/themes/contrib/mercury` installed; it may be used by Canvas or admin UI internally.

**Acceptance criteria:**
- `ddev drush theme:list` shows `fridaynightskate (enabled, default)`.
- Front page renders with the Starry Night aesthetic, not the Mercury default.
- No theme-related PHP errors in `ddev drush watchdog:show`.

---

### #7 — [config] Set `fridaynightskate` as default theme; keep admin theme as `gin`

**Priority:** P1  
**Labels:** `config`  
**Depends On:** #0, #6

After a fresh Starter-recipe install (#0), Mercury is the default theme. After copying
the Radix subtheme (#6), switch the frontend default to `fridaynightskate`. The Gin
admin theme set by DrupalCMS 2 must remain untouched.

```bash
ddev drush config:set system.theme default fridaynightskate -y
ddev drush cr
```

Verify at `/admin/appearance`.

**Notes:**
- Starting from the Starter recipe (#0), `system.theme.default` will be `mercury`.
  This is the expected state to switch from.
- Do NOT change `system.theme.admin` — keep `gin` as DrupalCMS 2 sets it.
- Do NOT uninstall Mercury — leave it installed. Canvas may reference Mercury SDC
  components internally, and it costs nothing to leave it present.

**Acceptance criteria:**
- `/admin/appearance` shows `fridaynightskate` as the default theme.
- Admin pages still use the Gin theme.

---

### #8 — [theme] Audit Canvas page builder compatibility with Radix subtheme

**Priority:** P2  
**Labels:** `theme`, `config`  
**Depends On:** #0, #6, #7

**Intent:** Canvas is the page-building layer; `fridaynightskate` (Radix/Bootstrap 5) is
the visual/frontend layer. They sit at different levels of the stack and are designed
to coexist. Canvas renders page component trees into the active theme's `content`
region. The `fridaynightskate` theme already defines a `content` region. The goal of
this issue is to confirm the handshake works cleanly and tune anything that doesn't.

**Background from exploration:** The current v2 Mercury install gave a working reference
for how Canvas pages look in a DrupalCMS 2 Starter context. That observation informs
this audit even though the Mercury content will be dropped in #0.

**Checklist:**
- [ ] Canvas home page (`/home`) renders correctly inside the Radix page template.
- [ ] `page--front.html.twig` in the fridaynightskate theme does not conflict with
      Canvas's front-page output. If it renders a custom starry-night layout that
      swallows the Canvas component tree, update the template to output `{{ page.content }}`
      as its primary content slot and layer the Starry Night aesthetics around it.
- [ ] Canvas's SDC (Single Directory Components) inject their own markup and CSS — they
      are theme-agnostic. Verify no SDC assumes Mercury-specific CSS class names or
      layout tokens. If any do, add a thin compatibility layer in the Radix subtheme.
- [ ] Canvas component enable/disable state: the Starter recipe disables several Canvas
      components that are not useful for frontend pages (dashboard blocks, admin nav
      blocks, etc.). Review the full component list at `/admin/structure/canvas/components`
      and disable anything that should not appear on public-facing Radix-themed pages.
- [ ] Admin toolbar (Gin + Navigation module) renders correctly over the Radix theme
      without z-index or layout conflicts.
- [ ] Block layout (`/admin/structure/block`) — since Mercury's block assignments are
      wiped in the fresh install (#0), place blocks fresh against the `fridaynightskate`
      regions: `navbar_branding`, `navbar_left`, `navbar_right`, `header`, `content`,
      `page_bottom`, `footer`.

**Notes:**
- Mercury stays installed (per #7 notes) but is not the default — its block assignments
  are irrelevant to production pages.
- The Starry Night aesthetic (animated canvas starfield, night-sky palette) lives
  entirely in the Radix subtheme's JS/CSS. Canvas has no visibility into it and
  will not interfere with it.

**Acceptance criteria:**
- `/home` (Canvas front page) renders without PHP errors.
- Site-wide blocks (nav, footer, branding, messages) appear in correct regions.
- Canvas edit mode is accessible and functional for content editors.

---

### #9 — [config] Verify pathauto pattern for `archive_media` nodes

**Priority:** P2  
**Labels:** `config`  
**Depends On:** #4

`fns_archive` ships `pathauto.pattern.archive_media_pattern.yml` in `config/optional/`.
The v2 site already has `pathauto` installed in contrib. Verify the pattern is active
after enabling `fns_archive`.

```bash
ddev drush config:get pathauto.pattern.archive_media_pattern
```

If it did not auto-import (optional config depends on pathauto being enabled):
```bash
ddev drush config:import --partial --source=web/modules/custom/fns_archive/config/optional -y
```

**Acceptance criteria:**
- `archive_media` nodes receive auto-generated aliases on save.

---

### #10 — [config] Block layout — archive view and navigation blocks

**Priority:** P2  
**Labels:** `config`  
**Depends On:** #4, #8

Place the key site blocks in the `fridaynightskate` theme's regions via
`/admin/structure/block` or config export. Minimum block assignments:

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

The `archive_by_date` View page (`/archive`) and its Masonry grid are rendered as
a full page View (not a block), so no additional block placement is needed for it.

**Acceptance criteria:**
- Navigation renders in the correct navbar regions.
- Status messages display.
- Footer menu renders.

---

### #11 — [test] Verify Views: `archive_by_date`, `moderation_dashboard`, `my_archive_content`

**Priority:** P2  
**Labels:** `test`  
**Depends On:** #4, #6

All three Views are installed by `fns_archive`. Run functional checks:

**`archive_by_date`** (`/archive/<skate-date-term>`)
- [ ] View page loads at the expected path.
- [ ] Empty state renders without errors (no archive_media nodes yet).
- [ ] Masonry grid layout library (`fridaynightskate/masonry-archive`) attaches correctly.
- [ ] Taxonomy term filter works (URL argument).

**`moderation_dashboard`** (`/admin/content/moderation`)
- [ ] View accessible to `moderator` role.
- [ ] Shows pending/draft nodes for review.

**`my_archive_content`** (`/user/{user}/archive`)
- [ ] View accessible to `skater` role.
- [ ] Shows only the current user's submissions.

**Acceptance criteria:**
- All three View pages load without PHP errors.
- Access control is correct per role.

---

### #12 — [test] Masonry archive grid and modal viewer JavaScript

**Priority:** P2  
**Labels:** `test`  
**Depends On:** #4, #6, #11

The archive grid uses custom Masonry.js + Swiper JS (compiled into `build/js/archive-masonry.js`) and a custom AJAX modal viewer (`build/js/modal-viewer.js`).

**Steps:**
1. Create at least one `archive_media` node with a Skate Date term.
2. Navigate to the archive view page.
3. Verify Masonry layout activates — items should arrange in a Pinterest-style grid.
4. Click an archive item — verify the modal opens and displays the item detail with the VideoJS media player.
5. Close the modal — verify focus returns correctly.
6. Test on mobile viewport — Masonry should degrade gracefully.

**Notes:**
- `fridaynightskate/masonry-archive` library is declared in `fridaynightskate.libraries.yml`.
- `fridaynightskate/modal-viewer` library is declared in `fridaynightskate.libraries.yml`.
- If these JS files reference paths that changed (e.g., VideoJS assets), update paths.
- The `node--archive-media--thumbnail.html.twig` template in the theme drives the
  grid item markup — verify it still matches what `archive-masonry.js` expects.

**Acceptance criteria:**
- Masonry grid renders and reflows correctly.
- Modal opens/closes without JS errors in browser console.
- VideoJS player plays in modal.

---

### #13 — [test] MetadataExtractor — GPS EXIF extraction from images

**Priority:** P2  
**Labels:** `test`  
**Depends On:** #5

`MetadataExtractor::extractFromImage()` uses PHP's `exif_read_data()` to pull GPS
coordinates from JPEG files. This is a pure PHP operation requiring no external
binaries.

**Steps:**
1. Enable `php-exif` in DDEV if not already active (check `ddev exec php -m | grep exif`).
2. Upload a JPEG that contains GPS EXIF data via the Bulk Upload form.
3. Verify `field_gps_coordinates` on the resulting `archive_media` node is populated.
4. Verify `field_metadata` JSON blob is populated with extracted metadata.

**Notes:**
- PHP EXIF extension is typically enabled in DDEV's PHP 8.3 image; no extra config needed.
- Test images with known GPS data can be generated with `exiftool` on the host.

**Acceptance criteria:**
- `field_gps_coordinates` shows correct lat/lon after image upload.
- `field_metadata` stores the full EXIF JSON blob.

---

### #14 — [test] MetadataExtractor — GPS/timecode extraction from video files

**Priority:** P2  
**Labels:** `test`  
**Depends On:** #2, #5

`MetadataExtractor` uses `ffprobe` (via CLI or `php-ffmpeg` library) to extract GPS
location tags and timecode from video format metadata.

**Steps:**
1. Confirm #2 is complete (`ddev exec ffprobe -version`).
2. Upload a GoPro-style MP4 with embedded GPS tags via the Bulk Upload form.
3. Verify `field_gps_coordinates` is populated after video processing.
4. Verify `field_timestamp` is populated from video timecode.

**Notes:**
- GoPro and DJI cameras embed GPS in a proprietary metadata track; `ffprobe` can
  read this from the `format_tags` section as `location` or `com.apple.quicktime.location.ISO6709`.
- `MetadataExtractor.php` line ~165 parses `location` tags in ISO 6709 format
  (e.g., `+35.6812+139.7671/`).
- If no GPS-tagged video is available for testing, `exiftool -geotag` can inject
  fake GPS data into a test video.

**Acceptance criteria:**
- GPS coordinates extracted from at least one test video.
- No PHP fatal errors in `ddev drush watchdog:show` after video upload.

---

### #15 — [test] skating_video_uploader — YouTube OAuth flow

**Priority:** P3  
**Labels:** `test`  
**Depends On:** #5

The YouTube uploader requires a valid Google Cloud project with:
- YouTube Data API v3 enabled
- OAuth 2.0 client credentials (Client ID + Secret)
- The v2 dev domain added as an authorized redirect URI

**Steps:**
1. Navigate to `/admin/config/skating-video-uploader/youtube-settings`.
2. Enter Google OAuth Client ID and Client Secret.
3. Initiate the OAuth flow — verify redirect to Google consent screen.
4. After authorization, verify token is stored.
5. Attempt a test upload via `/skating-video-uploader/bulk-upload`.

**Notes:**
- This requires real Google API credentials; keep them out of version control.
- Use Drupal's Key module (already installed in v2 contrib!) to store secrets safely
  instead of plain config. Consider updating `YouTubeSettingsForm` to use `key.key`
  entities for credentials — file a separate issue if this refactor is desired.

**Acceptance criteria:**
- OAuth flow completes without errors.
- A test video uploads to YouTube and the resulting YouTube ID is saved on the entity.

---

### #16 — [test] VideoJS media player — all view modes and bundle types

**Priority:** P2  
**Labels:** `test`  
**Depends On:** #3, #12

The `videojs_media` module ships five bundle types with teaser and default view modes
for each. Verify all render correctly.

**Matrix:**

| Bundle | View Mode | Pass? |
|---|---|---|
| `local_video` | default | |
| `local_video` | teaser | |
| `local_audio` | default | |
| `local_audio` | teaser | |
| `remote_video` | default | |
| `remote_video` | teaser | |
| `remote_audio` | default | |
| `remote_audio` | teaser | |
| `youtube` | default | |
| `youtube` | teaser | |

**Notes:**
- The VideoJS player JS/CSS is bundled within the `videojs_media` module's `assets/`
  directory — verify paths are correct after the copy.
- Check `videojs_media.module` for any `hook_library_info_build()` or
  `hook_libraries_info()` altered paths.

**Acceptance criteria:**
- All 10 combinations render without JS console errors.
- VideoJS player controls display and respond to play/pause.

---

### #17 — [config] DrupalCMS 2 recipe additions — install missing v1 content type recipes

**Priority:** P2  
**Labels:** `config`  
**Depends On:** #4

v1 had several DrupalCMS 1 content type recipes that v2's `byte` template does not
include by default. Review which are needed and install via the Recipe Installer or
directly with Drush:

| v1 Recipe | Status in v2 | Action |
|---|---|---|
| `drupal_cms_blog` | Not in v2 `byte` | Apply if blog is wanted |
| `drupal_cms_events` | Not in v2 `byte` | Apply if events are wanted |
| `drupal_cms_news` | Not in v2 `byte` | Apply if news is wanted |
| `drupal_cms_page` | Not in v2 `byte` | Apply — basic pages likely needed |
| `drupal_cms_person` | Not in v2 `byte` | Apply if team/people section wanted |
| `drupal_cms_project` | Not in v2 `byte` | Apply if portfolio section wanted |
| `drupal_cms_case_study` | Not in v2 `byte` | Apply if case studies wanted |
| `drupal_cms_search` | Not in v2 `byte` (`byte` includes it via sub-recipe) | Verify |
| `drupal_cms_ai` | In v2 | Already installed |

Apply recipes with:
```bash
ddev drush recipe recipes/drupal_cms_page
ddev drush recipe recipes/drupal_cms_blog
# etc.
```

**Notes:**
- The `archive_media` content type (the FNS-specific type) is handled by `fns_archive`
  (issue #4) — it is NOT a DrupalCMS recipe.
- Each recipe application must be followed by `ddev drush updb -y && ddev drush cr`.

**Acceptance criteria:**
- Desired content types visible at `/admin/structure/types`.
- Canvas page builder can create and edit pages using the new content types.

---

### #18 — [phpcs] Run Drupal coding standards against all ported custom modules

**Priority:** P2  
**Labels:** `test`  
**Depends On:** #3, #4, #5

After porting the three custom modules, run PHPCS to catch any issues introduced by
copying from v1 (different core version, different contrib API).

```bash
echo "=== PHPCS ===" && \
vendor/bin/phpcs --standard=Drupal,DrupalPractice \
  web/modules/custom/videojs_media \
  web/modules/custom/fns_archive \
  web/modules/custom/skating_video_uploader \
  2>&1 | head -100
```

Address all errors. Warnings may be tracked separately.

**Common issues to expect:**
- Missing PHPDoc on methods added post-PHPCS baseline.
- Deprecated `\Drupal::` static calls where DI is possible.
- `t()` calls outside class context.

**Acceptance criteria:**
- Zero PHPCS errors across all three modules.
- Warning count documented for future cleanup.

---

### #19 — [test] PHPUnit test suites for ported modules

**Priority:** P3  
**Labels:** `test`  
**Depends On:** #3, #4, #5

v1 had test files in each custom module's `tests/` directory. Port and run them.

```bash
# From project root
vendor/bin/phpunit web/modules/custom/fns_archive/tests/ --testdox
vendor/bin/phpunit web/modules/custom/videojs_media/tests/ --testdox
vendor/bin/phpunit web/modules/custom/skating_video_uploader/tests/ --testdox
```

Fix any test failures caused by Drupal 11.3 API changes or changed contrib versions.

**Notes:**
- `fns_archive/tests/` had moderation and responsive image test files (referenced in
  `TESTING.md`, `TESTING_MODERATION.md`, `TESTING_RESPONSIVE_IMAGES.md` from v1).
- Update the `phpunit.xml` (if any) to match v2's project paths.

**Acceptance criteria:**
- All previously-passing test cases continue to pass.
- New test failures are either fixed or filed as separate issues.

---

### #20 — [theme] Rebuild `fridaynightskate` frontend assets for v2

**Priority:** P3  
**Labels:** `theme`  
**Depends On:** #6

The compiled `build/` directory from v1 is functional, but the npm build should be run
fresh in the v2 context to ensure no stale asset references.

```bash
cd web/themes/custom/fridaynightskate
npm install
npm run production
```

Review `webpack.mix.js` for any paths that may need updating (e.g., references to
`node_modules/` paths for Bootstrap, Masonry, Swiper).

**Notes:**
- `package.json` uses `laravel-mix` + `webpack`. Node >=16, npm >=6 required.
- Bootstrap 5.3, Masonry.js, and Swiper are npm devDependencies — they will be
  resolved from `node_modules/` after `npm install`.
- Do NOT commit `node_modules/` to the repo; add to `.gitignore` if not already.

**Acceptance criteria:**
- `npm run production` exits with code 0.
- `build/css/main.style.css` and `build/js/*.js` are regenerated.
- No visual regressions compared to v1.

---

### #21 — [config] Starry Night theme — configure CSS custom properties and breakpoints

**Priority:** P3  
**Labels:** `theme`, `config`  
**Depends On:** #6, #7, #8

The Starry Night Radix subtheme uses CSS custom properties (variables) for the palette
(deep night sky, golden accents, swirling gradients). The `fridaynightskate.breakpoints.yml`
defines custom breakpoints used by `responsive_image.styles.archive_responsive`.

**Steps:**
1. Verify `fridaynightskate.breakpoints.yml` was copied correctly.
2. Confirm `responsive_image.styles.archive_responsive` references the correct breakpoint names.
3. Check that the Canvas home page and any new DrupalCMS 2 pages render acceptably with
   the night sky colour palette — adjust variables in `_variables.scss` if needed.

**Acceptance criteria:**
- No broken image style references in Drupal logs.
- The site's visual tone is the Starry Night aesthetic throughout.

---

### #22 — [config] Key module — store YouTube API credentials securely

**Priority:** P3  
**Labels:** `config`, `setup`  
**Depends On:** #5

v2 already has the `key` module installed. Rather than storing the Google OAuth client
ID and secret in Drupal config (which would be editable via the UI and potentially
exported to VCS), store them as `key` entities and update `YouTubeSettingsForm` to
reference them.

**Steps:**
1. Create two Key entities: `youtube_client_id` and `youtube_client_secret` at
   `/admin/config/system/keys`.
2. Update `skating_video_uploader` → `YouTubeSettingsForm` to use `\Drupal\key\KeyRepositoryInterface`
   to retrieve credentials instead of direct config storage.
3. Never export key values to `config/sync/`.

**Acceptance criteria:**
- YouTube credentials are not stored in config YAML.
- `key.key.youtube_client_id` and `key.key.youtube_client_secret` entities load the correct values.

---

## Migration Order (suggested)

```
#0 (fresh install — drop DB, Starter recipe, Mercury baseline)
  → #1 (Composer deps: radix, geofield, file_upload_secure_validator, google/apiclient, php-ffmpeg)
      → #2 (DDEV ffmpeg)
      → #3 (videojs_media module)
          → #4 (fns_archive module)
              → #9 (pathauto pattern)
              → #11 (Views)
              → #17 (recipe additions)
      → #5 (skating_video_uploader module)
          → #13 (metadata test: images)
          → #14 (metadata test: video)
          → #15 (YouTube OAuth)
      → #6 (theme copy: fridaynightskate Radix subtheme)
          → #7 (set fridaynightskate as default, keep Mercury installed)
              → #8 (Canvas + Radix compatibility audit)
              → #10 (block layout in Radix regions)
              → #12 (Masonry archive grid + modal viewer JS)
              → #21 (breakpoints/CSS vars for Starry Night)
          → #20 (rebuild npm assets)
#16 (VideoJS view modes) — after #3 + #12
#18 (PHPCS) — after #3 + #4 + #5
#19 (PHPUnit) — after #18
#22 (Key module creds) — after #15
```

---

## Out of Scope

- **Current v2 Mercury demo content** — intentionally discarded in #0. It was
  exploration/learning material only.
- **Content migration** — no content or users to migrate from v1 (confirmed). v1 never
  left development.
- **Drupal CMS 1 recipes** — not porting individual v1 recipe YAMLs; v2 recipe system
  is the canonical base.
- **`drupal/videojs_mediablock` contrib module** — not needed; the custom `videojs_media`
  module is a full self-contained replacement.
- **v1 documentation markdown files** (`BUGS_FOUND.md`, `IMPLEMENTATION_SUMMARY.md`,
  `MODAL_VIEWER_ARCHITECTURE.md`, etc.) — these were v1 development notes and are
  superseded by this document and future GitHub issues.
- **Mercury as a visual theme** — Mercury stays installed (for Canvas SDC safety) but
  is never the user-facing theme. The Radix subtheme is the visual layer from day one
  of development.
