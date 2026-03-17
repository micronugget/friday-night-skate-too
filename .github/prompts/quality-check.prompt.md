---
description: "Run a quality check pass on the project — PHPCS coding standards, PHPUnit tests, Composer validation, and Drush status."
name: "Quality Check"
argument-hint: "Optional: path to check (default: web/modules/custom)"
agent: "agent"
---

Execute a full quality check for this Drupal CMS project.

Follow all rules in [copilot-instructions.md](../copilot-instructions.md) and [copilot-terminal-guide.md](../copilot-terminal-guide.md).

## Brave Mode

**Proceed without asking for confirmation** on all checks below — they are all read-only validation commands.

---

## Step 1 — Composer Validation

```bash
echo "=== Composer Validate ===" && \
composer validate 2>&1 && \
echo "=== Exit: $? ==="
```

If validation fails → report the error and continue to next step.

---

## Step 2 — Coding Standards (PHPCS)

```bash
echo "=== PHPCS ===" && \
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom 2>&1 | head -80 && \
echo "=== Exit: $? ==="
```

Report:
- Number of errors and warnings
- Files with violations (first 10)
- Suggested fix command if violations found

---

## Step 3 — PHPUnit Tests

```bash
echo "=== PHPUnit ===" && \
vendor/bin/phpunit 2>&1 | tail -30 && \
echo "=== Exit: $? ==="
```

Report:
- Tests passed/failed/skipped counts
- Any failing test names and assertions

---

## Step 4 — Drush Status

```bash
echo "=== Drush Status ===" && \
ddev drush status 2>&1 | head -30 && \
echo "=== Exit: $? ==="
```

---

## Step 5 — Config Diff

```bash
echo "=== Config Diff ===" && \
ddev drush config:diff 2>&1 | head -30 && \
echo "=== Exit: $? ==="
```

Report any unexpected configuration differences.

---

## Step 6 — Summary Report

```
=== QUALITY CHECK REPORT ===
Composer:    ✓ VALID  (or ✗ FAILED — details)
PHPCS:       ✓ 0 errors, 0 warnings  (or ✗ N errors)
PHPUnit:     ✓ N tests passed  (or ✗ N failed)
Drush:       ✓ OK  (or ✗ errors)
Config diff: ✓ No changes  (or ⚠ N changes pending export)

Overall: ✓ PASS  (or ✗ FAIL — action required)
```

If any check fails, list the specific issues and suggested remediation steps.

