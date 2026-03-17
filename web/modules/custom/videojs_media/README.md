# VideoJS Media

## Overview

The **VideoJS Media** module provides a modern, entity-based architecture for embedding VideoJS media players in Drupal. Unlike block-based approaches, this module implements VideoJS media as **content entities with bundles**, giving you the flexibility and power of Drupal's entity system.

## Key Features

- **Bundle-based architecture** - 5 distinct media types (bundles):
  - Local Video
  - Local Audio
  - Remote Video
  - Remote Audio
  - YouTube

- **Clean data model** - Each bundle only has the fields it needs (no hidden fields or AJAX complexity)

- **Native view modes** - Configure different displays per bundle

- **Granular permissions** - Per-bundle create, edit, delete, and view permissions

- **Full Entity API** - Revisions, translations, workflows, moderation

- **Views integration** - First-class Views support with bundle filtering

- **Entity Reference ready** - Can be referenced from any content type

- **Field UI support** - Site builders can add custom fields per bundle

- **ADA/508 compliant** - Supports subtitles/captions for accessibility

- **Responsive** - Works on all device sizes

## Why VideoJS Media vs. videojs_mediablock?

### Technical Advantages

| Feature | videojs_mediablock (Old) | videojs_media (New) |
|---------|-------------------------|---------------------|
| Architecture | Block Content entities | Content entities with bundles |
| Form complexity | 500+ lines of AJAX | Standard entity forms (zero custom AJAX) |
| Data model | 5 entity reference fields (4 always empty) | Only relevant fields per bundle |
| Permissions | Custom permission checking in form_alter | Native entity access control |
| View modes | Preprocessing hack with conditional rendering | Native view mode system |
| Field visibility | AJAX toggles and form state management | Bundle selection determines fields |
| Reusability | Blocks (designed for placement) | Content entities (designed for references) |

### Functional Advantages

- **Better UX**: Users choose media type first, then see only relevant fields
- **True view modes**: Each bundle can have different displays (full, compact, thumbnail, etc.)
- **Superior Views**: Filter by bundle, use bundle-specific fields, natural relationships
- **Entity References**: Reference VideoJS Media from nodes, paragraphs, or any entity
- **REST/JSON:API**: Full decoupled/headless support
- **Search API**: Natural content entity indexing
- **Content Moderation**: Per-bundle workflows (Draft → Review → Published)
- **Better performance**: No AJAX callbacks, cleaner queries

## Requirements

- Drupal ^10.3 or ^11
- PHP fileinfo library
- NPM (for updating VideoJS libraries)

### Drupal Module Dependencies

- Config
- Field
- Media
- Media Library
- Serialization
- File Upload Secure Validator
- Taxonomy
- Options
- File
- Link
- Text

## Installation

1. Place this module in `web/modules/custom/videojs_media/`

2. **VideoJS Libraries**: This module includes its own copy of the VideoJS libraries in the `node_modules/` directory. You do not need to run `npm install` to use the module, but if you want to update the libraries in the future, you can do so with npm.

   **Important**: The `node_modules/` directory is included and ready to use. However, if you need to update the VideoJS libraries in the future:

   ```bash
   cd web/modules/custom/videojs_media
   npm update
   ```

   Or to upgrade to the latest major versions:
   ```bash
   npm update --latest
   ```

3. Enable the module:
   ```bash
   drush en videojs_media
   ```

4. Configure file upload security at `/admin/config/media/file_upload_secure_validator`:
   ```
   video/quicktime,video/mp4,video/x-m4v,video/ogg,video/webm,video/x-flv,video/x-f4v
   audio/x-aac,audio/x-flac,audio/x-mpeg,audio/mpeg,audio/mp4,audio/ogg,application/x-ogg,audio/vorbis,audio/x-wav
   text/vtt,text/plain
   ```

5. Grant permissions at `/admin/people/permissions` (filter by "VideoJS Media")

## Quick Start: Using VideoJS Media in Your Content

**Understanding the Concept**: VideoJS Media items are **content entities** (like Media items), not blocks. They're designed to be created once and referenced from multiple places.

### Workflow Overview

```
1. Create VideoJS Media items
   ↓
2. Add Entity Reference field to your content type
   ↓
3. Reference VideoJS Media items when creating content
   ↓
4. Display VideoJS Media in your content
```

### Step-by-Step Guide

#### Step 1: Create VideoJS Media Items

1. Navigate to **Content** → **VideoJS Media** (or `/admin/content/videojs-media`)
   - *Tip: You'll now see a "VideoJS Media" tab next to "Content" and "Media" tabs*
2. Click **Add VideoJS Media**
3. Choose a media type (Local Video, YouTube, etc.)
4. Fill in the required fields and save

#### Step 2: Add Entity Reference Field to Content Type

To use VideoJS Media in your Article, Basic Page, or custom content type:

1. Go to **Structure** → **Content types** → **[Your Content Type]** → **Manage fields**
2. Click **Add field**
3. Choose **Reference** → **Other...** (Entity Reference)
4. Configure:
   - **Label**: "Video" (or your preferred name)
   - **Type of item to reference**: VideoJS Media
   - **Optional**: Limit to specific bundles (e.g., only YouTube, only Local Video)
5. Save field settings
6. Configure widget (Autocomplete or Inline Entity Form)
7. Configure display settings (Rendered entity with your preferred view mode)

#### Step 3: Add VideoJS Media to Your Content

1. Create or edit content (Article, Basic Page, etc.)
2. In the VideoJS Media field, start typing the name of a VideoJS Media item
3. Select from the autocomplete suggestions
4. Save your content

#### Step 4: View Your Content

The VideoJS Media player will display inline within your content, using the view mode you configured.

### Example Field Configuration (via Drush)

```bash
# Using drush to quickly add a field to Article content type
drush field:create node article field_video \
  --field-type=entity_reference \
  --target-type=videojs_media \
  --field-label="Video"
```

### Navigation Tips

- **Main content list**: `/admin/content` - Your nodes and regular content
- **VideoJS Media list**: `/admin/content/videojs-media` - Your reusable video/audio entities
- **Media list**: `/admin/content/media` - Traditional Drupal Media entities
- These are separate but complementary content types!

### Using VideoJS Media in Blocks

The module provides a **VideoJS Media Block** plugin that allows you to place VideoJS Media items directly in any block region.

#### How to Use:

1. Go to **Structure** → **Block layout**
2. Click **Place block** in your desired region
3. Search for "VideoJS Media" and click **Place block**
4. Configure the block:
   - **VideoJS Media**: Use autocomplete to select a VideoJS Media item
   - **View mode**: Choose how to display it (Default, Full, Compact, etc.)
   - **Hide title**: Check this to hide the VideoJS Media title from displaying
5. Save the block configuration

#### Use Cases:
- Display a promotional video in the sidebar
- Add background music to specific pages
- Create a featured video section in the header
- Place tutorial videos in the footer

## Usage

### Creating VideoJS Media

1. Navigate to `/videojs-media/add`
2. Choose a media type (Local Video, Remote Audio, YouTube, etc.)
3. Fill in the form:
   - **Name**: Required title for the media
   - **Media file/URL**: The source (varies by bundle)
   - **Poster Image**: Optional thumbnail (Media Library image)
   - **Subtitles**: Optional WebVTT caption files
   - **Published**: Publish status

### Managing VideoJS Media

- **Content list**: `/admin/content/videojs-media`
- **Add new**: `/videojs-media/add`
- **Edit**: `/videojs-media/{id}/edit`
- **View revisions**: `/videojs-media/{id}/revisions`

### Managing Bundles (Types)

- **Type list**: `/admin/structure/videojs-media/types`
- **Add type**: `/admin/structure/videojs-media/types/add`
- **Edit type**: `/admin/structure/videojs-media/types/{type}`
- **Manage fields**: `/admin/structure/videojs-media/types/{type}/fields`
- **Manage form display**: `/admin/structure/videojs-media/types/{type}/form-display`
- **Manage display**: `/admin/structure/videojs-media/types/{type}/display`

### Using in Views

1. Create a new View
2. Show: **VideoJS Media**
3. Filter by:
   - **Type** (bundle): local_video, youtube, etc.
   - **Published status**
   - **Author**
   - Any custom fields
4. Add fields:
   - **Rendered entity**: Choose view mode
   - **Bundle-specific fields**: field_youtube_url, field_media_file, etc.
5. Use **relationships** to relate to referenced media entities

### Referencing from Content

Add an **Entity Reference** field to any content type:

```yaml
field_video:
  type: entity_reference
  target_type: videojs_media
  handler_settings:
    target_bundles:
      youtube: youtube
      local_video: local_video
```

Then use:
- **Autocomplete widget** for selection
- **Inline Entity Form** for embedded editing
- **Entity Browser** for advanced selection

### View Modes

Configure view modes per bundle:

- `/admin/structure/videojs-media/types/{type}/display`

#### Built-in View Modes

This module ships with the following view modes:

- **Default**: Full player with all metadata and controls
- **Teaser**: Displays only the poster image without player controls or playback functionality. Ideal for listing pages, search results, or anywhere you want to show a preview without the full player.

#### Suggested Custom View Modes

You can create additional view modes to suit your needs:

- **Compact**: Smaller player, minimal metadata
- **Thumbnail**: Poster image only, click to play
- **Player only**: Just the VideoJS player, no wrapper
- **Full**: Page display with extended metadata
### Custom Templates

Override templates in your theme:

```
your-theme/templates/
  videojs-media.html.twig                    (all bundles, all view modes)
  videojs-media--youtube.html.twig           (youtube bundle, all view modes)
  videojs-media--local-video--compact.html.twig  (local_video bundle, compact view mode)
  videojs-media--[id].html.twig             (specific entity)
```

### Single Directory Component (SDC) Theming

Override the player component in your theme:

```
your-theme/components/videojs_media/player/
  player.component.yml
  player.twig
  player.js
```

## Permissions

### Administrative
- **Administer VideoJS Media**: Full access
- **Administer VideoJS Media types**: Manage bundles

### Per-Bundle Permissions

Each bundle has these permissions:
- **Create [bundle] videojs media**
- **Edit own [bundle] videojs media**
- **Edit any [bundle] videojs media**
- **Delete own [bundle] videojs media**
- **Delete any [bundle] videojs media**
- **View [bundle] videojs media** (published)
- **View unpublished [bundle] videojs media**

Example: To let editors create YouTube videos only:
- Grant: "Create youtube videojs media"
- Grant: "Edit own youtube videojs media"
- Don't grant other bundle permissions

## Bundles (Media Types)

### Local Video (`local_video`)
- **Field**: field_media_file (entity reference to Media: videojs_video)
- **Supports**: MP4, WebM, OGG video files
- **Use when**: Hosting video files on your server

### Local Audio (`local_audio`)
- **Field**: field_media_file (entity reference to Media: videojs_audio)
- **Supports**: MP3, OGG, AAC audio files
- **Use when**: Hosting audio files on your server

### Remote Video (`remote_video`)
- **Field**: field_remote_url (link field)
- **Supports**: HLS (.m3u8), DASH (.mpd), remote MP4, WebM
- **Use when**: Streaming from CDN or remote server

### Remote Audio (`remote_audio`)
- **Field**: field_remote_url (link field)
- **Supports**: Remote MP3, OGG, streaming audio
- **Use when**: Audio hosted elsewhere

### YouTube (`youtube`)
- **Field**: field_youtube_url (link field)
- **Supports**: Full YouTube URLs or video IDs
- **Use when**: Embedding YouTube videos

## Extending the Module

### Adding Custom Fields

1. Go to `/admin/structure/videojs-media/types/{type}/fields`
2. Click "Add field"
3. Add any field type (taxonomy, text, image, etc.)
4. Configure form and display settings

Example use cases:
- Add "Duration" field to track video length
- Add "Tags" taxonomy for categorization
- Add "Transcript" text field for full text
- Add "Quality" options list

### Creating Custom Bundles

1. Go to `/admin/structure/videojs-media/types/add`
2. Create a new type (e.g., "Vimeo")
3. Add fields specific to that type
4. Configure permissions for the new bundle

### Custom View Builders

Create a custom view builder for advanced rendering:

```php
namespace Drupal\my_module\Entity;

use Drupal\Core\Entity\EntityViewBuilder;

class CustomVideoJsMediaViewBuilder extends EntityViewBuilder {
  // Custom build logic
}
```

Update the entity annotation in VideoJsMedia.php:
```php
handlers = {
  "view_builder" = "Drupal\my_module\Entity\CustomVideoJsMediaViewBuilder",
}
```

### REST/JSON:API

VideoJS Media entities are automatically available via JSON:API:

```bash
# Get all VideoJS Media
GET /jsonapi/videojs_media/videojs_media

# Get only YouTube videos
GET /jsonapi/videojs_media/youtube

# Create new local video
POST /jsonapi/videojs_media/local_video
```

## Maintaining VideoJS Libraries

This module includes all necessary VideoJS libraries in its `node_modules/` directory. To keep them up-to-date:

### Check for Updates
```bash
cd web/modules/custom/videojs_media
npm outdated
```

### Security Audits
```bash
npm audit
npm audit fix
```

### Update Libraries
```bash
# Safe updates (respects package.json version ranges)
npm update

# Major version upgrades (use with caution)
npm update --latest
```

### Recommended Update Schedule
- **Security updates**: Check monthly with `npm audit`
- **Minor/patch updates**: Quarterly with `npm update`
- **Major version updates**: Review changelog before updating

## Roadmap

- [x] ~~Copy node_modules to this module (remove videojs_mediablock dependency)~~ ✅ **COMPLETE**
- [x] ~~Add more view mode examples~~ ✅ **COMPLETE** (Teaser view mode added)
- [ ] Add example view configurations
- [ ] Provide migration from videojs_mediablock (optional)
- [ ] Add integration tests
- [ ] Document REST API endpoints
- [ ] Add Drush command for library updates

## Credits

This module architecture is inspired by and functionally based on the excellent [VideoJS Mediablock](https://www.drupal.org/project/videojs_mediablock) module, reimagined with a modern entity-with-bundles architecture.

## License

GPL-2.0+
