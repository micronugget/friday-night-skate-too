---
name: Guidance — AI Agent Team Operational Framework
description: Operational framework and handoff protocols for the AI agent team. Defines agent responsibilities, success metrics, and coordination procedures for this Drupal CMS project.
tags: [guidance, workflow, coordination, handoff, team]
version: 1.0.0
---

# Guidance: AI Agent Team Operational Framework

This document provides the operational framework for the AI agent team working on this Drupal CMS project.

## 1. Agent Team Overview

Reference `.github/AGENT_DIRECTORY.md` for the complete list of available agents and their specializations.

### Agent Roles & Success Metrics

| Agent | Primary Output | Success Metric |
| :--- | :--- | :--- |
| **Architect** | Task assignments, workflows, architecture decisions | Project cohesion, feature completion |
| **Drupal Developer** | Modules, themes, recipes, Drush commands | Code quality, Drupal standards compliance |
| **Tester** | Test reports, PHPCS results, PHPUnit output | Zero regressions, coding standards pass |
| **Technical Writer** | Documentation, guides, changelog | Documentation accuracy |
| **Database Administrator** | Schema optimization, backup procedures | Query performance, data integrity |
| **Performance Engineer** | Performance audits, caching config | Page load times, Core Web Vitals |
| **Security Specialist** | Security audits, code reviews | Security posture, compliance |
| **Environment Manager** | DDEV config, CI/CD pipelines | Environment parity |
| **UX/UI Designer** | Design specs, frontend components | Visual quality, accessibility |

## 2. Terminal Command Best Practices (ALL AGENTS)

**⚠️ CRITICAL:** All agents executing terminal commands MUST follow these patterns for reliability.

See `.github/copilot-terminal-guide.md` and `.github/TERMINAL_QUICK_REF.md` for comprehensive guidance.

### The 5 Golden Rules

| Rule | Pattern | Why |
|------|---------|-----|
| **1. Never Background Output Commands** | `isBackground: false` | You need to read the result |
| **2. Always Add Markers** | `echo "=== Step ===" && cmd && echo "=== Done ==="` | Makes parsing reliable |
| **3. Capture All Output** | `command 2>&1` | Get both stdout and stderr |
| **4. Verify Explicitly** | `cmd && echo "Exit: $?"` | Don't assume success |
| **5. Limit Verbose Output** | `cmd \| head -50` | Prevent buffer overflow |

### Standard Command Pattern
```bash
echo "=== [Operation Description] ===" && \
ddev drush <command> 2>&1 | head -50 && \
EXIT_CODE=$? && \
echo "=== Exit Code: $EXIT_CODE ==="
```

## 3. Project-Specific Conventions

### Drupal Coding Standards (DO NOT violate)
- All PHP code must pass `vendor/bin/phpcs --standard=Drupal,DrupalPractice`
- Use FQCN for service calls
- PHPDoc blocks on all classes, methods, and properties
- Never hack core

### Config Management
```bash
# ✅ CORRECT — export config to code before committing
ddev drush cex -y

# ❌ WRONG — never leave config changes only in the database
# (always export with drush cex)
```

### Recipe Structure
```yaml
# ✅ CORRECT — valid recipe.yml
name: 'My Recipe'
description: 'What this recipe does'
type: 'Recipe'
install:
  - my_module
```

## 4. Handoff Protocol

When one agent completes work, they should:
1. Document what was done (files modified, logic changed)
2. Specify what still needs to be done
3. Identify the next agent in the workflow
4. Provide context for the next agent
5. Include test results or validation commands run

## 5. Standard Workflows

### Module/Recipe Development
```
Architect → Drupal Developer → Tester → Technical Writer → Architect (Review)
```

### New Recipe Creation
```
Architect → Drupal Developer (recipe) → Tester (phpcs/phpunit) → Architect
```

### Security Change
```
Architect → Security Specialist (review) → Drupal Developer (implement) → Tester → Security Specialist (validate)
```

### Performance Optimization
```
Architect → Performance Engineer → Drupal Developer → Tester → Architect (Review)
```

### Documentation Update
```
Drupal Developer (change) → Technical Writer (update docs) → Architect (review)
```
