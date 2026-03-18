# Testing and Validation Guide - Skating Video Uploader

This document provides instructions for testing and validating the Skating Video Uploader module.

## Prerequisites

Before testing, ensure:
- DDEV is installed and running
- FFmpeg/ffprobe is installed in the DDEV container
- VideoJS Media module is installed and enabled
- Google API credentials are available (for YouTube upload testing)

## Automated Testing Commands (DDEV Required)

### PHP Unit Tests
```bash
# Run all tests for the module
ddev phpunit web/modules/custom/skating_video_uploader

# Run specific test class
ddev phpunit --filter MetadataExtractorTest

# Run with coverage report
ddev phpunit --coverage-html coverage web/modules/custom/skating_video_uploader
```

### Static Analysis
```bash
# Run PHPStan analysis
ddev phpstan analyze web/modules/custom/skating_video_uploader

# Maximum strictness
ddev phpstan analyze --level max web/modules/custom/skating_video_uploader
```

### Code Standards
```bash
# Check Drupal coding standards
ddev exec phpcs --standard=Drupal web/modules/custom/skating_video_uploader

# Auto-fix coding standards
ddev exec phpcbf --standard=Drupal web/modules/custom/skating_video_uploader
```

### Clear Cache (before testing)
```bash
ddev drush cr
```

## Manual Installation Testing

### 1. Install FFmpeg/ffprobe
```bash
# Install ffmpeg in DDEV container
ddev exec apt-get update
ddev exec apt-get install -y ffmpeg

# Verify installation
ddev exec ffprobe -version
```

### 2. Install VideoJS Media Module
```bash
# Install via Composer
ddev composer require drupal/videojs_media

# Enable the module
ddev drush en videojs_media -y
```

### 3. Install and Enable Skating Video Uploader
```bash
# Enable the module
ddev drush en skating_video_uploader -y

# Clear cache
ddev drush cr

# Verify module is enabled
ddev drush pml | grep skating_video_uploader
```

### 4. Verify Database Schema
```bash
# Check if table was created
ddev drush sqlq "SHOW TABLES LIKE 'skating_video_metadata'"

# Check table structure
ddev drush sqlq "DESCRIBE skating_video_metadata"
```

Expected columns:
- id (primary key)
- videojs_media_id
- file_id
- youtube_id
- latitude
- longitude
- altitude
- creation_time
- duration
- timecode_data
- consent_given
- created
- changed

### 5. Configure YouTube API Credentials
```bash
# Navigate to the settings page
ddev drush uli /admin/config/media/skating-video-uploader
```

Or via browser:
1. Navigate to `/admin/config/media/skating-video-uploader`
2. Enter YouTube Client ID
3. Enter YouTube Client Secret
4. Enter Redirect URI (usually `https://your-site.local/admin/config/media/skating-video-uploader/youtube/oauth-callback`)
5. Save configuration
6. Click "Authenticate with YouTube"

## Feature Testing

### Test 1: Metadata Extraction (Local Video)

#### Prerequisites
- Video file with GPS metadata (e.g., from smartphone camera)
- Test video should contain location data in EXIF/metadata

#### Steps
1. Navigate to `/videojs-media/add/local_video`
2. Enter a title: "Test GPS Video"
3. Upload video file with GPS metadata
4. Save the entity
5. Check Drupal logs: `ddev drush watchdog:show --filter=skating_video_uploader`
6. Query metadata table:
   ```bash
   ddev drush sqlq "SELECT * FROM skating_video_metadata ORDER BY created DESC LIMIT 1"
   ```

#### Expected Results
- ✅ Metadata record created in database
- ✅ Latitude and longitude extracted (if present in video)
- ✅ Duration extracted correctly
- ✅ Creation time populated
- ✅ No PHP errors in logs

#### Validation
```bash
# Check last log entry
ddev drush watchdog:show --severity=error --filter=skating_video_uploader

# Should return no errors
```

### Test 2: Metadata Extraction with YouTube Upload

#### Prerequisites
- YouTube API credentials configured
- OAuth authentication completed
- Video file ready for upload

#### Steps
1. Navigate to `/videojs-media/add/local_video`
2. Enter a title: "Test YouTube Upload"
3. Upload video file
4. Check the "Upload to YouTube and preserve metadata" checkbox
5. Save the entity
6. Wait for processing (check logs for progress)
7. Query metadata table:
   ```bash
   ddev drush sqlq "SELECT videojs_media_id, youtube_id, consent_given FROM skating_video_metadata ORDER BY created DESC LIMIT 1"
   ```

#### Expected Results
- ✅ Metadata extracted and stored locally
- ✅ Video uploaded to YouTube
- ✅ YouTube ID stored in database
- ✅ Consent flag set to 1
- ✅ User receives success message
- ✅ Video appears in YouTube account (unlisted)

#### Validation
```bash
# Check upload success
ddev drush watchdog:show --severity=notice --filter=skating_video_uploader | grep "uploaded to YouTube"
```

### Test 3: Form Integration

#### Steps
1. Navigate to `/videojs-media/add/local_video`
2. Verify "YouTube Upload & Metadata" fieldset is present
3. Verify consent checkbox is visible
4. Check checkbox label and description text
5. Save without checking consent
6. Verify video is saved but NOT uploaded to YouTube

#### Expected Results
- ✅ Form displays consent fieldset
- ✅ Consent text is clear and informative
- ✅ Without consent, metadata extracted but video not uploaded
- ✅ With consent, metadata extracted AND video uploaded

### Test 4: FFprobe Metadata Extraction

#### Create Test Video with Known Metadata
```bash
# Create a test video with GPS metadata using ffmpeg
ddev exec bash -c 'ffmpeg -f lavfi -i testsrc=duration=10:size=320x240:rate=1 \
  -metadata location="+37.7749-122.4194" \
  -metadata creation_time="2024-01-28T18:00:00" \
  /tmp/test_gps_video.mp4'

# Import this video through Drupal UI
```

#### Verify Extraction
```bash
# Check extracted metadata
ddev drush sqlq "SELECT latitude, longitude, creation_time, duration FROM skating_video_metadata WHERE videojs_media_id = [ID]"
```

#### Expected Results
- ✅ Latitude: 37.7749 (or close)
- ✅ Longitude: -122.4194 (or close)
- ✅ Creation time: 2024-01-28T18:00:00
- ✅ Duration: ~10 seconds

### Test 5: Error Handling

#### Test 5a: Missing FFprobe
```bash
# Temporarily make ffprobe unavailable
ddev exec mv /usr/bin/ffprobe /usr/bin/ffprobe.bak

# Try to upload video
# Should see error in logs

# Restore ffprobe
ddev exec mv /usr/bin/ffprobe.bak /usr/bin/ffprobe
```

#### Expected Results
- ✅ Graceful error message to user
- ✅ Error logged to watchdog
- ✅ No PHP fatal errors

#### Test 5b: Invalid Video File
1. Upload a non-video file (e.g., text file renamed to .mp4)
2. Save the entity

#### Expected Results
- ✅ Error message displayed
- ✅ No metadata record created
- ✅ No fatal errors

#### Test 5c: YouTube API Errors
1. Configure invalid YouTube credentials
2. Attempt upload with consent

#### Expected Results
- ✅ Clear error message to user
- ✅ Metadata still extracted and stored
- ✅ User can retry later

## Regression Testing

### Verify No Breaking Changes to VideoJS Media
```bash
# Create standard VideoJS Media without consent checkbox
ddev drush php-eval "
  \$media = \Drupal\videojs_media\Entity\VideoJsMedia::create([
    'type' => 'local_video',
    'name' => 'Regression Test Video',
  ]);
  \$media->save();
  echo 'Created media ID: ' . \$media->id();
"
```

#### Expected Results
- ✅ VideoJS Media entities still function normally
- ✅ No required consent for normal operations
- ✅ Module only activates with consent checkbox

## Performance Testing

### Test Large Video Files
```bash
# Upload video >100MB
# Monitor memory usage and processing time
ddev exec bash -c 'top -b -n 1 | head -20'
```

#### Expected Results
- ✅ Chunked upload works for large files
- ✅ No memory exhaustion
- ✅ Reasonable processing time

## Security Testing

### Test 1: Permission Enforcement
```bash
# Create user without admin permissions
ddev drush user:create testuser --mail="test@example.com"

# Try to access settings page
# Should be denied
```

#### Expected Results
- ✅ Settings page restricted to admin permission
- ✅ Non-admin users cannot configure YouTube API

### Test 2: SQL Injection Protection
- All database queries use parameterized queries
- Review code for direct SQL concatenation

### Test 3: File Access
- Verify files in private:// are properly protected
- Test that metadata extraction doesn't expose file paths

## Cross-Browser Testing

Test the consent form and upload functionality in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

## Validation Checklist

- [ ] Module enables without errors
- [ ] Database table created correctly
- [ ] FFprobe is available and working
- [ ] Metadata extraction works for videos with GPS
- [ ] Metadata extraction works for videos without GPS
- [ ] Form integration displays correctly
- [ ] Consent checkbox works as expected
- [ ] YouTube upload works with valid credentials
- [ ] Error handling works gracefully
- [ ] No PHP warnings or notices in logs
- [ ] PHPUnit tests pass
- [ ] PHPStan analysis passes (no errors)
- [ ] Code follows Drupal coding standards
- [ ] Configuration exports successfully with `drush cex`
- [ ] Services are properly injected (no `\Drupal::` in services)
- [ ] All PHP files have `declare(strict_types=1);`

## Common Issues and Solutions

### Issue: "ffprobe: command not found"
**Solution:** Install FFmpeg in DDEV container:
```bash
ddev exec apt-get update && ddev exec apt-get install -y ffmpeg
```

### Issue: "No metadata extracted"
**Solution:** Verify video file contains metadata:
```bash
ddev exec ffprobe -v quiet -print_format json -show_format /path/to/video.mp4
```

### Issue: YouTube upload fails
**Solution:** 
1. Check API credentials
2. Verify OAuth authentication completed
3. Check YouTube API quota
4. Review logs: `ddev drush watchdog:show --filter=skating_video_uploader`

### Issue: "Table skating_video_metadata doesn't exist"
**Solution:** Reinstall module:
```bash
ddev drush pm:uninstall skating_video_uploader -y
ddev drush en skating_video_uploader -y
```

## Test Data Cleanup

After testing, clean up test data:
```bash
# Remove test VideoJS Media entities
ddev drush entity:delete videojs_media --bundle=local_video

# Truncate metadata table
ddev drush sqlq "TRUNCATE TABLE skating_video_metadata"

# Clear cache
ddev drush cr
```

## Continuous Integration

For CI/CD pipelines, use this test script:
```bash
#!/bin/bash
set -e

# Install dependencies
ddev composer install

# Install ffmpeg
ddev exec apt-get update
ddev exec apt-get install -y ffmpeg

# Enable modules
ddev drush en videojs_media skating_video_uploader -y

# Run PHPUnit tests
ddev phpunit web/modules/custom/skating_video_uploader

# Run PHPStan
ddev phpstan analyze web/modules/custom/skating_video_uploader

# Check coding standards
ddev exec phpcs --standard=Drupal web/modules/custom/skating_video_uploader

echo "✅ All tests passed!"
```

## Next Steps After Validation

1. Export configuration: `ddev drush cex -y`
2. Commit all changes
3. Create pull request
4. Request code review
5. Deploy to staging environment
6. Final testing on staging
7. Deploy to production
