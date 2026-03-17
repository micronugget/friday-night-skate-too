# Responsive Image Styles - Implementation Documentation

## Overview
This implementation adds responsive image styles optimized for Bootstrap 5 breakpoints with WebP format as default. Images are configured for lazy loading and efficient caching.

## Image Styles Created

### 1. archive_thumbnail
- **Size:** 400x400 (crop)
- **Format:** WebP
- **Usage:** Grid thumbnails, small previews
- **Config:** `image.style.archive_thumbnail.yml`

### 2. archive_medium
- **Size:** 800x600 (scale)
- **Format:** WebP
- **Usage:** Teaser view, medium displays
- **Config:** `image.style.archive_medium.yml`

### 3. archive_large
- **Size:** 1200x900 (scale)
- **Format:** WebP
- **Usage:** Large displays, desktop views
- **Config:** `image.style.archive_large.yml`

### 4. archive_full
- **Size:** 1920x1440 (scale, max dimensions)
- **Format:** WebP
- **Usage:** Full-size modal views, high-resolution displays
- **Config:** `image.style.archive_full.yml`

## Responsive Image Style

### archive_responsive
Maps image styles to Bootstrap 5 breakpoints defined in `fridaynightskate.breakpoints.yml`:

| Breakpoint | Media Query | 1x Multiplier | 2x Multiplier |
|------------|-------------|---------------|---------------|
| XS | `(max-width: 575px)` | archive_thumbnail | archive_medium |
| SM | `(min-width: 576px) and (max-width: 767px)` | archive_medium | archive_large |
| MD | `(min-width: 768px) and (max-width: 991px)` | archive_medium | archive_large |
| LG | `(min-width: 992px) and (max-width: 1199px)` | archive_large | archive_full |
| XL | `(min-width: 1200px)` | archive_large | archive_full |
| XXL | `(min-width: 1400px)` | archive_full | archive_full |

*Note: XL and XXL both apply at their respective minimum widths. At 1400px+, XXL takes precedence over XL.*

**Fallback:** `archive_medium` for browsers without responsive image support

**Config:** `responsive_image.styles.archive_responsive.yml`

## View Modes Integration

### Node View Modes
The following node view modes now render media entities with their corresponding view modes:

1. **Thumbnail** (`node.archive_media.thumbnail`)
   - Uses: `media.image.thumbnail` view mode
   - Image Style: `archive_thumbnail` (400x400)
   - Lazy Loading: ✅ Enabled

2. **Teaser** (`node.archive_media.teaser`)
   - Uses: `media.image.teaser` view mode
   - Image Style: `archive_medium` (800x600)
   - Lazy Loading: ✅ Enabled

3. **Modal** (`node.archive_media.modal`)
   - Uses: `media.image.modal` view mode
   - Responsive Style: `archive_responsive` (all breakpoints)
   - Lazy Loading: ✅ Enabled

### Media View Modes
Created three media view modes for the image bundle:

- `core.entity_view_mode.media.thumbnail.yml`
- `core.entity_view_mode.media.teaser.yml`
- `core.entity_view_mode.media.modal.yml`

Each has a corresponding display configuration:
- `core.entity_view_display.media.image.thumbnail.yml`
- `core.entity_view_display.media.image.teaser.yml`
- `core.entity_view_display.media.image.modal.yml`

## Performance Features

### 1. WebP Format
All image styles convert to WebP format for ~30-50% file size reduction compared to JPEG.

### 2. Lazy Loading
All image fields configured with `image_loading: { attribute: lazy }` to defer loading off-screen images.

### 3. Responsive Delivery
- Browser receives srcset with multiple image sizes
- sizes attribute optimized for Bootstrap 5 grid
- Browser automatically selects optimal image based on viewport and pixel density

### 4. Caching
Drupal's standard image cache applies:
- Generated images cached in `files/styles/[style_name]/`
- HTTP cache headers for client-side caching
- CDN-friendly (if configured)

## Installation

When the `fns_archive` module is installed or updated, these configurations will be automatically imported:

```bash
ddev drush en responsive_image -y
ddev drush en fns_archive -y
ddev drush cr
```

Or for existing installations:
```bash
ddev drush config-import
ddev drush cr
```

## Validation

### 1. Check Configuration Import
```bash
ddev drush cget image.style.archive_thumbnail
ddev drush cget responsive_image.styles.archive_responsive
```

### 2. Verify Image Styles
- Navigate to: `/admin/config/media/image-styles`
- Confirm all 4 archive styles are listed

### 3. Test Responsive Images
- Navigate to: `/admin/config/media/responsive-image-style`
- Confirm `archive_responsive` style exists
- Check breakpoint mappings

### 4. Inspect Generated HTML
View page source and verify:
- `<picture>` element with multiple `<source>` tags
- `srcset` attributes with multiple image URLs
- `sizes` attribute matching Bootstrap breakpoints
- `loading="lazy"` attribute on images
- WebP format in URLs (e.g., `/styles/archive_medium/public/.../image.jpg.webp`)

### 5. Network Tab Verification
Open browser DevTools Network tab:
- Filter by "Img"
- Verify only appropriate image sizes load
- Check WebP content-type: `image/webp`
- Verify lazy loading (images load as you scroll)

### 6. Performance Testing

#### Lighthouse Audit
```bash
# Install Lighthouse CLI (if needed)
npm install -g lighthouse

# Run audit
lighthouse https://your-site.ddev.site --view
```

**Target Metrics:**
- Performance Score: >90
- Largest Contentful Paint (LCP): <2.5s
- Cumulative Layout Shift (CLS): <0.1
- Image optimization: WebP images <100KB each

#### Manual Testing
1. Load archive page
2. Open DevTools > Network
3. Disable cache, reload
4. Check:
   - Total image payload size
   - Number of image requests
   - Image format (should be WebP)
   - Load times

## Browser Compatibility

### WebP Support
- Chrome/Edge: ✅ Full support
- Firefox: ✅ Full support
- Safari: ✅ Full support (14+)
- Older browsers: Drupal automatically serves JPEG fallback

### Responsive Images
- All modern browsers support `<picture>` and `srcset`
- Fallback: `archive_medium` style served to older browsers

## Troubleshooting

### Images not converting to WebP
1. Check GD/ImageMagick has WebP support:
   ```bash
   ddev php -r "var_dump(function_exists('imagewebp'));"
   ```
2. Verify image toolkit: `/admin/config/media/image-toolkit`
3. Clear image cache: `ddev drush image-flush --all`

### Responsive images not loading
1. Verify responsive_image module enabled: `ddev drush pm:list | grep responsive`
2. Check breakpoints: `cat web/themes/custom/fridaynightskate/fridaynightskate.breakpoints.yml`
3. Clear cache: `ddev drush cr`

### Lazy loading not working
- Ensure `image_loading` setting in view display config
- Check browser support (all modern browsers)
- Verify HTML output has `loading="lazy"` attribute

## Configuration Files

All configurations are in `/web/modules/custom/fns_archive/config/install/`:

### Image Styles (4)
- `image.style.archive_thumbnail.yml`
- `image.style.archive_medium.yml`
- `image.style.archive_large.yml`
- `image.style.archive_full.yml`

### Responsive Image Style (1)
- `responsive_image.styles.archive_responsive.yml`

### Media View Modes (3)
- `core.entity_view_mode.media.thumbnail.yml`
- `core.entity_view_mode.media.teaser.yml`
- `core.entity_view_mode.media.modal.yml`

### Media Entity View Displays (3)
- `core.entity_view_display.media.image.thumbnail.yml`
- `core.entity_view_display.media.image.teaser.yml`
- `core.entity_view_display.media.image.modal.yml`

### Node Entity View Displays (3 updated)
- `core.entity_view_display.node.archive_media.thumbnail.yml`
- `core.entity_view_display.node.archive_media.teaser.yml`
- `core.entity_view_display.node.archive_media.modal.yml`

## Dependencies

Added to `fns_archive.info.yml`:
- `drupal:image` - Core image handling
- `drupal:responsive_image` - Responsive image functionality

## Next Steps

1. **Install/Update Module:** Deploy and enable the updated module
2. **Test Visually:** Verify images render correctly in all view modes
3. **Run Lighthouse:** Measure performance improvements
4. **Production Deploy:** Export config and deploy to production
5. **Monitor:** Check image payload sizes and load times

## Related Documentation

- [Drupal Responsive Image Documentation](https://www.drupal.org/docs/mobile-guide/responsive-images-in-drupal-8)
- [Bootstrap 5 Breakpoints](https://getbootstrap.com/docs/5.0/layout/breakpoints/)
- [WebP Image Format](https://developers.google.com/speed/webp)
