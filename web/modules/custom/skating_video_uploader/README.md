# Skating Video Uploader

## Overview

The Skating Video Uploader module extends the VideoJS Media module to collect GPS and timecode metadata from videos and upload them to YouTube with user consent. This module is designed specifically for skating clubs to easily share videos while preserving important metadata that would otherwise be lost when uploading directly to YouTube.

## Features

- Collects and preserves GPS location data from videos before YouTube upload
- Extracts and stores timecode metadata
- Provides a user consent mechanism for metadata collection and YouTube uploads
- Integrates seamlessly with the VideoJS Media module
- Uploads videos to YouTube using the YouTube Data API v3
- Preserves metadata locally before YouTube processing scrubs it away
- Implements OAuth 2.0 authentication for secure YouTube uploads
- Uses ffprobe for reliable metadata extraction from video files

## Requirements

- Drupal 10.3 or higher
- VideoJS Media module
- ffprobe (part of FFmpeg) installed on the server
- Google API Client library (installed via Composer)
- YouTube Data API v3 enabled in Google Cloud Console

## Installation

1. Ensure the VideoJS Media module is installed and enabled:
   ```
   ddev composer require drupal/videojs_media
   ddev drush en videojs_media
   ```

2. Install this module using Composer:
   ```
   ddev composer require drupal/skating_video_uploader
   ```

3. Enable the module:
   ```
   ddev drush en skating_video_uploader
   ```

4. Configure the YouTube API credentials at `/admin/config/media/skating-video-uploader`.

## Configuration

### YouTube API Setup

1. Create a project in the [Google Cloud Console](https://console.cloud.google.com/)
2. Enable the YouTube Data API v3
3. Create OAuth 2.0 credentials (Client ID and Client Secret)
4. Configure the authorized redirect URI to point to your site's callback URL:
   ```
   https://your-site.com/admin/config/media/skating-video-uploader/youtube/oauth-callback
   ```
5. Enter these credentials in the module's settings form
6. Click "Authenticate with YouTube" to complete the OAuth flow

### FFmpeg/ffprobe Installation

On Ubuntu 24.04 (production or DDEV):

```bash
# For DDEV environments
ddev exec apt-get update
ddev exec apt-get install -y ffmpeg

# For production Ubuntu 24.04
sudo apt-get update
sudo apt-get install -y ffmpeg
```

Verify installation:
```bash
# In DDEV
ddev exec ffprobe -version

# On production
ffprobe -version
```

### Metadata Consent Text

You can customize the consent text that users will see when uploading videos. This text should clearly explain:

- What metadata is being collected (GPS coordinates, timecode, duration, etc.)
- How it will be used (preserved for the skating community)
- That the video will be uploaded to YouTube
- Any privacy implications

## Usage

1. Create a new VideoJS Media item at `/videojs-media/add/local_video`
2. Upload a video file containing GPS metadata (e.g., from a smartphone or action camera)
3. Check the "Upload to YouTube and preserve metadata" checkbox
4. Save the entity
5. The module will automatically:
   - Extract GPS coordinates, timecode, and other metadata using ffprobe
   - Store the metadata in the local database
   - Upload the video to YouTube via the YouTube API
   - Associate the YouTube video ID with the local metadata

## How It Works

1. **Metadata Extraction**: When a VideoJS Media entity with a local video file is saved, the module uses ffprobe to extract:
   - GPS coordinates (latitude, longitude, altitude)
   - Video creation time
   - Duration
   - Timecode data
   - Frame rate

2. **Local Storage**: All extracted metadata is stored in a custom database table before the YouTube upload, ensuring it's preserved even if YouTube strips it from the uploaded file.

3. **YouTube Upload**: If the user consents, the video file is uploaded to YouTube using chunked upload for large files. The YouTube video ID is stored alongside the local metadata.

4. **Community Benefit**: The preserved metadata remains accessible on your site, providing value to the skating community for route tracking, event documentation, and historical records.

## Troubleshooting

### Video Upload Fails

- Check the YouTube API credentials in the settings form
- Ensure the OAuth flow has been completed successfully
- Check Drupal logs at `/admin/reports/dblog` for detailed error messages
- Verify that the YouTube API quota hasn't been exceeded

### Metadata Extraction Fails

- Ensure ffprobe is properly installed (run `ddev exec ffprobe -version`)
- Check that the video file contains GPS metadata
- Verify file permissions allow reading the video file
- Check Drupal logs for specific ffprobe errors

### File Access Issues

- Ensure the private files directory is properly configured
- Verify web server has read access to the file system
- For DDEV: files in `private://` are mounted correctly

## Development and Testing

### Running Tests

```bash
# PHPUnit tests
ddev phpunit web/modules/custom/skating_video_uploader

# PHPStan static analysis
ddev phpstan

# Drupal coding standards
ddev drush test-run
```

### Testing Metadata Extraction

Create a test video with GPS data or use existing videos from smartphones/action cameras that embed GPS metadata.

## Credits

Developed for the Friday Night Skate Club community to preserve valuable video metadata while leveraging YouTube's hosting infrastructure.
