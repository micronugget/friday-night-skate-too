# Responsive Image Styles - Testing & Validation Guide

## Pre-requisites

Before testing, ensure:
1. DDEV environment is running
2. Drupal is installed
3. The `fns_archive` module is updated/reinstalled

## Installation & Setup

### Step 1: Enable Required Modules
```bash
# Enable responsive_image module if not already enabled
ddev drush en responsive_image -y

# Reinstall fns_archive module to import new configs
ddev drush pm:uninstall fns_archive -y
ddev drush en fns_archive -y

# Alternative: Import configuration
ddev drush config-import -y

# Clear all caches
ddev drush cr
```

### Step 2: Verify Module Installation
```bash
# Check that responsive_image is enabled
ddev drush pm:list | grep responsive_image

# Should show: Responsive Image (responsive_image) Enabled
```

## Configuration Verification

### Test 1: Image Styles Exist
```bash
# List all image styles
ddev drush config:get image.style.archive_thumbnail
ddev drush config:get image.style.archive_medium
ddev drush config:get image.style.archive_large
ddev drush config:get image.style.archive_full
```

**Expected Result:** Each command should display the configuration with WebP effect defined.

**UI Check:**
1. Navigate to: `/admin/config/media/image-styles`
2. Verify these styles are listed:
   - Archive Thumbnail (400×400)
   - Archive Medium (800×600)
   - Archive Large (1200×900)
   - Archive Full (1920×1440)

### Test 2: Responsive Image Style Exists
```bash
ddev drush config:get responsive_image.styles.archive_responsive
```

**Expected Result:** Configuration should show mappings for all 6 Bootstrap breakpoints (xs, sm, md, lg, xl, xxl).

**UI Check:**
1. Navigate to: `/admin/config/media/responsive-image-style`
2. Click on "Archive Responsive"
3. Verify breakpoint mappings match Bootstrap 5 breakpoints

### Test 3: Media View Modes Exist
```bash
ddev drush config:get core.entity_view_mode.media.thumbnail
ddev drush config:get core.entity_view_mode.media.teaser
ddev drush config:get core.entity_view_mode.media.modal
```

**UI Check:**
1. Navigate to: `/admin/structure/display-modes/view`
2. Verify "Thumbnail", "Teaser", and "Modal" exist for Media

### Test 4: Media Display Configurations
```bash
# Check image media display configurations
ddev drush config:get core.entity_view_display.media.image.thumbnail
ddev drush config:get core.entity_view_display.media.image.teaser  
ddev drush config:get core.entity_view_display.media.image.modal
```

**UI Check:**
1. Navigate to: `/admin/structure/media/manage/image/display`
2. Select each view mode (Default, Thumbnail, Teaser, Modal)
3. Verify the Image field uses:
   - Thumbnail: Image style formatter with `archive_thumbnail`
   - Teaser: Image style formatter with `archive_medium`
   - Modal: Responsive image formatter with `archive_responsive`
4. Verify lazy loading is enabled (check under formatter settings)

### Test 5: Node Display Configurations
```bash
# Check archive_media node display configurations
ddev drush config:get core.entity_view_display.node.archive_media.thumbnail
ddev drush config:get core.entity_view_display.node.archive_media.teaser
ddev drush config:get core.entity_view_display.node.archive_media.modal
```

**Expected Result:** The `field_archive_media` field should use `entity_reference_entity_view` formatter.

**UI Check:**
1. Navigate to: `/admin/structure/types/manage/archive_media/display`
2. Select each view mode (Thumbnail, Teaser, Modal)
3. Verify "Media" field uses "Rendered entity" formatter
4. Check that view mode settings match:
   - Thumbnail → thumbnail
   - Teaser → teaser
   - Modal → modal

## Functional Testing

### Test 6: Create Test Content

1. **Upload a test image:**
   ```bash
   # Navigate to media creation
   # Or via UI: /media/add/image
   ```
   
2. **Create archive media node:**
   - Navigate to: `/node/add/archive_media`
   - Fill in required fields
   - Select/upload an image
   - Save

3. **Repeat with different image sizes** (small, medium, large) to test responsiveness

### Test 7: View Rendered Output

**Thumbnail View:**
1. Navigate to archive view with grid (usually `/archive` or taxonomy term page)
2. Right-click an image → "Inspect Element"
3. Verify:
   ```html
   <img src="/sites/default/files/styles/archive_thumbnail/public/.../image.jpg.webp"
        loading="lazy"
        width="400"
        height="400">
   ```

**Teaser View:**
1. If teaser view is used in listings
2. Check image source uses `archive_medium` style
3. Verify lazy loading attribute

**Modal View:**
1. Open item in modal (if modal functionality is implemented)
2. Inspect image element
3. Should see `<picture>` element with multiple `<source>` tags:
   ```html
   <picture>
     <source srcset="/...archive_thumbnail.jpg.webp 400w,
                     /...archive_medium.jpg.webp 800w,
                     /...archive_large.jpg.webp 1200w,
                     /...archive_full.jpg.webp 1920w"
             sizes="(max-width: 575px) 400px,
                    (max-width: 767px) 800px,
                    (max-width: 991px) 800px,
                    (max-width: 1199px) 1200px,
                    1920px"
             type="image/webp">
     <img src="/...archive_medium.jpg.webp"
          loading="lazy"
          alt="...">
   </picture>
   ```

### Test 8: WebP Format Verification

**Using Browser DevTools:**
1. Open DevTools (F12)
2. Go to Network tab
3. Filter by "Img"
4. Clear and reload page
5. Click on an image request
6. Check Headers:
   - **Content-Type:** should be `image/webp`
   - **URL:** should end with `.jpg.webp` or `.png.webp`

**Using Command Line:**
```bash
# Check generated image style directory
ddev exec ls -la /var/www/html/web/sites/default/files/styles/archive_thumbnail/public/

# Should see .webp files
```

### Test 9: Lazy Loading Verification

1. Open a page with many images
2. Open DevTools → Network tab
3. Clear network log
4. Scroll down slowly
5. Verify:
   - Images load as you scroll (not all at once)
   - "lazy" attribute present in HTML
   - Network requests occur on scroll

### Test 10: Responsive Behavior

**Desktop Testing:**
1. View page at full desktop width (1920px+)
2. Open DevTools → Network tab → Img filter
3. Note which image size loads (should be `archive_full` or `archive_large`)

**Mobile Testing:**
1. Open DevTools (F12)
2. Click device toolbar icon (Ctrl+Shift+M)
3. Select mobile device (e.g., iPhone SE)
4. Clear Network tab
5. Reload page
6. Verify smaller images load (`archive_thumbnail` or `archive_medium`)
7. Test multiple breakpoints:
   - 375px (mobile)
   - 768px (tablet)
   - 1024px (desktop)
   - 1920px (large desktop)

### Test 11: Image Cache Verification

**Test image derivative generation:**
```bash
# Clear image cache
ddev drush image-flush --all

# Visit a page with images
# Then check if derivatives were generated
ddev exec ls -la /var/www/html/web/sites/default/files/styles/
```

**Expected Result:** Should see directories for each style with generated images.

**Cache Headers:**
1. Open DevTools → Network
2. Click on an image request
3. Check Response Headers:
   - `Cache-Control`: should have max-age
   - `ETag`: should be present
   - `Last-Modified`: should be present

## Performance Testing

### Test 12: Lighthouse Audit

**Using Chrome DevTools:**
1. Open page in Chrome
2. Open DevTools (F12)
3. Go to "Lighthouse" tab
4. Select:
   - ☑ Performance
   - ☑ Best Practices
   - Device: Mobile or Desktop
5. Click "Analyze page load"

**Target Metrics:**
- **Performance Score:** >90
- **Largest Contentful Paint (LCP):** <2.5s
- **First Contentful Paint (FCP):** <1.8s
- **Cumulative Layout Shift (CLS):** <0.1
- **Total Blocking Time (TBT):** <200ms

**Image Specific Checks:**
- "Properly size images" - Should pass
- "Serve images in next-gen formats" - Should pass (WebP)
- "Efficiently encode images" - Should pass
- "Defer offscreen images" - Should pass (lazy loading)

**Using CLI:**
```bash
# Install lighthouse CLI
npm install -g lighthouse

# Run audit
lighthouse https://friday-night-skate.ddev.site \
  --output html \
  --output-path ./lighthouse-report.html \
  --view

# Or for specific page
lighthouse https://friday-night-skate.ddev.site/archive \
  --output json \
  --output-path ./lighthouse-report.json
```

### Test 13: Image Size Analysis

**Manual Check:**
1. Open DevTools → Network → Img
2. Enable "Size" column
3. Note file sizes for each image
4. Verify:
   - WebP images are smaller than JPEG equivalents
   - No single image >100KB (target)
   - Total image payload reasonable for page

**Using Command Line:**
```bash
# Check actual file sizes
ddev exec "find /var/www/html/web/sites/default/files/styles/ -name '*.webp' -exec ls -lh {} \;" | head -20
```

### Test 14: Loading Performance

1. Open DevTools → Network
2. Throttle network: "Fast 3G" or "Slow 3G"
3. Disable cache
4. Reload page
5. Measure:
   - Time to first image
   - Total images loaded
   - Which images load first (should be above-the-fold)
   - Progressive loading behavior

## Regression Testing

### Test 15: Video Media Not Affected
Since the configuration targets image media:
1. Create/view video media in archive
2. Verify videos still work correctly
3. Verify video thumbnails/posters work
4. Check that videojs functionality is not broken

### Test 16: Existing Content
1. View existing archive media nodes
2. Verify images display correctly
3. Check that old content benefits from new responsive images
4. Verify no broken images

## Troubleshooting Tests

### Test 17: Missing WebP Support
**If WebP doesn't work:**
```bash
# Check PHP GD/ImageMagick WebP support
ddev php -r "var_dump(function_exists('imagewebp'));"
# Should output: bool(true)

# Check image toolkit
ddev drush config:get system.image
# Look for toolkit: gd or imagemagick

# Check GD info
ddev php -r "print_r(gd_info());"
# Should show WebP Support => 1
```

**If not supported:**
- Install/enable WebP in PHP GD
- Or install ImageMagick with WebP support
- Or remove WebP effects from image styles (fallback)

### Test 18: Responsive Images Not Loading
**Debug steps:**
```bash
# Check if responsive_image module is enabled
ddev drush pm:list | grep responsive

# Check breakpoints (theme file, not config)
cat web/themes/custom/fridaynightskate/fridaynightskate.breakpoints.yml

# Verify responsive image style
ddev drush config:get responsive_image.styles.archive_responsive

# Clear cache
ddev drush cr

# Rebuild image derivatives
ddev drush image-flush --all
```

### Test 19: Lazy Loading Not Working
**Check:**
1. HTML attribute: `loading="lazy"` present
2. Browser support (all modern browsers)
3. JavaScript console for errors
4. Polyfill if needed for older browsers

## Documentation Tests

### Test 20: Configuration Export
```bash
# Export configuration
ddev drush config:export -y

# Check that responsive image configs are exported
ls -la config/sync/*responsive_image* config/sync/*image.style.archive*

# Commit to git
git add config/sync/
git commit -m "Export responsive image configuration"
```

### Test 21: Fresh Installation Test
**Ideal test on staging/fresh environment:**
```bash
# Fresh database
ddev drush sql:drop -y
ddev drush site:install --existing-config -y

# Or just reinstall module
ddev drush pm:uninstall fns_archive -y
ddev drush en fns_archive -y

# Verify configs imported
ddev drush config:get image.style.archive_thumbnail
```

## Success Criteria Checklist

- [ ] All 4 image styles created and visible in UI
- [ ] Responsive image style created with correct breakpoint mappings
- [ ] Media view modes exist and configured
- [ ] Node view displays render media entities correctly
- [ ] WebP format generated for images
- [ ] Lazy loading attribute present on images
- [ ] Responsive images load correct sizes at different breakpoints
- [ ] Lighthouse Performance score >90
- [ ] LCP <2.5s
- [ ] CLS <0.1
- [ ] No broken images on existing content
- [ ] Video media still works correctly
- [ ] Configuration can be exported/imported
- [ ] Documentation is clear and accurate

## Reporting Issues

If any tests fail, document:
1. **Test number and name**
2. **Expected result**
3. **Actual result**
4. **Environment details** (DDEV version, Drupal version, PHP version)
5. **Steps to reproduce**
6. **Screenshots or error messages**
7. **Browser/device if UI-related**

## Next Steps After Testing

1. ✅ All tests pass → Ready for production deployment
2. ⚠️ Some tests fail → Fix issues and retest
3. 📊 Performance not meeting targets → Optimize further
4. 📝 Update documentation with any findings
5. 🚀 Deploy to production
6. 📈 Monitor performance metrics post-deployment
