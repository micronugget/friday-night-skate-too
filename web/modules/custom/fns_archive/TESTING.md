# Testing and Validation Guide

This document provides instructions for testing and validating the FNS Archive module.

## Automated Testing

### Quick Test (Recommended)
The repository includes an automated test script that sets up DDEV and tests the module:

```bash
# Run the automated test script
./test-fns-archive.sh
```

This script will:
1. Install DDEV (if not already installed)
2. Configure and start the DDEV project
3. Install dependencies
4. Enable required modules
5. Enable the fns_archive module
6. Create test taxonomy terms
7. Verify configuration

### GitHub Copilot Testing
When you assign an issue to @copilot, it can automatically test your code using the `.github/copilot-setup-steps.yml` workflow. This workflow:
- Boots an ephemeral Ubuntu environment
- Installs DDEV and Docker
- Sets up the project
- Tests the fns_archive module installation
- Verifies configuration

## Pre-Installation Validation

### YAML Syntax Validation
All configuration files have been validated for YAML syntax:
```bash
php -r "yaml_parse_file('path/to/config.yml') or exit(1);"
```

### PHP Syntax Validation
All PHP files have been validated for syntax errors:
```bash
php -l web/modules/custom/fns_archive/fns_archive.module
php -l web/modules/custom/fns_archive/fns_archive.install
```

## Manual Installation Testing (with DDEV)

### 1. Enable the Module
```bash
# Enable the module
ddev drush en fns_archive -y

# Clear cache
ddev drush cr
```

### 2. Verify Configuration Status
```bash
# Check configuration status
ddev drush cst

# List entity types
ddev drush entity:info node
ddev drush entity:info taxonomy_term
```

### 3. Verify Taxonomy Vocabulary
```bash
# List vocabularies
ddev drush taxonomy:info

# Should show "skate_dates" vocabulary
```

### 4. Verify Content Type
```bash
# List content types
ddev drush entity:bundle:info node

# Should show "archive_media" content type
```

### 5. Create Test Taxonomy Terms
Via UI:
1. Navigate to `/admin/structure/taxonomy/manage/skate_dates/add`
2. Add a term: "2024-01-15 - Downtown Loop"
3. Add a term: "2024-01-22 - Waterfront Route"

Via Drush:
```bash
ddev drush term:create skate_dates "2024-01-15 - Downtown Loop"
ddev drush term:create skate_dates "2024-01-22 - Waterfront Route"
```

### 6. Create Test Content
Via UI:
1. Navigate to `/node/add/archive_media`
2. Fill in the form:
   - Title: "Test Media Entry"
   - Media: Select or upload an image/video
   - Skate Date: Select "2024-01-15 - Downtown Loop"
   - GPS Coordinates: (optional) Enter coordinates
   - Timestamp: (optional) Enter a date/time
3. Save the node
4. Verify the uploader field is auto-populated with your user

### 7. Verify URL Alias
After creating test content, check the URL pattern:
- Expected format: `/archive/{skate_date}/{node_id}`
- Example: `/archive/2024-01-15-downtown-loop/1`

### 8. Verify View Modes
Check each view mode displays correctly:
- Full: `/node/{nid}`
- Teaser: `/node/{nid}/teaser`
- Thumbnail: `/node/{nid}/thumbnail`
- Modal: `/node/{nid}/modal`

### 9. Export Configuration
After successful testing, export the configuration:
```bash
ddev drush cex -y
```

## PHPUnit Testing (if available)

```bash
# Run PHPUnit tests for the module
ddev phpunit web/modules/custom/fns_archive/tests

# Run specific test
ddev phpunit --filter=ArchiveMediaTest
```

## Update Hook Testing

For existing sites, test the update hook:
```bash
# Run pending updates
ddev drush updb -y

# Verify update 10001 was executed
ddev drush updatedb:status
```

## Common Issues and Solutions

### Issue: Module won't enable
**Solution:** Check dependencies are met:
```bash
ddev drush en node field taxonomy datetime text user media geofield pathauto -y
ddev drush en fns_archive -y
```

### Issue: Fields not showing
**Solution:** Clear cache and rebuild field caches:
```bash
ddev drush cr
ddev drush entity:updates -y
```

### Issue: Pathauto pattern not working
**Solution:** Verify pathauto module is enabled:
```bash
ddev drush en pathauto -y
ddev drush pathauto:aliases-generate --all
```

### Issue: Geofield not available
**Solution:** Install and enable the geofield module:
```bash
ddev composer require drupal/geofield
ddev drush en geofield -y
```

## Validation Checklist

- [ ] Module enables without errors
- [ ] Taxonomy vocabulary "skate_dates" exists
- [ ] Content type "archive_media" exists
- [ ] All 7 fields are present on the content type
- [ ] Field "field_skate_date" is required
- [ ] View modes (Full, Teaser, Thumbnail, Modal) are configured
- [ ] Pathauto pattern generates URLs in format `/archive/{skate_date}/{node_id}`
- [ ] Uploader field auto-populates with current user
- [ ] Can create taxonomy terms via UI
- [ ] Can create archive_media nodes via UI
- [ ] Configuration exports successfully with `drush cex`
- [ ] `drush cst` shows no configuration errors

## Next Steps

After validation:
1. Export configuration: `ddev drush cex`
2. Commit configuration changes
3. Test on a clean Drupal installation
4. Document any dependencies that need to be installed first
