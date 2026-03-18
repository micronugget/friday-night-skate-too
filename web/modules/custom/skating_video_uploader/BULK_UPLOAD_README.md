# Bulk Upload Feature Implementation

## Overview
This document describes the bulk upload feature implementation for the Friday Night Skate Archive.

## Features Implemented

### 1. Multi-Step Upload Form
Location: `src/Form/BulkUploadForm.php`

The form provides a 4-step workflow:

#### Step 1: File Selection
- Bulk file upload (images and videos)
- Multiple file selection support (up to 50 files)
- YouTube URL input (multiple URLs, one per line)
- Drag-and-drop interface
- Mobile camera integration (capture="environment")
- Client-side file validation

#### Step 2: Metadata Extraction
- Progress indicators for processing
- Display of file processing status
- AJAX-based metadata extraction
- Integration with MetadataExtractor service

#### Step 3: Assign Skate Date
- Autocomplete field for skate_dates taxonomy
- Attribution field (defaults to current user)
- "Apply to all" checkbox for bulk assignment
- Validation for required fields

#### Step 4: Review & Submit
- Summary of upload details
- File count, YouTube videos, skate date, attribution
- Moderation notice
- Final submission for moderation workflow

### 2. Form Assets

#### CSS (`css/bulk-upload.css`)
- Bootstrap 5 responsive styling
- Progress indicator animations
- Drag-and-drop visual feedback
- Mobile-optimized touch targets
- Accessible design patterns

#### JavaScript (`js/bulk-upload.js`)
- Client-side file validation (size, extension, count)
- Drag-and-drop functionality
- YouTube URL pattern validation
- Loading indicators
- Mobile camera hints
- Real-time feedback

### 3. Testing

#### Functional Tests (`tests/src/Functional/BulkUploadFormTest.php`)
Tests cover:
- Form access control
- Step 1 file selection UI
- Validation: at least one file or URL required
- YouTube URL validation (valid/invalid patterns)
- Multi-step progression
- Back button functionality
- Progress indicator display

#### Unit Tests (`tests/src/Unit/BulkUploadFormValidationTest.php`)
Tests cover:
- YouTube URL pattern matching (18 test cases)
- File extension validation (14 test cases)
- Edge cases and invalid inputs

### 4. Routing
Route: `/skate/upload`
Permission: `create archive_media content`

## Installation

### Required Dependencies

**IMPORTANT:** Due to network connectivity issues in the current environment, the following dependency needs to be installed manually:

```bash
ddev composer require drupal/media_library_bulk_upload
```

This module provides enhanced bulk upload widgets that integrate with Drupal's media library.

### Enable and Configure

1. **Install dependency** (when online):
   ```bash
   ddev composer require drupal/media_library_bulk_upload
   ```

2. **Clear cache**:
   ```bash
   ddev drush cr
   ```

3. **Import configuration** (if needed):
   ```bash
   ddev drush cim
   ```

4. **Grant permissions**:
   - Navigate to `/admin/people/permissions`
   - Grant "Create archive_media content" to appropriate roles (e.g., "skater" role)

## Usage

### For Users
1. Navigate to `/skate/upload`
2. Upload files or paste YouTube URLs
3. Wait for metadata extraction
4. Assign a skate date
5. Review and submit for moderation

### For Administrators
- Submissions appear in the moderation dashboard
- Review at `/admin/content/moderation-dashboard`
- Approve or reject submissions

## Technical Details

### File Validation
- **Allowed extensions**: jpg, jpeg, png, gif, mp4, mov, avi
- **Max file size**: 500MB per file
- **Max files per upload**: 50 files
- **Upload location**: `private://skating-uploads`

### YouTube URL Patterns
Accepts:
- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`
- `https://m.youtube.com/watch?v=VIDEO_ID`
- `https://www.youtube.com/embed/VIDEO_ID`
- `https://www.youtube.com/v/VIDEO_ID`

### Integration Points
- **MetadataExtractor service**: Extracts EXIF/GPS data from files
- **VideoProcessor service**: Handles video processing
- **Taxonomy**: Uses `skate_dates` vocabulary
- **Content type**: Creates `archive_media` nodes
- **Moderation**: Submissions start in "draft" state

## Running Tests

### PHPUnit Tests
```bash
# Run all upload form tests
ddev phpunit --group upload_form

# Run functional tests only
ddev phpunit web/modules/custom/skating_video_uploader/tests/src/Functional/

# Run unit tests only
ddev phpunit web/modules/custom/skating_video_uploader/tests/src/Unit/
```

### Static Analysis
```bash
ddev phpstan analyze web/modules/custom/skating_video_uploader
```

### Code Standards
```bash
ddev exec phpcs --standard=Drupal web/modules/custom/skating_video_uploader
```

## Mobile Optimization

### Features
- Touch-friendly UI elements (larger buttons)
- Camera integration via `capture="environment"` attribute
- Responsive design (Bootstrap 5 breakpoints)
- Drag-and-drop support on mobile browsers
- File size warnings before upload
- Progress indicators optimized for mobile

### Browser Support
- iOS Safari 12+
- Chrome Android 80+
- Firefox Android 68+
- Modern desktop browsers

## Accessibility

- Semantic HTML5 markup
- ARIA labels and roles
- Keyboard navigation support
- Screen reader friendly
- Color contrast compliance (WCAG AA)
- Focus indicators
- Error messages associated with form fields

## Security Considerations

- File upload validation (server and client-side)
- File extension whitelist
- File size limits
- Private file system for uploads
- Permission-based access control
- CSRF protection (Drupal Form API)
- XSS prevention (sanitized output)
- Input validation for all form fields

## Performance

- AJAX-based metadata extraction (non-blocking)
- Progressive file upload
- Client-side validation reduces server load
- Efficient database queries
- Caching of taxonomy terms

## Future Enhancements

Potential improvements for future iterations:

1. **Chunked Upload**: For very large video files
2. **Background Processing**: Queue-based metadata extraction
3. **Image Optimization**: Automatic resize/compress
4. **Batch Import**: CSV import for bulk YouTube URLs
5. **GPS Map Preview**: Show location on map during upload
6. **Video Thumbnails**: Generate preview images
7. **Upload Resume**: Resume interrupted uploads
8. **Duplicate Detection**: Prevent duplicate uploads

## Troubleshooting

### Form doesn't appear
- Check permissions: User needs "create archive_media content"
- Clear cache: `ddev drush cr`
- Check route: Visit `/skate/upload` directly

### Metadata extraction fails
- Verify ffprobe is installed: `ddev exec which ffprobe`
- Check file permissions on private://
- Review logs: `ddev drush watchdog:show`

### Tests failing
- Ensure fns_archive module is enabled
- Verify taxonomy vocabulary exists
- Check database connection
- Run: `ddev drush updb` to apply any pending updates

## Related Documentation

- [Drupal Form API](https://www.drupal.org/docs/drupal-apis/form-api)
- [Media Library](https://www.drupal.org/docs/core-modules-and-themes/core-modules/media-module)
- [PHPUnit Testing](https://www.drupal.org/docs/automated-testing/phpunit-in-drupal)
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.0/)

## Support

For issues or questions:
- Check logs: `ddev drush watchdog:show --severity=Error`
- Review test output: `ddev phpunit --group upload_form`
- Verify configuration: `ddev drush config:status`
