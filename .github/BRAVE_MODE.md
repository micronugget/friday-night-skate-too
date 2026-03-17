# Brave Mode — Autonomous Agent Operation

Brave mode enables GitHub Copilot agents to execute commands and make changes **without asking for confirmation** on standard, safe, reversible operations.

> **Inspired by Junie's brave mode.** Works with all GitHub Copilot agents.

---

## What Is Brave Mode?

By default, agents ask for permission before executing commands:
> "Should I run the coding standards check?"
> "Should I install Composer dependencies?"
> "Should I run the tests?"

In **brave mode**, agents act autonomously on safe operations:
> ✓ Ran `composer install` — dependencies installed
> ✓ Ran PHPCS — 0 errors, 2 warnings
> ✓ Ran PHPUnit — 42 tests passed
> Ready to proceed?

---

## Activation

### Enable for current session
```
Use brave mode - execute commands and make changes without asking
```

### Disable
```
Exit brave mode - ask before making changes
```

### Scoped brave mode
```
Brave mode for this task only: run all checks and fix any issues
```

---

## Safe Commands (Auto-Execute in Brave Mode)

These commands are **always safe to run without asking**:

### Composer
```bash
composer install 2>&1
composer validate 2>&1
composer outdated 2>&1 | head -30
```

### Drush (read-only)
```bash
ddev drush status 2>&1
ddev drush pm:list --status=enabled 2>&1 | head -40
ddev drush watchdog:show --count=20 2>&1
ddev drush config:diff 2>&1 | head -30
```

### Drush (cache/config — safe in dev)
```bash
ddev drush cr 2>&1
ddev drush cex -y 2>&1
```

### Code Quality
```bash
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom 2>&1 | head -50
vendor/bin/phpunit 2>&1 | tail -40
```

### File Reads
```bash
cat composer.json
cat web/sites/default/settings.php
grep -r "variable_name" web/modules/custom 2>&1
find web/modules/custom -name "*.php" 2>&1
```

### Git (Read)
```bash
git status && git diff && git log --oneline -10
```

### Git (Write — local only)
```bash
git add -p                          # Interactive staging
git commit -m "feat: description"  # Local commit
git checkout -b feature/branch      # Create branch
```

---

## Commands That Still Require Confirmation

**Always ask before running these:**

| Command | Reason |
|---------|--------|
| `ddev drush updb -y` on production | Runs database update hooks |
| `ddev drush cim -y` on production | Imports config (may overwrite live config) |
| `ddev drush entity:delete` | Destructive data operation |
| `git push origin main` or `--force` | Visible to all collaborators |
| `git push origin feature/*` (first push) | Creates remote branch |
| Any command modifying `settings.php` | Site configuration |
| `composer update drupal/core-*` | May introduce breaking changes |

---

## How to Use Brave Mode for This Project

### Example 1: Add a new recipe

**With Brave Mode:**
```
User: "Add a new recipe called drupal_cms_newsletter"
Agent: ✓ Created recipes/drupal_cms_newsletter/recipe.yml
       ✓ Created recipes/drupal_cms_newsletter/composer.json
       ✓ Ran composer validate — passed
       ✓ PHPCS: 0 errors
       Ready to test installation?
```

### Example 2: Fix a coding standards violation

**With Brave Mode:**
```
User: "Fix all PHPCS warnings in web/modules/custom/my_module/"
Agent: ✗ Initial PHPCS: 5 violations found
       ✓ Fixed: missing docblock on MyService::doThing() line 45
       ✓ Fixed: trailing whitespace in my_module.module line 12
       ✓ Fixed: short array syntax on line 78
       ✓ Second PHPCS: 0 violations
       "All coding standard issues resolved."
```

---

## Safety Mechanisms

Even in brave mode, safety is maintained through:

### 1. Version Control
All changes are in Git and reversible:
```bash
git reset --hard HEAD~1     # Undo last commit
git checkout -- file.php    # Revert specific file
```

### 2. Read before Write
Always read the current state before modifying:
```bash
ddev drush config:diff 2>&1 | head -30  # Check what would change
```

---

## For Agents: Brave Mode Command Pattern

When you see "brave mode" or "BRAVE MODE ACTIVE", use this pattern:

```bash
# Instead of asking...
"Should I run the coding standards check?"

# Just do it with proper output capture:
echo "=== PHPCS Check ===" && \
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom \
  2>&1 | head -50 && \
echo "=== Exit: $? ==="
```

Always follow patterns from `.github/copilot-terminal-guide.md`.

---

## Best Practices

1. **Use brave mode for read-only and validation work** — PHPCS, PHPUnit, `drush status` are always safe
2. **Confirm destructive operations** — Always pause before commands that modify the database or live config
3. **Trust but verify** — Review agent changes with `git diff`
4. **Set clear boundaries** — Define which operations need confirmation

---

**Version:** 1.0.0
**Created:** March 2026
**Compatibility:** All GitHub Copilot agents
**Safety Level:** High (read-first, version control, explicit confirmation for destructive ops)
