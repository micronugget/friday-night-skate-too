---
name: Drupal CMS — Project Instructions
description: Drupal CMS project guidelines, architecture, conventions, and coding standards for AI agents
tags: [instructions, standards, drupal, cms, php, composer]
version: 1.0.0
---

# Project Guidelines

## Overview

Drupal CMS (`drupal/cms`) is a ready-to-use platform built on Drupal core, offering smart defaults to get started quickly and enterprise-grade tools for marketers, designers, and content creators. This project uses Composer for dependency management and is structured around Drupal recipes.

## Architecture

- **CMS Platform**: Drupal 11 (core-recommended)
- **Package Manager**: Composer 2.x
- **Recipes**: Modular feature sets in `recipes/` (each has its own `composer.json`, `recipe.yml`, config, and content)
- **Web root**: `web/` directory
- **PHP**: 8.3+

### Directory layout

```
composer.json          # Root Composer manifest
composer.lock          # Locked dependencies
recipes/               # Drupal recipes (modular feature sets)
  drupal_cms_*/        # Individual recipe packages
vendor/                # Composer-managed dependencies
web/                   # Drupal web root
  core/                # Drupal core
  modules/             # Contributed and custom modules
  themes/              # Contributed and custom themes
  sites/               # Site-specific config and files
  profiles/            # Installation profiles
```

## Build and Test

```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Run Drupal code standards check
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom

# Run PHPUnit tests
vendor/bin/phpunit

# Drush commands (if using DDEV)
ddev drush status
ddev drush cr
ddev drush cex
ddev drush cim
ddev drush updb
```

## Conventions

### Recipe structure

Each recipe in `recipes/` must include:
- `recipe.yml` — recipe definition (type, description, install, config, content)
- `composer.json` — package manifest for the recipe
- `config/` — exported configuration (optional)
- `content/` — default content (optional)

### Coding standards

- Follow **Drupal Coding Standards** for all PHP code
- Use **FQCN** for Drupal service calls
- Configuration belongs in code (export with `drush cex`, import with `drush cim`)
- Custom modules go in `web/modules/custom/`
- Custom themes go in `web/themes/custom/`

### Dependency management

- All dependencies managed via Composer
- Use `composer require` to add new packages
- Never manually edit `composer.lock`
- Use `composer.json` `patches` section for core/contrib patches

## Pitfalls

- **Never hack core** — all customizations go in custom modules/themes or config
- **Config sync** — always run `drush cex` before committing configuration changes
- **Recipe conflicts** — recipes may have overlapping config; resolve by checking `recipe.yml` dependencies
- **Composer autoload** — run `composer dump-autoload` after adding new PHP classes outside of standard module paths
- **Update hooks** — run `drush updb` after any module updates

## Key Files

- `composer.json` — root project manifest; controls recipes, modules, and Drupal version
- `web/sites/default/settings.php` — Drupal site configuration (database, trusted hosts, etc.)
- `web/sites/default/services.yml` — Drupal services configuration
- `recipes/` — all Drupal CMS recipe packages
- `vendor/bin/drush` — Drush CLI tool

## Specialized Agents

Check `.github/agents/` before implementing complex features.

| Agent | File | When to Use |
|---|---|---|
| Architect | `architect.agent.md` | Task decomposition, workflow orchestration |
| Drupal Developer | `developer_drupal.agent.md` | Modules, themes, recipes, Drush |
| Tester | `tester.agent.md` | PHPUnit, coding standards, check mode |
| Technical Writer | `technical-writer.agent.md` | README, changelog, guides |
| UX/UI Designer | `ux-ui-designer.agent.md` | Theme design, CSS, accessibility |
| Security Specialist | `security-specialist.agent.md` | Secrets, hardening, code review |
| Performance Engineer | `performance-engineer.agent.md` | Caching, optimization, Core Web Vitals |

Full agent directory: `.github/AGENT_DIRECTORY.md`

Read `.github/copilot-terminal-guide.md` for reliable terminal command patterns (always capture `2>&1`, use echo markers, `| head -50` for verbose output).
