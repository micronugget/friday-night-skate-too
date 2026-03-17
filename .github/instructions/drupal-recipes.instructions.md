---
applyTo: "recipes/**"
description: Standards and conventions for Drupal CMS recipe authoring
---

# Drupal Recipe Authoring Standards

Read this before creating or modifying recipes in `recipes/`.

## Recipe Structure

Every recipe directory must contain:

```
recipes/drupal_cms_<name>/
  recipe.yml          # Required — recipe definition
  composer.json       # Required — package manifest
  config/             # Optional — exported configuration
  content/            # Optional — default content
  README.md           # Recommended
```

## recipe.yml Format

```yaml
name: 'Human-readable Recipe Name'
description: 'Brief description of what this recipe provides.'
type: 'Recipe'

# Modules to install
install:
  - module_name
  - another_module

# Other recipes this depends on
recipes:
  - drupal_cms_content_type_base

# Config actions (optional)
config:
  simple_config_update:
    system.site:
      name: 'My Site'
```

## composer.json Format

```json
{
    "name": "drupal/drupal_cms_<name>",
    "description": "Drupal CMS — <Name> recipe",
    "type": "drupal-recipe",
    "license": "GPL-2.0-or-later",
    "require": {
        "drupal/some_module": "^1.0"
    }
}
```

## Rules

- Recipe `type` must be `"drupal-recipe"` in `composer.json`
- Recipe names must be prefixed with `drupal_cms_` for CMS recipes
- All module dependencies must be declared in `composer.json`
- Config in `config/` must be exported via `drush cex` — never hand-edited
- Content in `content/` must use Drupal's default content format

## Validation

After creating or modifying a recipe:
```bash
# Validate composer.json
echo "=== Validate composer ===" && \
composer validate recipes/<name>/composer.json 2>&1 && \
echo "=== Exit: $? ==="
```

