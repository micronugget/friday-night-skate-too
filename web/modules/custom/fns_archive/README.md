# FNS Archive Module

## Overview
This module provides the foundational content architecture for the Friday Night Skate Archive, including taxonomy vocabulary for skate dates and a content type for managing uploaded media.

## Features

### Content Moderation Workflow
- **Workflow name:** Archive Review
- **States:** Draft, In Review, Published, Archived
- **Roles:** Skater (uploader), Moderator (reviewer)
- **Email notifications:** Automatic notifications on submission, approval, and rejection
- **Dashboard:** Moderation queue for reviewers at `/admin/content/moderation`
- **User view:** Personal content management at `/user/my-archive-content`

See [MODERATION_WORKFLOW.md](./MODERATION_WORKFLOW.md) for complete documentation.

### Taxonomy Vocabulary: Skate Dates
- **Machine name:** `skate_dates`
- **Format:** "YYYY-MM-DD - Location/Description"
- **Purpose:** Organize archive media by skate session date and location
- **Hierarchical:** No
- **Multiple values per node:** No

### Views

#### Archive by Skate Date
- **Machine name:** `archive_by_date`
- **Path:** `/archive/%` (where % is the taxonomy term ID)
- **Purpose:** Display archive media filtered by Skate Date taxonomy term
- **Display:** Page with Masonry grid layout (unformatted list)
- **Items per page:** 50 with pagination
- **Contextual filter:** Skate Date taxonomy term
- **Filters:** Archive Media content type, Published status only, Published moderation state
- **Sort:** By field_timestamp DESC (newest first)
- **Row style:** Rendered entity using "thumbnail" view mode

**Usage Examples:**
- `/archive/123` - Display all media for skate date term ID 123
- Title automatically overridden with taxonomy term name
- Empty state message with link back to archive index

**Integration:** Designed for Masonry.js grid layout with:
- Unformatted list format (no views-specific wrapper classes)
- `.masonry-item` row class for JavaScript targeting
- Thumbnail view mode for consistent media display

### Content Type: Archive Media
- **Machine name:** `archive_media`
- **Purpose:** Store and organize images and videos from Friday Night Skate sessions

#### Fields:
1. **Title** - Auto-generated from upload filename
2. **Media** (`field_archive_media`) - Entity reference to media (image or video)
3. **Skate Date** (`field_skate_date`) - Taxonomy reference to skate_dates vocabulary (required)
4. **GPS Coordinates** (`field_gps_coordinates`) - Geofield for location data
5. **Timestamp** (`field_timestamp`) - Date/Time field for when media was captured
6. **Uploader** (`field_uploader`) - User reference, auto-populated with current user
7. **Metadata** (`field_metadata`) - Text field for JSON-formatted EXIF/ffprobe data

#### View Modes:
- **Full** - Display all fields
- **Teaser** - Display media and skate date
- **Thumbnail** - Display media only
- **Modal** - Display media with key metadata (date, GPS, timestamp, uploader)

#### URL Pattern:
`/archive/{skate_date}/{node_id}`

Example: `/archive/2024-01-15-downtown-loop/123`

## Installation

This module is installed automatically when enabled. On installation, it creates:
- The `skate_dates` taxonomy vocabulary
- The `archive_media` content type with all required fields
- View modes for different display contexts
- Pathauto pattern for clean URLs

## Usage

### Creating Archive Media
1. Navigate to Content → Add content → Archive Media
2. Enter a title or let it auto-generate
3. Select or upload media (image or video)
4. Choose the skate date from the dropdown
5. Optionally add GPS coordinates, timestamp, and metadata
6. The uploader field will auto-populate with your user account

### Managing Skate Dates
1. Navigate to Structure → Taxonomy → Skate Dates
2. Add terms in the format: "YYYY-MM-DD - Location/Description"
3. Example: "2024-01-15 - Downtown Loop"

## Technical Details

### Auto-population
The module implements `hook_node_presave()` to automatically populate:
- **Uploader field** with the current user when creating new archive_media nodes
- **Moderation state tracking** for triggering workflow notifications

### Hooks Implemented
- `hook_ENTITY_TYPE_presave()` - Track moderation state changes
- `hook_ENTITY_TYPE_insert()` - Handle new content notifications
- `hook_ENTITY_TYPE_update()` - Trigger workflow transition notifications
- `hook_mail()` - Email templates for moderation notifications

### Update Hook
The module includes `fns_archive_update_10001()` to install all configurations on existing sites.

## Dependencies
- node
- field
- taxonomy
- datetime
- text
- user
- media
- geofield
- pathauto
- content_moderation
- workflows

## Maintainers
- Friday Night Skate development team
