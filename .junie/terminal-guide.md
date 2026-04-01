# Terminal Reliability Guide

## Core Rules — Always Follow
1. **Always read output** — use `isBackground: false` equivalent (interactive mode)
2. **Add markers** around operations so you know what succeeded
3. **Capture both stdout and stderr** with `2>&1`
4. **Verify success explicitly** — do not assume a command worked
5. **Limit verbose output** with `| head -50` or `| tail -50`

---

## Standard Pattern: Announce → Execute → Verify

```bash
echo "=== [Operation Description] ===" && \
command --with-options 2>&1 && \
echo "=== Done ===" && \
verification-command | head -20
```

---

## DDEV Commands

All CLI commands must be prefixed with `ddev`. Run them from the project root:
`/home/lee/ams_projects/2026/week-10/v1/fridaynightskate2/`

```bash
# Clear caches
echo "=== Cache rebuild ===" && ddev drush cr 2>&1 | tail -5

# Export config
echo "=== Config export ===" && ddev drush cex -y 2>&1 | tail -10

# Import config
echo "=== Config import ===" && ddev drush cim -y 2>&1 | tail -10

# Install a module
echo "=== Enable module ===" && ddev drush en <module_name> -y 2>&1 | tail -10

# Run PHPUnit
echo "=== PHPUnit ===" && ddev phpunit 2>&1 | tail -30

# Run PHPCS
echo "=== PHPCS ===" && ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/ 2>&1 | head -50

# Compile theme assets (always from inside theme dir)
echo "=== Theme build ===" && ddev exec "cd web/themes/custom/fridaynightskate && npm run dev" 2>&1 | tail -20
```

---

## Checking Site Status

```bash
ddev drush status 2>&1 | head -20
```

---

## Drush Login Link

```bash
ddev drush uli --uri=https://fridaynightskate2.ddev.site
```

---

## Avoiding Common Failures

| Problem | Solution |
|---------|----------|
| Command hangs | Add `2>&1 \| head -50` to cap output |
| Silent failure | Always check `echo "Exit: $?"` after critical commands |
| Wrong directory | Always run `ddev` commands from project root |
| Stale cache after config/module change | Always run `ddev drush cr` after changes |
| Theme assets not updating | Run `npm run dev` inside theme dir, then `ddev drush cr` |

---

## Git Operations

```bash
# Standard branch creation
git checkout master && git pull origin master && git checkout -b issue/$N-<slug>

# Commit
git add -p && git commit -m "Issue #$N: <description>"

# Push and open PR
git push origin issue/$N-<slug> && gh pr create --base master --title "Issue #$N: <title>"
```
