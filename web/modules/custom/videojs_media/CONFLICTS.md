# Conflict Analysis: Running videojs_mediablock + videojs_media Simultaneously

This document analyzes potential conflicts when both modules are enabled on the same Drupal site during migration.

## Summary: ✅ Safe to Run Both Modules

**Good news**: The modules are **architecturally isolated** and can run simultaneously with **minimal conflicts**. However, there are a few considerations.

---

## Detailed Conflict Analysis

### ✅ No Conflicts (Safe)

#### 1. **Entity Types** - NO CONFLICT
- **videojs_mediablock**: Uses `block_content` (core entity type, bundle: `videojs_mediablock`)
- **videojs_media**: Uses `videojs_media` (custom entity type, bundles: 5 types)

**Why Safe**: Completely different entity types with separate database tables:
```
block_content_field_data           (old)
videojs_media_field_data           (new)
```

#### 2. **Database Tables** - NO CONFLICT
Each module uses its own tables:

**videojs_mediablock**:
- `block_content_field_data`
- `block_content__field_videojs_*`
- `block_content_revision__field_videojs_*`

**videojs_media**:
- `videojs_media`
- `videojs_media_field_data`
- `videojs_media_revision`
- `videojs_media__field_*`

**Why Safe**: No shared table names, no overlapping schemas.

#### 3. **Routes** - NO CONFLICT
The modules define completely different routes:

**videojs_mediablock**:
- Uses core `block_content` routes
- `/block/add/videojs_mediablock`
- `/admin/structure/block/block-content`

**videojs_media**:
- `/videojs-media/add`
- `/videojs-media/{videojs_media}`
- `/admin/content/videojs-media`
- `/admin/structure/videojs-media/types`

**Why Safe**: No route name collisions, completely different URL patterns.

#### 4. **Permissions** - NO CONFLICT
Different permission namespaces:

**videojs_mediablock**:
- `administer videojs mediablock`
- `use videojs local audio`
- `use videojs local video`
- (etc.)

**videojs_media**:
- `administer videojs media`
- `create local_video videojs media`
- `edit own local_video videojs media`
- (etc.)

**Why Safe**: Different permission names, different structure (field-based vs bundle-based).

#### 5. **Configuration** - NO CONFLICT
- **videojs_mediablock**: `block_content.type.videojs_mediablock.yml`
- **videojs_media**: `videojs_media.type.local_video.yml` (etc.)

**Why Safe**: Different config prefixes, no overlap.

---

### ⚠️ Minor Conflicts (Manageable)

#### 1. **Single Directory Component (SDC) Name Collision**

**Issue**: Both modules have a component named "Player" in the same group:

```
videojs_mediablock:player
videojs_media:player
```

**Component IDs**:
- `videojs_mediablock:player` (old)
- `videojs_media:player` (new)

**Impact**:
- ⚠️ Drupal's SDC system uses `module:component` naming, so they're technically different
- ✅ No actual conflict in most cases
- ⚠️ **Potential issue**: If a theme tries to override just `player` without module prefix, Drupal may be confused

**Solution**: Always use fully-qualified component names:
```twig
{# Good - explicit #}
{% include "videojs_mediablock:player" %}
{% include "videojs_media:player" %}

{# Bad - ambiguous #}
{% include "player" %}
```

**Migration Impact**: When migrating, you'll want to update any theme overrides or includes to use the new component name.

#### 2. **Shared Media Types** - SHARED DEPENDENCY (Good Thing!)

Both modules depend on the same Media types:
- `media.type.videojs_video`
- `media.type.videojs_audio`
- `field.storage.media.field_media_videojs_video_file`
- `field.storage.media.field_media_videojs_audio_file`

**Why This is Good**:
- ✅ Migration can reference the same media entities
- ✅ No need to migrate media files themselves
- ✅ Both modules can use the same media library items

**Impact**:
- ✅ **Positive**: Simplifies migration - media references stay valid
- ✅ **Positive**: Can gradually migrate without disrupting media

**Consideration**: Don't uninstall `videojs_mediablock` until migration is complete, as it owns these media type configs.

#### 3. **Node Modules / JavaScript Libraries**

**Current Setup**:
- `videojs_media` now has its own `node_modules/` directory (102MB)
- `videojs_mediablock` has its own `node_modules/` directory (102MB)

**Impact**:
- ⚠️ Disk space: 204MB total (duplicated libraries)
- ✅ No runtime conflict - each module loads its own
- ✅ Each module's component specifies its own library paths

**Library Loading**:
```yaml
# videojs_mediablock uses:
../../node_modules/video.js/dist/video-js.css

# videojs_media uses:
../../node_modules/video.js/dist/video-js.css
```

**Why Safe**: Drupal's library system handles this correctly. Both load the same VideoJS library, but:
- Libraries are identified by module name
- Drupal won't load the same library twice on a page
- If both modules are used on the same page, only one VideoJS library loads

---

### ⚠️ Potential User Confusion (Not Technical Conflicts)

#### 1. **Similar Names in Admin UI**
- Both in "VideoJS" package
- Both appear in module list
- Users might be confused which to use

**Solution**: Clear naming and descriptions (already done):
- `videojs_mediablock`: "Enables users to create a custom block..."
- `videojs_media`: "Content entity-based VideoJS media player..."

#### 2. **Two Add Interfaces**
Users will see two ways to add VideoJS media:
- `/block/add/videojs_mediablock` (old)
- `/videojs-media/add` (new)

**Solution**:
- Disable block creation for old module during migration
- Update documentation/training
- Consider adding admin notice

#### 3. **Two Content Listings**
Admins will see content in two places:
- `/admin/structure/block/block-content` (old blocks)
- `/admin/content/videojs-media` (new entities)

**Solution**: This is expected during migration period.

---

## Migration Workflow Impact

### Phase 1: Pre-Migration (Both Enabled)
```
✅ videojs_mediablock: ENABLED (has existing content)
✅ videojs_media: ENABLED (destination ready)
✅ videojs_media_migrate: ENABLED (ready to run)
```

**Status**: ✅ **SAFE** - No conflicts, both operational

### Phase 2: During Migration
```bash
drush migrate:import --group=videojs_media
```

**What Happens**:
1. Migration reads from `block_content` tables (videojs_mediablock)
2. Migration writes to `videojs_media` tables (videojs_media)
3. ✅ No table conflicts
4. ✅ Media references preserved (shared media types)
5. ✅ Both modules function independently

**Status**: ✅ **SAFE** - Modules operate in isolation

### Phase 3: Verification Period (Both Enabled)
```
✅ videojs_mediablock: ENABLED (old content still works)
✅ videojs_media: ENABLED (new content accessible)
```

**Benefits**:
- Can compare old vs new entities
- Can rollback migration if needed
- Old block placements still work
- Time to update references

**Status**: ✅ **SAFE** - Intentional coexistence

### Phase 4: Transition (Disable Old)
```
❌ videojs_mediablock: DISABLED
✅ videojs_media: ENABLED
❌ videojs_media_migrate: DISABLED (no longer needed)
```

**Before Disabling videojs_mediablock**:
1. ✅ Verify all content migrated
2. ✅ Update any hard-coded block references
3. ✅ Replace block placements with entity reference fields
4. ✅ Test frontend rendering
5. ⚠️ **Keep database backup**

**Status**: ✅ **SAFE** - Old module no longer needed

---

## Specific Conflict Scenarios

### Scenario 1: Both Module Entities on Same Page
**Question**: What if a page displays both old blocks and new entities?

**Answer**: ✅ **SAFE**
- Each renders using its own template
- Each loads its own component
- Drupal's library system prevents duplicate VideoJS loads
- Both players work independently
- Multi-player pause functionality works across both

### Scenario 2: Theme Overrides
**Question**: What if my theme overrides videojs_mediablock templates?

**Answer**: ⚠️ **Needs Update**
```
your-theme/templates/
  block--block-content--type--videojs-mediablock.html.twig  (old - still works)

  # Add new template for new module:
  videojs-media.html.twig  (new - add this)
  videojs-media--youtube.html.twig  (optional bundle-specific)
```

**Impact**: Need to create new theme templates for videojs_media entities.

### Scenario 3: Views
**Question**: Can I have Views for both?

**Answer**: ✅ **YES**
- Old: Views of `Content block` type, filtered to `videojs_mediablock`
- New: Views of `VideoJS Media` type, filtered by bundle
- No conflict - completely separate entity types

### Scenario 4: REST/JSON:API
**Question**: What about API endpoints?

**Answer**: ✅ **SAFE** - Different endpoints:
```
# Old
GET /jsonapi/block_content/videojs_mediablock

# New
GET /jsonapi/videojs_media/local_video
GET /jsonapi/videojs_media/youtube
```

### Scenario 5: Migrations Re-run
**Question**: What if I need to re-run migration?

**Answer**: ✅ **SAFE**
```bash
# Rollback (deletes migrated videojs_media entities)
drush migrate:rollback --group=videojs_media

# Source data unchanged (block_content still exists)

# Re-run
drush migrate:import --group=videojs_media
```

**Impact**: Old data untouched, can re-run safely.

---

## Recommendations

### ✅ DO

1. **Enable both modules during migration** - Safe and recommended
2. **Test in development first** - Always test migration on copy of production
3. **Keep backups** - Database backups before migration
4. **Verify gradually** - Check samples before disabling old module
5. **Update references incrementally** - No rush to remove old blocks
6. **Use fully-qualified component names** - `videojs_media:player` not just `player`

### ⚠️ DON'T

1. **Don't uninstall videojs_mediablock immediately** - Keep disabled but installed
2. **Don't delete old content until verified** - Keep for rollback capability
3. **Don't use ambiguous component names** - Always specify module prefix
4. **Don't forget theme updates** - New templates for new entity type

### 📋 Best Practice Migration Workflow

```bash
# 1. Enable everything (SAFE)
drush en videojs_media videojs_media_migrate

# 2. Run migration (SAFE - reads old, writes new)
drush migrate:import --group=videojs_media

# 3. Verify (both modules working)
# Check admin UI, test entities, compare rendering

# 4. Update theme templates (if needed)
# Add videojs-media.html.twig

# 5. Update content references (gradual)
# Replace block references with entity references

# 6. When confident, disable old module (SAFE)
drush pmu videojs_mediablock

# 7. Much later, uninstall old module (removes configs)
drush pmu videojs_mediablock --uninstall
```

---

## Conclusion

### Final Answer: ✅ **NO SIGNIFICANT CONFLICTS**

The two modules can **safely coexist** because:

1. ✅ **Different entity types** (block_content vs videojs_media)
2. ✅ **Different database tables** (no overlap)
3. ✅ **Different routes** (no URL collisions)
4. ✅ **Different permissions** (no namespace conflicts)
5. ✅ **Shared media types** (beneficial for migration)
6. ⚠️ **Component naming** (manageable with best practices)

### Migration Safety: ✅ **SAFE TO PROCEED**

You can confidently:
- Enable both modules simultaneously
- Run migrations while both are active
- Keep both enabled during verification period
- Gradually transition from old to new
- Rollback if needed without data loss

The modules were designed with different namespaces and entity types specifically to allow this coexistence during migration! 🎉
