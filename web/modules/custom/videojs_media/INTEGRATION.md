# VideoJS Media - Integration Guide

## Table of Contents

- [For Module Developers](#for-module-developers)
- [For Theme Developers](#for-theme-developers)
- [For Site Builders](#for-site-builders)

---

## For Module Developers

This section provides guidance for developers building custom modules that integrate with VideoJS Media.

### Referencing VideoJS Media from Custom Modules

#### Adding Entity Reference Field

```php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Implements hook_install().
 */
function mymodule_install() {
  // Create field storage if it doesn't exist
  $field_storage = FieldStorageConfig::loadByName('node', 'field_featured_video');
  
  if (!$field_storage) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_featured_video',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1, // Single value
      'settings' => [
        'target_type' => 'videojs_media',
      ],
    ]);
    $field_storage->save();
  }
  
  // Add field to article content type
  $field = FieldConfig::create([
    'field_storage' => $field_storage,
    'bundle' => 'article',
    'label' => 'Featured Video',
    'description' => 'Select a VideoJS media item to feature in this article',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:videojs_media',
      'handler_settings' => [
        'target_bundles' => [
          'local_video' => 'local_video',
          'youtube' => 'youtube',
        ],
        'sort' => [
          'field' => 'created',
          'direction' => 'DESC',
        ],
      ],
    ],
  ]);
  $field->save();
  
  // Configure form display
  \Drupal::service('entity_display.repository')
    ->getFormDisplay('node', 'article', 'default')
    ->setComponent('field_featured_video', [
      'type' => 'entity_reference_autocomplete',
      'weight' => 5,
      'settings' => [
        'match_operator' => 'CONTAINS',
        'size' => 60,
        'placeholder' => 'Start typing to search for a video...',
      ],
    ])
    ->save();
  
  // Configure view display
  \Drupal::service('entity_display.repository')
    ->getViewDisplay('node', 'article', 'default')
    ->setComponent('field_featured_video', [
      'type' => 'entity_reference_entity_view',
      'label' => 'hidden',
      'weight' => 10,
      'settings' => [
        'view_mode' => 'default',
        'link' => FALSE,
      ],
    ])
    ->save();
}
```

### Entity Query Examples

#### Query All Published Videos

```php
use Drupal\videojs_media\Entity\VideoJsMedia;

/**
 * Get all published VideoJS media items.
 */
function mymodule_get_published_videos() {
  $storage = \Drupal::entityTypeManager()->getStorage('videojs_media');
  
  $query = $storage->getQuery()
    ->condition('status', 1)
    ->sort('created', 'DESC')
    ->accessCheck(TRUE);
  
  $ids = $query->execute();
  
  return $storage->loadMultiple($ids);
}
```

#### Query by Bundle with Pagination

```php
/**
 * Get YouTube videos with pagination.
 *
 * @param int $page
 *   The page number (0-indexed).
 * @param int $items_per_page
 *   Number of items per page.
 *
 * @return array
 *   Array of VideoJsMedia entities.
 */
function mymodule_get_youtube_videos_paged($page = 0, $items_per_page = 10) {
  $storage = \Drupal::entityTypeManager()->getStorage('videojs_media');
  
  $query = $storage->getQuery()
    ->condition('type', 'youtube')
    ->condition('status', 1)
    ->sort('created', 'DESC')
    ->range($page * $items_per_page, $items_per_page)
    ->accessCheck(TRUE);
  
  $ids = $query->execute();
  
  return $storage->loadMultiple($ids);
}
```

#### Query with Multiple Conditions

```php
/**
 * Get recent videos by specific author with subtitles.
 *
 * @param int $uid
 *   User ID of the author.
 * @param int $days
 *   Number of days to look back.
 *
 * @return array
 *   Array of VideoJsMedia entities.
 */
function mymodule_get_recent_videos_by_author($uid, $days = 7) {
  $storage = \Drupal::entityTypeManager()->getStorage('videojs_media');
  
  $timestamp = strtotime("-$days days");
  
  $query = $storage->getQuery()
    ->condition('uid', $uid)
    ->condition('status', 1)
    ->condition('created', $timestamp, '>=')
    ->condition('type', ['local_video', 'youtube'], 'IN')
    ->exists('field_subtitle') // Has subtitles
    ->sort('created', 'DESC')
    ->accessCheck(TRUE);
  
  $ids = $query->execute();
  
  return $storage->loadMultiple($ids);
}
```

### Views Integration Examples

#### Creating a View Programmatically

```php
use Drupal\views\Entity\View;

/**
 * Create a view for displaying VideoJS media gallery.
 */
function mymodule_create_video_gallery_view() {
  $view = View::create([
    'id' => 'videojs_media_gallery',
    'label' => 'VideoJS Media Gallery',
    'description' => 'Displays all published VideoJS media items',
    'base_table' => 'videojs_media_field_data',
    'display' => [
      'default' => [
        'display_plugin' => 'default',
        'id' => 'default',
        'display_title' => 'Default',
        'position' => 0,
        'display_options' => [
          'title' => 'Video Gallery',
          'fields' => [
            'name' => [
              'id' => 'name',
              'table' => 'videojs_media_field_data',
              'field' => 'name',
              'label' => '',
              'element_label_colon' => FALSE,
            ],
          ],
          'filters' => [
            'status' => [
              'id' => 'status',
              'table' => 'videojs_media_field_data',
              'field' => 'status',
              'value' => '1',
              'entity_type' => 'videojs_media',
              'plugin_id' => 'boolean',
            ],
          ],
          'sorts' => [
            'created' => [
              'id' => 'created',
              'table' => 'videojs_media_field_data',
              'field' => 'created',
              'order' => 'DESC',
            ],
          ],
          'pager' => [
            'type' => 'full',
            'options' => [
              'items_per_page' => 12,
            ],
          ],
        ],
      ],
      'page_1' => [
        'display_plugin' => 'page',
        'id' => 'page_1',
        'display_title' => 'Page',
        'position' => 1,
        'display_options' => [
          'path' => 'video-gallery',
          'menu' => [
            'type' => 'normal',
            'title' => 'Video Gallery',
            'menu_name' => 'main',
          ],
        ],
      ],
    ],
  ]);
  
  $view->save();
}
```

### Form API Integration

#### Custom Form with VideoJS Media Selection

```php
namespace Drupal\mymodule\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for selecting and displaying VideoJS media.
 */
class VideoSelectionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mymodule_video_selection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['video'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select Video'),
      '#target_type' => 'videojs_media',
      '#selection_settings' => [
        'target_bundles' => ['local_video', 'youtube'],
      ],
      '#required' => TRUE,
    ];
    
    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Mode'),
      '#options' => [
        'default' => $this->t('Full Player'),
        'teaser' => $this->t('Thumbnail Only'),
      ],
      '#default_value' => 'default',
    ];
    
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Display Video'),
    ];
    
    // Display selected video if form was submitted
    if ($form_state->getValue('video')) {
      $video_id = $form_state->getValue('video');
      $view_mode = $form_state->getValue('view_mode', 'default');
      
      $storage = \Drupal::entityTypeManager()->getStorage('videojs_media');
      $video = $storage->load($video_id);
      
      if ($video && $video->access('view')) {
        $view_builder = \Drupal::entityTypeManager()->getViewBuilder('videojs_media');
        $form['video_display'] = $view_builder->view($video, $view_mode);
      }
    }
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

}
```

### REST API Integration

#### JSON:API Endpoint Usage

```php
/**
 * Fetch VideoJS media via JSON:API.
 *
 * @param string $bundle
 *   The bundle machine name.
 *
 * @return array
 *   Decoded JSON response.
 */
function mymodule_fetch_videos_jsonapi($bundle = 'youtube') {
  $client = \Drupal::httpClient();
  $base_url = \Drupal::request()->getSchemeAndHttpHost();
  
  try {
    $response = $client->request('GET', "$base_url/jsonapi/videojs_media/$bundle", [
      'query' => [
        'filter[status]' => '1',
        'sort' => '-created',
        'page[limit]' => 10,
      ],
    ]);
    
    return json_decode($response->getBody(), TRUE);
  }
  catch (\Exception $e) {
    \Drupal::logger('mymodule')->error('JSON:API request failed: @message', [
      '@message' => $e->getMessage(),
    ]);
    return [];
  }
}
```

### Service Integration

#### Custom Service That Works with VideoJS Media

```php
namespace Drupal\mymodule\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for managing VideoJS media recommendations.
 */
class VideoRecommendationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a VideoRecommendationService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Get recommended videos for the current user.
   *
   * @param int $limit
   *   Maximum number of recommendations.
   *
   * @return array
   *   Array of VideoJsMedia entities.
   */
  public function getRecommendations($limit = 5) {
    $storage = $this->entityTypeManager->getStorage('videojs_media');
    
    // Simple example: return most recent published videos
    $query = $storage->getQuery()
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->accessCheck(TRUE);
    
    $ids = $query->execute();
    
    return $storage->loadMultiple($ids);
  }

}
```

Register in `mymodule.services.yml`:

```yaml
services:
  mymodule.video_recommendation:
    class: Drupal\mymodule\Service\VideoRecommendationService
    arguments: ['@entity_type.manager', '@current_user']
```

---

## For Theme Developers

This section covers template customization, styling, and component overrides.

### Template Suggestions

VideoJS Media provides multiple template suggestion levels:

```
videojs-media.html.twig                           (all bundles, all view modes)
videojs-media--[bundle].html.twig                 (specific bundle, all view modes)
videojs-media--[bundle]--[view-mode].html.twig   (specific bundle and view mode)
videojs-media--[id].html.twig                     (specific entity ID)
```

#### Examples

```
videojs-media.html.twig
videojs-media--youtube.html.twig
videojs-media--local-video.html.twig
videojs-media--local-video--teaser.html.twig
videojs-media--youtube--default.html.twig
videojs-media--123.html.twig
```

### Available Variables in Templates

#### Base Template Variables

```twig
{# videojs-media.html.twig #}

{# Entity object #}
{{ videojs_media }}

{# Entity ID #}
{{ videojs_media.id }}

{# Bundle (type) #}
{{ videojs_media.bundle }}

{# Entity name/title #}
{{ videojs_media.name.value }}

{# View mode #}
{{ view_mode }}

{# Rendered fields #}
{{ content.field_media_file }}
{{ content.field_youtube_url }}
{{ content.field_poster_image }}
{{ content.field_subtitle }}

{# Metadata #}
{{ videojs_media.created.value }}
{{ videojs_media.changed.value }}
{{ videojs_media.owner.entity.name.value }}
```

### Custom Template Example

#### Override Default Template

Create `videojs-media.html.twig` in your theme:

```twig
{#
/**
 * @file
 * Default theme implementation for VideoJS Media.
 *
 * Available variables:
 * - videojs_media: The VideoJS Media entity.
 * - content: Renderable array of the entity's fields.
 * - attributes: HTML attributes for the wrapper.
 * - title_prefix: Additional output populated by modules.
 * - title_suffix: Additional output populated by modules.
 * - view_mode: View mode (e.g., 'default', 'teaser').
 */
#}

<article{{ attributes.addClass('videojs-media', 'videojs-media--' ~ videojs_media.bundle|clean_class) }}>
  
  {{ title_prefix }}
  
  {% if not page %}
    <h2{{ title_attributes.addClass('videojs-media__title') }}>
      <a href="{{ url }}" rel="bookmark">{{ videojs_media.name.value }}</a>
    </h2>
  {% endif %}
  
  {{ title_suffix }}
  
  <div{{ content_attributes.addClass('videojs-media__content') }}>
    {{ content }}
  </div>
  
  <footer class="videojs-media__meta">
    <span class="videojs-media__type">{{ videojs_media.type.entity.label }}</span>
    <time class="videojs-media__date" datetime="{{ videojs_media.created.value|date('c') }}">
      {{ videojs_media.created.value|date('F j, Y') }}
    </time>
  </footer>

</article>
```

#### Bundle-Specific Template

Create `videojs-media--youtube.html.twig`:

```twig
{#
/**
 * @file
 * Theme implementation for YouTube VideoJS Media.
 */
#}

<article{{ attributes.addClass('videojs-media', 'videojs-media--youtube') }}>
  
  <div class="videojs-media__player-wrapper">
    {{ content.field_youtube_url }}
  </div>
  
  {% if content.field_subtitle|render %}
    <div class="videojs-media__subtitles">
      {{ content.field_subtitle }}
    </div>
  {% endif %}
  
  <div class="videojs-media__sharing">
    <a href="{{ videojs_media.field_youtube_url.0.uri }}" target="_blank" class="btn btn-primary">
      Watch on YouTube
    </a>
  </div>

</article>
```

#### Teaser View Mode Template

Create `videojs-media--local-video--teaser.html.twig`:

```twig
{#
/**
 * @file
 * Teaser display for local video (poster image only).
 */
#}

<div{{ attributes.addClass('videojs-media-teaser', 'videojs-media-teaser--local-video') }}>
  
  <a href="{{ url }}" class="videojs-media-teaser__link">
    {% if content.field_poster_image|render %}
      <div class="videojs-media-teaser__image">
        {{ content.field_poster_image }}
      </div>
    {% else %}
      <div class="videojs-media-teaser__placeholder">
        <svg class="icon icon--play" width="64" height="64">
          <use xlink:href="#icon-play"></use>
        </svg>
      </div>
    {% endif %}
    
    <div class="videojs-media-teaser__title">
      <h3>{{ videojs_media.name.value }}</h3>
    </div>
  </a>
  
</div>
```

### CSS Classes and Styling

#### Default CSS Classes

VideoJS Media entities are wrapped with these classes:

```css
/* Base entity wrapper */
.videojs-media { }

/* Bundle-specific classes */
.videojs-media--local-video { }
.videojs-media--local-audio { }
.videojs-media--remote-video { }
.videojs-media--remote-audio { }
.videojs-media--youtube { }

/* View mode classes */
.videojs-media--view-mode-default { }
.videojs-media--view-mode-teaser { }

/* Published/unpublished */
.videojs-media--published { }
.videojs-media--unpublished { }
```

#### Example Styling

Add to your theme's CSS:

```css
/* Base player styling */
.videojs-media {
  margin-bottom: 2rem;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* YouTube-specific styling */
.videojs-media--youtube .videojs-media__player-wrapper {
  position: relative;
  padding-bottom: 56.25%; /* 16:9 aspect ratio */
  height: 0;
  overflow: hidden;
}

.videojs-media--youtube iframe {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

/* Teaser view mode */
.videojs-media-teaser {
  position: relative;
  transition: transform 0.2s ease;
}

.videojs-media-teaser:hover {
  transform: scale(1.05);
}

.videojs-media-teaser__image {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
}

.videojs-media-teaser__image::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 60px;
  height: 60px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  /* Play icon overlay */
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .videojs-media {
    margin-bottom: 1rem;
  }
}
```

### Single Directory Component (SDC) Override

Override the player component in your theme:

#### Component Structure

```
your-theme/
  components/
    videojs-player/
      videojs-player.component.yml
      videojs-player.twig
      videojs-player.css
```

#### Component Definition

`videojs-player.component.yml`:

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/modules/sdc/src/metadata.schema.json
name: VideoJS Player Override
description: Custom VideoJS player component
props:
  type: object
  properties:
    video_url:
      type: string
      title: Video URL
    poster_url:
      type: string
      title: Poster Image URL
    subtitle_url:
      type: string
      title: Subtitle URL
    autoplay:
      type: boolean
      title: Autoplay
      default: false
```

#### Component Template

`videojs-player.twig`:

```twig
<div class="custom-videojs-player" data-component="videojs-player">
  <video
    class="video-js vjs-default-skin"
    controls
    {% if poster_url %}poster="{{ poster_url }}"{% endif %}
    {% if autoplay %}autoplay{% endif %}
    preload="auto"
    width="640"
    height="360"
    data-setup='{}'>
    
    <source src="{{ video_url }}" type="video/mp4" />
    
    {% if subtitle_url %}
      <track kind="subtitles" src="{{ subtitle_url }}" srclang="en" label="English" />
    {% endif %}
    
    <p class="vjs-no-js">
      To view this video please enable JavaScript, and consider upgrading to a
      web browser that supports HTML5 video.
    </p>
  </video>
</div>
```

### JavaScript Integration

#### Attach Custom JavaScript to VideoJS Media

Create a library in your theme:

`mytheme.libraries.yml`:

```yaml
videojs-media-enhancements:
  version: 1.0
  js:
    js/videojs-media.js: {}
  dependencies:
    - core/drupal
    - core/jquery
```

`js/videojs-media.js`:

```javascript
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.videojsMediaEnhancements = {
    attach: function (context, settings) {
      $('.videojs-media', context).once('videojsMediaEnhancements').each(function () {
        var $player = $(this);
        
        // Add custom analytics
        $player.find('video').on('play', function () {
          console.log('Video started playing');
          // Send to analytics service
        });
        
        // Add custom controls
        $player.find('video').on('ended', function () {
          console.log('Video finished playing');
          // Show related videos, etc.
        });
      });
    }
  };

})(jQuery, Drupal);
```

Attach in `mytheme.theme`:

```php
/**
 * Implements hook_preprocess_videojs_media().
 */
function mytheme_preprocess_videojs_media(&$variables) {
  $variables['#attached']['library'][] = 'mytheme/videojs-media-enhancements';
}
```

---

## For Site Builders

This section provides guidance for non-developers configuring VideoJS Media through the UI.

### Views Configuration Tips

#### Creating a Video Gallery View

1. **Create New View**
   - Admin → Structure → Views → Add view
   - **View name**: Video Gallery
   - **Show**: VideoJS Media
   - **Of type**: All (or select specific bundle like "YouTube")
   - **Create a page**: Yes
   - **Page title**: Videos
   - **Path**: /videos
   - **Items per page**: 12

2. **Add Filters**
   - Click "Add" in **Filter Criteria**
   - Search for "Published" and add it
   - Check "Yes" to show only published items

3. **Add Bundle Filter** (optional)
   - Filter Criteria → Add → VideoJS Media: Type
   - Select specific bundles (e.g., Local Video, YouTube)
   - Expose filter to let users filter by type

4. **Configure Display**
   - Format → Show: Rendered entity
   - Settings → View mode: Teaser (for thumbnails) or Default (for players)

5. **Add Sorting**
   - Sort Criteria → Add → VideoJS Media: Authored on
   - Sort descending to show newest first

#### Creating a User's Videos View

1. Create view showing **VideoJS Media**
2. Add **Contextual Filter**:
   - VideoJS Media: Author uid
   - When filter value is NOT available: Provide default value
   - Type: User ID from logged in user
3. Add to user profile:
   - Block display
   - Block title: "My Videos"
   - Place in User account menu

### Entity Reference Field Setup

#### Add Video Field to Content Type

1. **Navigate to Content Type**
   - Admin → Structure → Content types → [Your Type] → Manage fields

2. **Add New Field**
   - Click "Add field"
   - **Field type**: Reference → Other
   - **Type of item to reference**: VideoJS Media
   - **Label**: Video (or your preferred label)
   - Click "Save and continue"

3. **Configure Field Storage**
   - **Allowed number of values**: 1 (or Unlimited for multiple videos)
   - Click "Save field settings"

4. **Configure Field Settings**
   - **Reference method**: Default
   - **Available bundles**: Select which types of media can be referenced
     - ☑ Local Video
     - ☑ YouTube
     - ☐ Local Audio (uncheck if not needed)
   - **Sort by**: Created date (Newest first)
   - Click "Save settings"

5. **Configure Form Display**
   - Manage form display tab
   - **Widget**: Entity reference autocomplete
   - **Size**: 60
   - **Placeholder**: "Start typing to search videos..."

6. **Configure View Display**
   - Manage display tab
   - **Format**: Rendered entity
   - **View mode**: Default (shows player) or Teaser (shows thumbnail)
   - **Label**: Hidden

### Display Mode Configuration

#### Configure Default View Mode

1. **Navigate to Display Settings**
   - Admin → Structure → VideoJS Media types → [Bundle] → Manage display

2. **Default View Mode**
   - Shows full player with all fields
   - Drag fields to reorder
   - Click gear icon to configure each field
   - Hide unwanted fields by dragging to "Disabled" section

3. **Field Configuration Examples**:
   - **Name**: Hidden (usually displayed as page title)
   - **Media File/URL**: Visible, weight: 0
   - **Poster Image**: Hidden (used by player automatically)
   - **Subtitle**: Hidden (loaded by player automatically)

#### Configure Teaser View Mode

1. Navigate to **Manage display** → **Teaser** tab

2. **Teaser displays poster image only** (no player):
   - **Poster Image**: Visible, weight: 0, Format: Image
   - **Name**: Hidden or Visible above image
   - **Media File/URL**: Hidden
   - **Subtitle**: Hidden

3. Use teaser in:
   - Gallery views
   - Related video listings
   - Search results
   - Entity reference displays

#### Create Custom View Mode

1. **Create View Mode**
   - Admin → Structure → Display modes → View modes → Add view mode
   - **Entity type**: VideoJS Media
   - **Name**: Compact
   - **Description**: Smaller player for sidebar use

2. **Enable for Bundles**
   - Admin → Structure → VideoJS Media types → [Bundle] → Manage display
   - Check "Custom display settings" → ☑ Compact
   - Save
   - Click "Compact" tab to configure

### Block Placement

#### Place VideoJS Media Block

1. **Navigate to Block Layout**
   - Admin → Structure → Block layout

2. **Place Block**
   - Choose region (e.g., Content, Sidebar)
   - Click "Place block"
   - Search for "VideoJS Media"
   - Click "Place block" next to "VideoJS Media Block"

3. **Configure Block**
   - **Title**: Video of the Day (or leave blank)
   - **Display title**: Check to show title
   - **VideoJS Media**: Start typing to search for a specific video
   - **View mode**: Default (player) or Teaser (thumbnail)
   - **Visibility**: Configure which pages to show on

4. **Save Block**

#### Dynamic Block with Views

1. Create a View with **Block display**
2. Filter to show 1 item (random or most recent)
3. Place the view block instead of entity block
4. Updates automatically when new videos are added

### Permissions Configuration

#### Setting Up Roles

1. **Navigate to Permissions**
   - Admin → People → Permissions

2. **Anonymous Users** (Public visitors):
   - ☑ View published Local Video VideoJS media
   - ☑ View published YouTube VideoJS media
   - ☐ All other permissions unchecked

3. **Authenticated Users** (Logged in):
   - ☑ Create Local Video VideoJS media
   - ☑ Create YouTube VideoJS media
   - ☑ Edit own Local Video VideoJS media
   - ☑ Edit own YouTube VideoJS media
   - ☑ Delete own Local Video VideoJS media
   - ☑ View published (all bundles)

4. **Content Editor** (Staff):
   - ☑ Edit any [Bundle] VideoJS media
   - ☑ Delete any [Bundle] VideoJS media
   - ☑ View unpublished (all bundles)

5. **Administrator**:
   - ☑ Administer VideoJS Media
   - ☑ Administer VideoJS Media types

### Workflow Recommendations

#### Content Moderation

1. **Enable Content Moderation Module**
   - If not already enabled: `drush en content_moderation`

2. **Create Workflow**
   - Admin → Configuration → Workflow → Workflows → Add workflow
   - **Name**: Video Moderation
   - **Type**: Content moderation
   - **States**: Draft, In Review, Published, Archived
   - **Transitions**: Configure allowed transitions

3. **Apply to VideoJS Media**
   - Edit workflow
   - "This workflow applies to" → Check VideoJS Media bundles
   - Save

4. **Users can now**:
   - Save as Draft
   - Submit for Review
   - Publish when approved
   - Archive old videos

### Best Practices

#### Performance

- **Use Teaser view mode** for lists and grids to avoid loading multiple players on one page
- **Limit items per page** in Views (12-24 is optimal)
- **Enable caching** in Views settings
- **Use lazy loading** for images in teaser mode

#### Content Organization

- **Consistent naming**: Use descriptive names for videos
- **Add poster images**: Always add custom thumbnails for better visual appeal
- **Use subtitles**: Improve accessibility and SEO
- **Tag with taxonomy**: Add taxonomy reference fields for categorization

#### Accessibility

- **Always provide subtitles** for spoken content
- **Descriptive names**: Use meaningful titles that describe video content
- **Alt text**: Ensure poster images have proper alt text
- **Keyboard navigation**: VideoJS player is keyboard accessible by default

#### SEO

- **Schema.org markup**: Consider adding VideoObject schema in templates
- **Transcripts**: Provide text transcripts on the same page as videos
- **Descriptive URLs**: Use Pathauto to generate SEO-friendly URLs
- **XML Sitemap**: Include VideoJS Media in XML sitemap module configuration
