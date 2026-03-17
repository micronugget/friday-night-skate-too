# Copilot Terminal Quick Reference Card

## 🚨 CRITICAL: Read This When Running Commands

### The Golden Rules

| Rule | Pattern | Why |
|------|---------|-----|
| **1. Never Background Output Commands** | `isBackground: false` | You need to read the result |
| **2. Always Add Markers** | `echo "=== Step ===" && cmd && echo "=== Done ==="` | Makes parsing reliable |
| **3. Capture All Output** | `command 2>&1` | Get both stdout and stderr |
| **4. Verify Explicitly** | `cmd && echo "Exit: $?"` | Don't assume success |
| **5. Limit Verbose Output** | `cmd \| head -50` | Prevent buffer overflow |

## 📋 Copy-Paste Patterns

### Composer Install
```bash
echo "=== Composer Install ===" && \
composer install 2>&1 | tail -20 && \
echo "=== Exit: $? ==="
```

### Drush Cache Rebuild
```bash
echo "=== Cache Rebuild ===" && \
ddev drush cr 2>&1 && \
echo "=== Exit: $? ==="
```

### Drush Config Import
```bash
echo "=== Config Import ===" && \
ddev drush cim -y 2>&1 && \
echo "=== Exit: $? ==="
```

### PHPUnit Tests
```bash
echo "=== PHPUnit ===" && \
vendor/bin/phpunit 2>&1 | tail -40 && \
echo "=== Exit: $? ==="
```

### PHPCS Coding Standards
```bash
echo "=== PHPCS ===" && \
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom 2>&1 | head -50
```

## 🔍 Debugging Failed Commands

If nothing happened or output is missing:

```bash
# Check Drupal status
ddev drush status 2>&1 | head -20

# List enabled modules
ddev drush pm:list --status=enabled 2>&1 | head -30

# Check recent log messages
ddev drush watchdog:show --count=10 2>&1

# Validate composer config
composer validate 2>&1
```

## ✅ Verification Checklist

Before reporting "complete":

- [ ] Command ran with `isBackground: false`
- [ ] Output captured with `2>&1`
- [ ] Exit code checked (0 = success)
- [ ] Drupal status shows no errors
- [ ] You actually READ the output and confirmed success

## 🎯 Common Mistakes

| ❌ Don't Do This | ✅ Do This Instead |
|-----------------|-------------------|
| `composer install` (no output capture) | Add `2>&1 \| tail -20` |
| `isBackground: true` for output commands | `isBackground: false` |
| `drush cim` without `-y` in automation | Use `drush cim -y` |
| Long runs without `\| head -80` | Limit output |
| Assuming success | Check `$?` |
