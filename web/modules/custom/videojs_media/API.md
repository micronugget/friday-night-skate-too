# VideoJS Media - API Documentation

## Table of Contents

- [Entity API Reference](#entity-api-reference)
  - [VideoJsMedia Entity](#videojsmedia-entity)
  - [VideoJsMediaType Config Entity](#videojsmediatype-config-entity)
- [Base Field Definitions](#base-field-definitions)
- [Bundle-Specific Fields](#bundle-specific-fields)
- [Access Control API](#access-control-api)
- [Hook Documentation](#hook-documentation)
- [Services Documentation](#services-documentation)
- [Code Examples](#code-examples)

---

## Entity API Reference

### VideoJsMedia Entity

The `VideoJsMedia` entity is a content entity with bundles that represents a VideoJS media player.

**Namespace**: `Drupal\videojs_media\Entity\VideoJsMedia`

**Interface**: `Drupal\videojs_media\VideoJsMediaInterface`

#### Entity Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Unique entity ID |
| `revision_id` | int | Revision ID |
| `uuid` | string | Universal unique identifier |
| `type` | string | Bundle machine name (local_video, local_audio, remote_video, remote_audio, youtube) |
| `name` | string | Entity label/name |
| `uid` | int | Author user ID |
| `status` | boolean | Published status (TRUE = published) |
| `created` | timestamp | Creation timestamp |
| `changed` | timestamp | Last modified timestamp |
| `langcode` | string | Language code |

#### Public Methods

##### getName()
```php
public function getName(): ?string
```
Returns the name (label) of the VideoJS media item.

**Returns**: The entity name or NULL

**Example**:
```php
$media = \Drupal::entityTypeManager()
  ->getStorage('videojs_media')
  ->load(1);
$name = $media->getName();
// Returns: "My Video Title"
```

---

##### setName(string $name)
```php
public function setName(string $name): VideoJsMediaInterface
```
Sets the name (label) of the VideoJS media item.

**Parameters**:
- `$name` - The name to set

**Returns**: The entity object for method chaining

**Example**:
```php
$media->setName('New Video Title')
  ->save();
```

---

##### getCreatedTime()
```php
public function getCreatedTime(): int
```
Returns the creation timestamp.

**Returns**: Unix timestamp

**Example**:
```php
$timestamp = $media->getCreatedTime();
// Returns: 1706534400
```

---

##### setCreatedTime(int $timestamp)
```php
public function setCreatedTime(int $timestamp): VideoJsMediaInterface
```
Sets the creation timestamp.

**Parameters**:
- `$timestamp` - Unix timestamp

**Returns**: The entity object for method chaining

**Example**:
```php
$media->setCreatedTime(time())
  ->save();
```

---

##### isPublished()
```php
public function isPublished(): bool
```
Checks if the entity is published.

**Returns**: TRUE if published, FALSE otherwise

**Example**:
```php
if ($media->isPublished()) {
  // Display the media
}
```

---

##### setPublished(bool|null $published)
```php
public function setPublished(?bool $published = NULL): VideoJsMediaInterface
```
Sets the published status.

**Parameters**:
- `$published` - TRUE to publish, FALSE to unpublish

**Returns**: The entity object for method chaining

**Example**:
```php
// Publish
$media->setPublished(TRUE)->save();

// Unpublish
$media->setPublished(FALSE)->save();
```

---

##### getOwner()
```php
public function getOwner(): UserInterface
```
Returns the entity owner (author).

**Returns**: User entity

**Example**:
```php
$author = $media->getOwner();
$author_name = $author->getDisplayName();
```

---

##### getOwnerId()
```php
public function getOwnerId(): int
```
Returns the entity owner user ID.

**Returns**: User ID

**Example**:
```php
$uid = $media->getOwnerId();
```

---

##### setOwnerId(int $uid)
```php
public function setOwnerId(int $uid): VideoJsMediaInterface
```
Sets the entity owner user ID.

**Parameters**:
- `$uid` - User ID

**Returns**: The entity object for method chaining

**Example**:
```php
$media->setOwnerId(1)->save();
```

---

### VideoJsMediaType Config Entity

The `VideoJsMediaType` entity is a configuration entity that defines bundles (types) for VideoJS Media.

**Namespace**: `Drupal\videojs_media\Entity\VideoJsMediaType`

#### Methods

##### getDescription()
```php
public function getDescription(): string
```
Returns the bundle description.

**Returns**: Description text

---

##### setDescription(string $description)
```php
public function setDescription(string $description): VideoJsMediaTypeInterface
```
Sets the bundle description.

**Parameters**:
- `$description` - Description text

**Returns**: The config entity object

---

## Base Field Definitions

All VideoJS Media entities have these base fields regardless of bundle:

| Field Name | Field Type | Label | Required | Revisionable | Translatable |
|------------|------------|-------|----------|--------------|--------------|
| `name` | string | Name | Yes | Yes | Yes |
| `status` | boolean | Published | No (default: TRUE) | Yes | Yes |
| `created` | created | Authored on | No | Yes | Yes |
| `changed` | changed | Changed | No | Yes | Yes |
| `uid` | entity_reference (user) | Authored by | No | Yes | No |

---

## Bundle-Specific Fields

### Local Video Bundle (`local_video`)

| Field Name | Field Type | Label | Description |
|------------|------------|-------|-------------|
| `field_media_file` | entity_reference (media) | Media File | References a Media entity of type `videojs_video` |
| `field_poster_image` | entity_reference (media) | Poster Image | Optional thumbnail image via Media Library |
| `field_subtitle` | file | Subtitle | Optional WebVTT caption file |

### Local Audio Bundle (`local_audio`)

| Field Name | Field Type | Label | Description |
|------------|------------|-------|-------------|
| `field_media_file` | entity_reference (media) | Media File | References a Media entity of type `videojs_audio` |
| `field_poster_image` | entity_reference (media) | Poster Image | Optional thumbnail image |
| `field_subtitle` | file | Subtitle | Optional WebVTT caption file |

### Remote Video Bundle (`remote_video`)

| Field Name | Field Type | Label | Description |
|------------|------------|-------|-------------|
| `field_remote_url` | link | Remote URL | URL to remote video file |
| `field_poster_image` | entity_reference (media) | Poster Image | Optional thumbnail image |
| `field_subtitle` | file | Subtitle | Optional WebVTT caption file |

### Remote Audio Bundle (`remote_audio`)

| Field Name | Field Type | Label | Description |
|------------|------------|-------|-------------|
| `field_remote_url` | link | Remote URL | URL to remote audio file |
| `field_poster_image` | entity_reference (media) | Poster Image | Optional thumbnail image |
| `field_subtitle` | file | Subtitle | Optional WebVTT caption file |

### YouTube Bundle (`youtube`)

| Field Name | Field Type | Label | Description |
|------------|------------|-------|-------------|
| `field_youtube_url` | link | YouTube URL | YouTube video URL (standard, short, or embed format) |
| `field_poster_image` | entity_reference (media) | Poster Image | Optional custom thumbnail (overrides YouTube default) |
| `field_subtitle` | file | Subtitle | Optional WebVTT caption file |

---

## Access Control API

VideoJS Media uses Drupal's entity access control system with granular per-bundle permissions.

### Permission Pattern

Permissions follow this pattern: `{operation} {own|any} {bundle} videojs media`

**Operations**: `create`, `view`, `edit`, `delete`

**Scope**: `own` (entities authored by current user) or `any` (all entities)

### Administrative Permissions

| Permission | Description | Restrict Access |
|------------|-------------|-----------------|
| `administer videojs media` | Full administrative access | Yes |
| `administer videojs media types` | Manage bundles (types) | Yes |

### Per-Bundle Permissions

Each of the 5 bundles has these permissions:

| Permission Pattern | Example (local_video) |
|-------------------|----------------------|
| `create {bundle} videojs media` | `create local_video videojs media` |
| `edit own {bundle} videojs media` | `edit own local_video videojs media` |
| `edit any {bundle} videojs media` | `edit any local_video videojs media` |
| `delete own {bundle} videojs media` | `delete own local_video videojs media` |
| `delete any {bundle} videojs media` | `delete any local_video videojs media` |
| `view {bundle} videojs media` | `view local_video videojs media` |
| `view unpublished {bundle} videojs media` | `view unpublished local_video videojs media` |

### Checking Access Programmatically

```php
use Drupal\videojs_media\Entity\VideoJsMedia;

$media = VideoJsMedia::load(1);
$account = \Drupal::currentUser();

// Check view access
if ($media->access('view', $account)) {
  // User can view this entity
}

// Check edit access
if ($media->access('update', $account)) {
  // User can edit this entity
}

// Check delete access
if ($media->access('delete', $account)) {
  // User can delete this entity
}

// Check create access for a bundle
$access_handler = \Drupal::entityTypeManager()
  ->getAccessControlHandler('videojs_media');

if ($access_handler->createAccess('local_video', $account)) {
  // User can create local_video entities
}
```

---

## Hook Documentation

### Available Hooks

VideoJS Media integrates with standard Drupal entity hooks. You can implement these in your custom module:

#### hook_videojs_media_presave()

Called before a VideoJS Media entity is saved.

```php
/**
 * Implements hook_videojs_media_presave().
 */
function mymodule_videojs_media_presave(VideoJsMediaInterface $entity) {
  // Modify entity before save
  if ($entity->bundle() === 'youtube') {
    // Normalize YouTube URLs
    $url = $entity->get('field_youtube_url')->uri;
    $entity->set('field_youtube_url', ['uri' => normalize_youtube_url($url)]);
  }
}
```

#### hook_videojs_media_insert()

Called after a new VideoJS Media entity is inserted.

```php
/**
 * Implements hook_videojs_media_insert().
 */
function mymodule_videojs_media_insert(VideoJsMediaInterface $entity) {
  // React to new entity
  \Drupal::logger('mymodule')->info('New VideoJS media created: @name', [
    '@name' => $entity->getName(),
  ]);
}
```

#### hook_videojs_media_update()

Called after a VideoJS Media entity is updated.

```php
/**
 * Implements hook_videojs_media_update().
 */
function mymodule_videojs_media_update(VideoJsMediaInterface $entity) {
  // React to entity updates
  if ($entity->isPublished() && !$entity->original->isPublished()) {
    // Entity was just published
    notify_subscribers($entity);
  }
}
```

#### hook_videojs_media_delete()

Called before a VideoJS Media entity is deleted.

```php
/**
 * Implements hook_videojs_media_delete().
 */
function mymodule_videojs_media_delete(VideoJsMediaInterface $entity) {
  // Clean up related data
  \Drupal::database()->delete('mymodule_stats')
    ->condition('entity_id', $entity->id())
    ->execute();
}
```

#### hook_videojs_media_view()

Alter the renderable array for a VideoJS Media entity.

```php
/**
 * Implements hook_videojs_media_view().
 */
function mymodule_videojs_media_view(array &$build, VideoJsMediaInterface $entity, $display, $view_mode) {
  // Add custom elements to the build
  if ($view_mode === 'full') {
    $build['custom_metadata'] = [
      '#markup' => '<div class="video-metadata">Duration: 5:30</div>',
      '#weight' => 10,
    ];
  }
}
```

#### hook_videojs_media_view_alter()

Alter the renderable array after all modules have modified it.

```php
/**
 * Implements hook_videojs_media_view_alter().
 */
function mymodule_videojs_media_view_alter(array &$build, VideoJsMediaInterface $entity, $display) {
  // Final alterations
  $build['#attached']['library'][] = 'mymodule/video-analytics';
}
```

#### hook_videojs_media_access()

Control access to VideoJS Media entities.

```php
/**
 * Implements hook_videojs_media_access().
 */
function mymodule_videojs_media_access(VideoJsMediaInterface $entity, $operation, AccountInterface $account) {
  // Custom access logic
  if ($operation === 'view' && $entity->bundle() === 'youtube') {
    // Allow viewing YouTube videos if user has premium role
    if (in_array('premium', $account->getRoles())) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden('Premium subscription required');
  }
  
  return AccessResult::neutral();
}
```

---

## Services Documentation

VideoJS Media does not currently provide custom services. The module uses Drupal core services:

### Commonly Used Core Services

```php
// Entity Type Manager
$entity_type_manager = \Drupal::entityTypeManager();
$storage = $entity_type_manager->getStorage('videojs_media');

// Access Control Handler
$access_handler = $entity_type_manager->getAccessControlHandler('videojs_media');

// Entity View Builder
$view_builder = $entity_type_manager->getViewBuilder('videojs_media');
```

---

## Code Examples

### Creating Entities Programmatically

#### Create a Local Video

```php
use Drupal\videojs_media\Entity\VideoJsMedia;
use Drupal\media\Entity\Media;

// First, create or load a Media entity with video file
$video_media = Media::create([
  'bundle' => 'videojs_video',
  'name' => 'My Video File',
  'field_media_video_file' => [
    'target_id' => $file_id,
  ],
]);
$video_media->save();

// Create VideoJS Media entity
$videojs_media = VideoJsMedia::create([
  'type' => 'local_video',
  'name' => 'My Local Video',
  'field_media_file' => [
    'target_id' => $video_media->id(),
  ],
  'status' => TRUE,
  'uid' => \Drupal::currentUser()->id(),
]);
$videojs_media->save();

echo "Created VideoJS Media with ID: " . $videojs_media->id();
```

#### Create a YouTube Video

```php
use Drupal\videojs_media\Entity\VideoJsMedia;

$videojs_media = VideoJsMedia::create([
  'type' => 'youtube',
  'name' => 'My YouTube Video',
  'field_youtube_url' => [
    'uri' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
  ],
  'status' => TRUE,
  'uid' => 1,
]);
$videojs_media->save();
```

#### Create a Remote Audio File

```php
use Drupal\videojs_media\Entity\VideoJsMedia;

$videojs_media = VideoJsMedia::create([
  'type' => 'remote_audio',
  'name' => 'My Podcast Episode',
  'field_remote_url' => [
    'uri' => 'https://example.com/podcasts/episode-1.mp3',
  ],
  'status' => TRUE,
]);
$videojs_media->save();
```

### Loading Entities

#### Load a Single Entity

```php
$storage = \Drupal::entityTypeManager()->getStorage('videojs_media');

// Load by ID
$media = $storage->load(1);

if ($media) {
  echo $media->getName();
}
```

#### Load Multiple Entities

```php
$storage = \Drupal::entityTypeManager()->getStorage('videojs_media');

// Load multiple by IDs
$media_items = $storage->loadMultiple([1, 2, 3]);

foreach ($media_items as $media) {
  echo $media->getName() . "\n";
}
```

### Querying Entities

#### Query by Bundle

```php
$storage = \Drupal::entityTypeManager()->getStorage('videojs_media');

// Get all YouTube videos
$query = $storage->getQuery()
  ->condition('type', 'youtube')
  ->condition('status', 1) // Published only
  ->sort('created', 'DESC')
  ->accessCheck(TRUE);

$ids = $query->execute();
$youtube_videos = $storage->loadMultiple($ids);
```

#### Query by Author

```php
$storage = \Drupal::entityTypeManager()->getStorage('videojs_media');

// Get all media by specific user
$query = $storage->getQuery()
  ->condition('uid', 5)
  ->condition('status', 1)
  ->accessCheck(TRUE);

$ids = $query->execute();
$user_media = $storage->loadMultiple($ids);
```

#### Query with Field Conditions

```php
$storage = \Drupal::entityTypeManager()->getStorage('videojs_media');

// Find all local videos with subtitles
$query = $storage->getQuery()
  ->condition('type', 'local_video')
  ->exists('field_subtitle')
  ->accessCheck(TRUE);

$ids = $query->execute();
```

### Updating Entities

```php
$media = VideoJsMedia::load(1);

if ($media) {
  // Update name
  $media->setName('Updated Video Title');
  
  // Update published status
  $media->setPublished(FALSE);
  
  // Update custom field
  $media->set('field_youtube_url', ['uri' => 'https://youtube.com/watch?v=newvideo']);
  
  // Save changes
  $media->save();
}
```

### Deleting Entities

```php
$media = VideoJsMedia::load(1);

if ($media) {
  $media->delete();
  \Drupal::messenger()->addMessage('Media deleted successfully');
}
```

### Rendering Entities

#### Render with Default View Mode

```php
$media = VideoJsMedia::load(1);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('videojs_media');

$build = $view_builder->view($media, 'default');
$output = \Drupal::service('renderer')->render($build);
```

#### Render with Teaser View Mode

```php
$media = VideoJsMedia::load(1);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('videojs_media');

$build = $view_builder->view($media, 'teaser');
```

#### Render Multiple Entities

```php
$media_items = VideoJsMedia::loadMultiple([1, 2, 3]);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('videojs_media');

$build = $view_builder->viewMultiple($media_items, 'teaser');
```

### Working with Bundles

#### Get All Available Bundles

```php
$bundle_storage = \Drupal::entityTypeManager()
  ->getStorage('videojs_media_type');

$bundles = $bundle_storage->loadMultiple();

foreach ($bundles as $bundle_id => $bundle) {
  echo $bundle_id . ': ' . $bundle->label() . "\n";
}
```

#### Check if Bundle Exists

```php
$bundle_storage = \Drupal::entityTypeManager()
  ->getStorage('videojs_media_type');

$bundle = $bundle_storage->load('youtube');

if ($bundle) {
  echo "YouTube bundle exists: " . $bundle->getDescription();
}
```

#### Create Custom Bundle Programmatically

```php
use Drupal\videojs_media\Entity\VideoJsMediaType;

$bundle = VideoJsMediaType::create([
  'id' => 'live_stream',
  'label' => 'Live Stream',
  'description' => 'Live streaming video content',
]);
$bundle->save();
```

### Entity Reference Field Integration

#### Add Entity Reference Field to Content Type

```php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

// Create field storage
$field_storage = FieldStorageConfig::create([
  'field_name' => 'field_video_reference',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'settings' => [
    'target_type' => 'videojs_media',
  ],
]);
$field_storage->save();

// Create field instance on article content type
$field = FieldConfig::create([
  'field_storage' => $field_storage,
  'bundle' => 'article',
  'label' => 'Video',
  'settings' => [
    'handler' => 'default:videojs_media',
    'handler_settings' => [
      'target_bundles' => [
        'local_video' => 'local_video',
        'youtube' => 'youtube',
      ],
    ],
  ],
]);
$field->save();
```

#### Reference VideoJS Media from Node

```php
use Drupal\node\Entity\Node;
use Drupal\videojs_media\Entity\VideoJsMedia;

// Create VideoJS Media
$media = VideoJsMedia::create([
  'type' => 'youtube',
  'name' => 'Tutorial Video',
  'field_youtube_url' => ['uri' => 'https://youtube.com/watch?v=abc123'],
]);
$media->save();

// Create node that references the media
$node = Node::create([
  'type' => 'article',
  'title' => 'My Article with Video',
  'field_video_reference' => [
    'target_id' => $media->id(),
  ],
]);
$node->save();
```

### Batch Processing

#### Batch Update Multiple Entities

```php
$storage = \Drupal::entityTypeManager()->getStorage('videojs_media');

// Get all unpublished YouTube videos
$query = $storage->getQuery()
  ->condition('type', 'youtube')
  ->condition('status', 0)
  ->accessCheck(FALSE);

$ids = $query->execute();

// Publish them all
foreach ($storage->loadMultiple($ids) as $media) {
  $media->setPublished(TRUE);
  $media->save();
}

\Drupal::messenger()->addMessage('Published ' . count($ids) . ' YouTube videos');
```
