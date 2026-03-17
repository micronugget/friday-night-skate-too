# Migration from videojs_mediablock to videojs_media

This document outlines your options for migrating existing `videojs_mediablock` Block Content entities to the new `videojs_media` Content entities with bundles.

## Understanding the Data Transformation

### Old Structure (videojs_mediablock)
- **Entity Type**: `block_content`
- **Bundle**: `videojs_mediablock` (single bundle)
- **Field Logic**: Dropdown selection determines which field is "active"
  - `field_videojs_media_location`: "field_videojs_local" or "field_videojs_remote"
  - `field_videojs_local`: "field_videojs_local_audio_file" or "field_videojs_media_file"
  - `field_videojs_remote`: "field_videojs_remote_audio_file", "field_videojs_remote_media_file", or "field_videojs_youtube"
  - 5 media/URL fields (only 1 is populated per entity)
  - `field_videojs_poster_image`: Shared
  - `field_videojs_subtitle`: Shared

### New Structure (videojs_media)
- **Entity Type**: `videojs_media`
- **Bundles**: 5 separate bundles
  - `local_video` → `field_media_file` (videojs_video)
  - `local_audio` → `field_media_file` (videojs_audio)
  - `remote_video` → `field_remote_url`
  - `remote_audio` → `field_remote_url`
  - `youtube` → `field_youtube_url`
  - All bundles have: `field_poster_image`, `field_subtitle`

### Field Mapping

| Old Field | Old Value | New Bundle | New Field |
|-----------|-----------|------------|-----------|
| field_videojs_media_file | (Media: videojs_video) | local_video | field_media_file |
| field_videojs_local_audio_file | (Media: videojs_audio) | local_audio | field_media_file |
| field_videojs_remote_media_file | (Link URL) | remote_video | field_remote_url |
| field_videojs_remote_audio_file | (Link URL) | remote_audio | field_remote_url |
| field_videojs_youtube | (Link URL) | youtube | field_youtube_url |
| field_videojs_poster_image | (all) | (all bundles) | field_poster_image |
| field_videojs_subtitle | (all) | (all bundles) | field_subtitle |

---

## Migration Options

## Option 1: Drupal Migration API (Recommended)

**Best for**: Large datasets (50+ blocks), production sites, automated migrations

### Advantages
- ✅ **Repeatable**: Can be run multiple times (rollback and re-run)
- ✅ **Auditable**: Full migration tracking and logging
- ✅ **Incremental**: Can migrate in batches (highwater mark support)
- ✅ **Validated**: Drupal handles entity validation automatically
- ✅ **Professional**: Industry-standard approach
- ✅ **Drush integration**: Command-line control

### Implementation Steps

#### 1. Create Migration Module

Create `videojs_media_migrate` module:

```
web/modules/custom/videojs_media_migrate/
├── videojs_media_migrate.info.yml
├── config/install/
│   ├── migrate_plus.migration.videojs_local_video.yml
│   ├── migrate_plus.migration.videojs_local_audio.yml
│   ├── migrate_plus.migration.videojs_remote_video.yml
│   ├── migrate_plus.migration.videojs_remote_audio.yml
│   └── migrate_plus.migration.videojs_youtube.yml
└── src/Plugin/migrate/source/
    └── VideoJsMediablockSource.php
```

#### 2. Module Dependencies

```yaml
# videojs_media_migrate.info.yml
name: 'VideoJS Media Migration'
type: module
description: 'Migrates videojs_mediablock entities to videojs_media'
core_version_requirement: ^10.3 | ^11
package: 'VideoJS'
dependencies:
  - drupal:migrate
  - drupal:migrate_drupal
  - migrate_plus:migrate_plus
  - videojs_media:videojs_media
```

#### 3. Custom Source Plugin

The key is creating a source plugin that determines the bundle:

```php
<?php

namespace Drupal\videojs_media_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d10\Entity;

/**
 * Source plugin for videojs_mediablock entities.
 *
 * @MigrateSource(
 *   id = "videojs_mediablock_source",
 *   source_module = "videojs_mediablock"
 * )
 */
class VideoJsMediablockSource extends Entity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('block_content_field_data', 'bc')
      ->fields('bc')
      ->condition('bc.type', 'videojs_mediablock');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $id = $row->getSourceProperty('id');

    // Load the full block_content entity to access fields.
    $storage = \Drupal::entityTypeManager()->getStorage('block_content');
    $block = $storage->load($id);

    if (!$block) {
      return FALSE;
    }

    // Determine the bundle and active field.
    $media_location = $block->get('field_videojs_media_location')->value;

    if ($media_location === 'field_videojs_local') {
      $local_type = $block->get('field_videojs_local')->value;

      if ($local_type === 'field_videojs_media_file') {
        $row->setSourceProperty('destination_bundle', 'local_video');
        $row->setSourceProperty('media_file', $block->get('field_videojs_media_file')->getValue());
      }
      elseif ($local_type === 'field_videojs_local_audio_file') {
        $row->setSourceProperty('destination_bundle', 'local_audio');
        $row->setSourceProperty('media_file', $block->get('field_videojs_local_audio_file')->getValue());
      }
    }
    elseif ($media_location === 'field_videojs_remote') {
      $remote_type = $block->get('field_videojs_remote')->value;

      if ($remote_type === 'field_videojs_remote_media_file') {
        $row->setSourceProperty('destination_bundle', 'remote_video');
        $row->setSourceProperty('remote_url', $block->get('field_videojs_remote_media_file')->getValue());
      }
      elseif ($remote_type === 'field_videojs_remote_audio_file') {
        $row->setSourceProperty('destination_bundle', 'remote_audio');
        $row->setSourceProperty('remote_url', $block->get('field_videojs_remote_audio_file')->getValue());
      }
      elseif ($remote_type === 'field_videojs_youtube') {
        $row->setSourceProperty('destination_bundle', 'youtube');
        $row->setSourceProperty('youtube_url', $block->get('field_videojs_youtube')->getValue());
      }
    }

    // Get shared fields.
    $row->setSourceProperty('poster_image', $block->get('field_videojs_poster_image')->getValue());
    $row->setSourceProperty('subtitle', $block->get('field_videojs_subtitle')->getValue());
    $row->setSourceProperty('description', $block->get('info')->value);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Block Content ID'),
      'info' => $this->t('Block description/name'),
      'destination_bundle' => $this->t('Destination bundle'),
      'media_file' => $this->t('Media file reference'),
      'remote_url' => $this->t('Remote URL'),
      'youtube_url' => $this->t('YouTube URL'),
      'poster_image' => $this->t('Poster image'),
      'subtitle' => $this->t('Subtitle files'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'bc',
      ],
    ];
  }

}
```

#### 4. Migration Configuration (Example: Local Video)

```yaml
# migrate_plus.migration.videojs_local_video.yml
id: videojs_local_video
label: 'Migrate VideoJS Mediablock to VideoJS Media (Local Video)'
migration_group: videojs_media
migration_tags:
  - videojs
  - content

source:
  plugin: videojs_mediablock_source
  # Only migrate local_video bundle
  constants:
    bundle: local_video

process:
  # Skip if not the right bundle
  skip_row:
    plugin: skip_on_empty
    method: row
    source: destination_bundle

  skip_non_matching:
    plugin: callback
    callable: strcmp
    source:
      - destination_bundle
      - constants/bundle
    # Skip if destination_bundle != local_video

  # Base fields
  type:
    plugin: default_value
    default_value: local_video

  name: info

  status:
    plugin: default_value
    default_value: 1

  uid:
    plugin: default_value
    default_value: 1

  created: created
  changed: changed

  # Bundle-specific field
  field_media_file: media_file

  # Shared fields
  field_poster_image: poster_image
  field_subtitle: subtitle

destination:
  plugin: 'entity:videojs_media'
  default_bundle: local_video

migration_dependencies:
  required: []
  optional: []
```

#### 5. Run Migrations

```bash
# Enable migration modules
drush en migrate migrate_plus migrate_tools videojs_media_migrate

# Check migration status
drush migrate:status --group=videojs_media

# Run migrations
drush migrate:import videojs_local_video
drush migrate:import videojs_local_audio
drush migrate:import videojs_remote_video
drush migrate:import videojs_remote_audio
drush migrate:import videojs_youtube

# Or run all at once
drush migrate:import --group=videojs_media

# Check for issues
drush migrate:messages videojs_local_video

# Rollback if needed
drush migrate:rollback videojs_local_video

# Reset and re-run
drush migrate:reset-status videojs_local_video
drush migrate:import videojs_local_video
```

---

## Option 2: Simplified Migration (Single Configuration)

**Best for**: Simpler approach, fewer files

Create a single migration that handles all bundles with process plugins:

```yaml
# migrate_plus.migration.videojs_mediablock_all.yml
id: videojs_mediablock_all
label: 'Migrate all VideoJS Mediablocks'
source:
  plugin: videojs_mediablock_source

process:
  type: destination_bundle
  name: info
  status:
    plugin: default_value
    default_value: 1

  # Conditional field mapping
  field_media_file:
    -
      plugin: skip_on_empty
      method: process
      source: media_file

  field_remote_url:
    -
      plugin: skip_on_empty
      method: process
      source: remote_url

  field_youtube_url:
    -
      plugin: skip_on_empty
      method: process
      source: youtube_url

  field_poster_image: poster_image
  field_subtitle: subtitle

destination:
  plugin: 'entity:videojs_media'
  # Bundle determined by 'type' process above
```

---

## Option 3: Custom Drush Command

**Best for**: Custom logic, one-time migration, full control

```php
<?php

namespace Drupal\videojs_media_migrate\Commands;

use Drush\Commands\DrushCommands;

/**
 * Drush commands for migrating videojs_mediablock.
 */
class VideoJsMediaMigrateCommands extends DrushCommands {

  /**
   * Migrate videojs_mediablock entities to videojs_media.
   *
   * @command videojs:migrate
   * @aliases vjm
   */
  public function migrate() {
    $storage = \Drupal::entityTypeManager()->getStorage('block_content');
    $new_storage = \Drupal::entityTypeManager()->getStorage('videojs_media');

    $blocks = $storage->loadByProperties(['type' => 'videojs_mediablock']);

    $this->output()->writeln('Found ' . count($blocks) . ' blocks to migrate.');

    foreach ($blocks as $block) {
      $bundle = $this->determineBundle($block);

      if (!$bundle) {
        $this->logger()->warning('Could not determine bundle for block ' . $block->id());
        continue;
      }

      $values = [
        'type' => $bundle,
        'name' => $block->label(),
        'uid' => $block->getOwnerId(),
        'status' => $block->isPublished(),
        'created' => $block->getCreatedTime(),
      ];

      // Map fields based on bundle
      $values = array_merge($values, $this->mapFields($block, $bundle));

      $entity = $new_storage->create($values);
      $entity->save();

      $this->logger()->success('Migrated block ' . $block->id() . ' to ' . $bundle . ' entity ' . $entity->id());
    }
  }

  protected function determineBundle($block) {
    $location = $block->get('field_videojs_media_location')->value;

    if ($location === 'field_videojs_local') {
      $type = $block->get('field_videojs_local')->value;
      return $type === 'field_videojs_media_file' ? 'local_video' : 'local_audio';
    }
    elseif ($location === 'field_videojs_remote') {
      $type = $block->get('field_videojs_remote')->value;

      if ($type === 'field_videojs_remote_media_file') return 'remote_video';
      if ($type === 'field_videojs_remote_audio_file') return 'remote_audio';
      if ($type === 'field_videojs_youtube') return 'youtube';
    }

    return NULL;
  }

  protected function mapFields($block, $bundle) {
    $values = [];

    // Map primary field
    switch ($bundle) {
      case 'local_video':
        $values['field_media_file'] = $block->get('field_videojs_media_file')->getValue();
        break;
      case 'local_audio':
        $values['field_media_file'] = $block->get('field_videojs_local_audio_file')->getValue();
        break;
      case 'remote_video':
        $values['field_remote_url'] = $block->get('field_videojs_remote_media_file')->getValue();
        break;
      case 'remote_audio':
        $values['field_remote_url'] = $block->get('field_videojs_remote_audio_file')->getValue();
        break;
      case 'youtube':
        $values['field_youtube_url'] = $block->get('field_videojs_youtube')->getValue();
        break;
    }

    // Map shared fields
    if (!$block->get('field_videojs_poster_image')->isEmpty()) {
      $values['field_poster_image'] = $block->get('field_videojs_poster_image')->getValue();
    }

    if (!$block->get('field_videojs_subtitle')->isEmpty()) {
      $values['field_subtitle'] = $block->get('field_videojs_subtitle')->getValue();
    }

    return $values;
  }

}
```

Usage:
```bash
drush videojs:migrate
```

---

## Option 4: Database Script (SQL)

**Best for**: Direct database access, very large datasets

**Warning**: Bypasses Drupal validation. Use with caution.

```sql
-- This is a TEMPLATE - requires customization for your specific site

-- Migrate local_video
INSERT INTO videojs_media_field_data (
  type, name, uid, status, created, changed
)
SELECT
  'local_video' as type,
  info as name,
  uid,
  status,
  created,
  changed
FROM block_content_field_data bcd
WHERE type = 'videojs_mediablock'
  AND EXISTS (
    SELECT 1 FROM block_content__field_videojs_media_location loc
    WHERE loc.entity_id = bcd.id
      AND loc.field_videojs_media_location_value = 'field_videojs_local'
  )
  AND EXISTS (
    SELECT 1 FROM block_content__field_videojs_local local
    WHERE local.entity_id = bcd.id
      AND local.field_videojs_local_value = 'field_videojs_media_file'
  );

-- Then map the field data tables...
-- (This gets complex quickly)
```

---

## Comparison Matrix

| Feature | Migration API | Custom Drush | SQL Script |
|---------|---------------|--------------|------------|
| **Complexity** | Medium-High | Medium | High |
| **Setup Time** | 2-4 hours | 1-2 hours | 1 hour |
| **Repeatability** | Excellent | Good | Poor |
| **Rollback** | Built-in | Manual | Very difficult |
| **Validation** | Automatic | Automatic | None |
| **Batch Processing** | Built-in | Custom | N/A |
| **Audit Trail** | Excellent | Custom logging | None |
| **Production Ready** | Yes | Yes | No |
| **Learning Curve** | Steep | Medium | Low |

---

## Recommendation

**For most use cases**: Use **Migration API (Option 1)** because:
- It's the Drupal standard
- Provides rollback capabilities
- Fully auditable
- Can be run incrementally
- Easy to test and re-run
- Works with Drush
- Professional approach

**For quick migrations**: Use **Custom Drush Command (Option 3)** because:
- Faster to implement
- Full control over logic
- Easier to understand
- Still uses Entity API (validated)

**Avoid SQL scripts** unless you have very specific performance requirements and deep database knowledge.

---

## Post-Migration Tasks

After migration:

1. **Verify data**:
   ```bash
   drush sqlq "SELECT type, COUNT(*) FROM videojs_media_field_data GROUP BY type"
   ```

2. **Check field mappings**: Visit a few entities to ensure fields migrated correctly

3. **Update references**: If other content references the old blocks, update those references

4. **Update blocks**: If blocks were placed, replace them with new entity reference blocks

5. **Test functionality**: Ensure VideoJS player works with migrated data

6. **Backup and disable old module**: Keep videojs_mediablock disabled but available for rollback

---

## Need Help?

If you need a working migration module created, I can generate all the necessary files with full source plugins and configurations.
