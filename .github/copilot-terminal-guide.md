# Copilot Terminal Guide — Reliable Command Patterns

This guide ensures that all terminal commands executed by GitHub Copilot agents produce readable, parseable, verifiable output.

**Read this before running any Composer, Drush, or shell commands.**

---

## The 5 Golden Rules

| Rule | Pattern | Why |
|------|---------|-----|
| **1. Never background output commands** | `isBackground: false` | You need to read the result |
| **2. Always add echo markers** | `echo "=== Step ===" && cmd && echo "=== Done ==="` | Makes output parseable |
| **3. Capture both streams** | `command 2>&1` | Get both stdout and stderr |
| **4. Verify success explicitly** | `cmd && echo "Exit: $?"` | Don't assume success |
| **5. Limit verbose output** | `cmd \| head -50` or `\| tail -30` | Prevent buffer overflow |

---

## Standard Drupal/Composer Command Patterns

### Composer Install
```bash
echo "=== Composer Install ===" && \
composer install 2>&1 | tail -20 && \
echo "=== Exit: $? ==="
```

### Composer Update
```bash
echo "=== Composer Update ===" && \
composer update 2>&1 | tail -20 && \
echo "=== Exit: $? ==="
```

### Drush Status
```bash
echo "=== Drush Status ===" && \
ddev drush status 2>&1 && \
echo "=== Exit: $? ==="
```

### Drush Cache Rebuild
```bash
echo "=== Cache Rebuild ===" && \
ddev drush cr 2>&1 && \
echo "=== Exit: $? ==="
```

### Drush Config Export
```bash
echo "=== Config Export ===" && \
ddev drush cex -y 2>&1 && \
echo "=== Exit: $? ==="
```

### Drush Config Import
```bash
echo "=== Config Import ===" && \
ddev drush cim -y 2>&1 && \
echo "=== Exit: $? ==="
```

### Drush Update Database
```bash
echo "=== Update Database ===" && \
ddev drush updb -y 2>&1 && \
echo "=== Exit: $? ==="
```

### PHPUnit Tests
```bash
echo "=== PHPUnit ===" && \
vendor/bin/phpunit 2>&1 | tail -40 && \
echo "=== Exit: $? ==="
```

### Coding Standards Check
```bash
echo "=== PHPCS ===" && \
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom 2>&1 | head -50 && \
echo "=== Exit: $? ==="
```

---

## Debugging Patterns

### Check Drupal Status
```bash
echo "=== Drupal Status ===" && \
ddev drush status 2>&1
```

### List Installed Modules
```bash
echo "=== Module List ===" && \
ddev drush pm:list --status=enabled 2>&1 | head -40
```

### Check Recent Watchdog Logs
```bash
echo "=== Recent Logs ===" && \
ddev drush watchdog:show --count=20 2>&1
```

### Check Composer Dependencies
```bash
echo "=== Composer Outdated ===" && \
composer outdated 2>&1 | head -30
```

### Validate Composer Config
```bash
echo "=== Composer Validate ===" && \
composer validate 2>&1 && \
echo "=== Exit: $? ==="
```

---

## Output Filtering

### Show Only Errors in PHPUnit
```bash
vendor/bin/phpunit 2>&1 | grep -E "FAIL|ERROR|WARN|Tests:"
```

### Show Only Changed Config
```bash
ddev drush config:diff 2>&1 | head -30
```

---

## Common Mistakes

| ❌ Don't Do This | ✅ Do This Instead |
|-----------------|-------------------|
| `composer install` (no output capture) | Add `2>&1 \| tail -20` |
| `isBackground: true` for commands needing output | `isBackground: false` |
| Long runs without `\| head -80` | Always limit output |
| Assuming success without checking exit code | Use `&& echo "Exit: $?"` |
| `drush cim` without `-y` in automation | Use `drush cim -y` |

---

## Verification Checklist

Before reporting a command as "complete":
- [ ] Command ran with `isBackground: false`
- [ ] Output was captured with `2>&1`
- [ ] Exit code was verified (0 = success)
- [ ] Drupal status shows no errors
- [ ] You actually READ the output and can confirm success

---

*See `.github/TERMINAL_QUICK_REF.md` for a condensed reference card.*
