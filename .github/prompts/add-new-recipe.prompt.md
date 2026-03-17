---
description: "Create a new Drupal CMS recipe — generates recipe.yml, composer.json, and validates the structure."
name: "Add New Recipe"
argument-hint: "Recipe name (e.g. newsletter, events, team_members)"
agent: "agent"
---

Create a new Drupal CMS recipe for this project.

Follow all rules in [copilot-instructions.md](../copilot-instructions.md) and [drupal-recipes.instructions.md](../instructions/drupal-recipes.instructions.md).

## Brave Mode

**Proceed without asking for confirmation** on the following:

| Category | Commands |
|----------|----------|
| Reads | `cat`, `grep`, `find`, `head`, `tail`, `ls` |
| Validation | `composer validate`, `vendor/bin/phpcs` |
| Drush (read-only) | `ddev drush status`, `ddev drush pm:list` |
| Git (read) | `git status`, `git log`, `git diff` |

**Always ask before running:**
- `ddev drush cim -y` (imports config — may affect live site)
- `ddev drush updb -y` (runs update hooks)
- Any destructive database operation

---

## Step 1 — Parse Input

Extract from user input:
- **Recipe name** — e.g. `newsletter` → becomes `drupal_cms_newsletter`
- **Modules to install** — list of Drupal module machine names
- **Dependencies** — other recipes this depends on (optional)
- **Description** — what the recipe provides

---

## Step 2 — Check for Conflicts

```bash
echo "=== Check existing recipes ===" && \
ls recipes/ 2>&1 && \
echo "=== Done ==="
```

If a recipe with the same name already exists, report and stop.

---

## Step 3 — Generate Files

Create the following files:

### `recipes/drupal_cms_<name>/recipe.yml`
```yaml
name: 'Drupal CMS — <Human Name>'
description: '<Description>'
type: 'Recipe'

install:
  - <module_name>

# Add recipe dependencies if needed:
# recipes:
#   - drupal_cms_content_type_base
```

### `recipes/drupal_cms_<name>/composer.json`
```json
{
    "name": "drupal/drupal_cms_<name>",
    "description": "Drupal CMS — <Human Name> recipe",
    "type": "drupal-recipe",
    "license": "GPL-2.0-or-later",
    "require": {
        "drupal/<module_name>": "^1.0"
    }
}
```

### `recipes/drupal_cms_<name>/README.md`
Brief description of what the recipe does and how to use it.

---

## Step 4 — Validate

```bash
echo "=== Validate composer.json ===" && \
composer validate recipes/drupal_cms_<name>/composer.json 2>&1 && \
echo "=== Exit: $? ==="
```

---

## Step 5 — Summary Report

```
=== RECIPE CREATION REPORT ===
Recipe:      drupal_cms_<name>
Files:       recipe.yml, composer.json, README.md
Modules:     <list>
Validation:  ✓ PASSED

Next steps:
  1. Review generated files
  2. Add to root composer.json if needed: composer require drupal/drupal_cms_<name>
  3. Apply the recipe: ddev drush recipe recipes/drupal_cms_<name>
  4. Export config: ddev drush cex -y
```

