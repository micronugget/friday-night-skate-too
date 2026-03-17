# Quick Agent Reference Card

## 📋 All Available Specialized Agents

### 🏗️ Core Development (2 agents)
```
┌─────────────────────────────────────────────────────────────────┐
│ architect.agent.md          │ Planning, architecture, coordination│
│ developer_drupal.agent.md   │ PHP, modules, themes, recipes, drush│
└─────────────────────────────────────────────────────────────────┘
```

### ✅ Quality & Documentation (2 agents)
```
┌─────────────────────────────────────────────────────────────────┐
│ tester.agent.md             │ PHPUnit, PHPCS, coding standards   │
│ technical-writer.agent.md   │ READMEs, guides, changelog         │
└─────────────────────────────────────────────────────────────────┘
```

### 🛡️ Specialists (5 agents)
```
┌─────────────────────────────────────────────────────────────────┐
│ security-specialist.agent.md│ Secrets, SSL, OWASP, hardening     │
│ database-administrator.agent.md│ MySQL, backups, schema          │
│ performance-engineer.agent.md│ Caching, Core Web Vitals, opcache │
│ environment-manager.agent.md│ DDEV, CI/CD, GitHub Actions        │
│ ux-ui-designer.agent.md     │ Theme design, CSS, accessibility   │
└─────────────────────────────────────────────────────────────────┘
```

## 🎯 Quick Task → Agent Matcher

| I need to... | Use this agent |
|---|---|
| Write or fix a custom module | **developer_drupal.agent.md** |
| Create or modify a recipe | **developer_drupal.agent.md** |
| Update a Twig template | **developer_drupal.agent.md** |
| Run PHPUnit / PHPCS | **tester.agent.md** |
| Verify coding standards | **tester.agent.md** |
| Write/update documentation | **technical-writer.agent.md** |
| Review secrets / settings.php | **security-specialist.agent.md** |
| Harden configuration | **security-specialist.agent.md** |
| Tune caching / performance | **performance-engineer.agent.md** |
| MySQL optimization / backups | **database-administrator.agent.md** |
| Set up DDEV / staging | **environment-manager.agent.md** |
| Design Drupal theme / CSS | **ux-ui-designer.agent.md** |
| Plan architecture / roadmap | **architect.agent.md** |

## 📂 File Locations

All agents are in: `.github/agents/[agent-name].agent.md`

Example paths:
- `.github/agents/developer_drupal.agent.md`
- `.github/agents/security-specialist.agent.md`
- `.github/agents/tester.agent.md`

## 📖 More Info

- **Full directory**: `.github/AGENT_DIRECTORY.md`
- **Agent README**: `.github/agents/README.md`
- **Guidance doc**: `.github/agents/guidance.agent.md`
- **Terminal patterns**: `.github/copilot-terminal-guide.md`

## 🔄 Common Workflows

```
# Module/Recipe Development
Architect → Drupal-Dev → Tester → Tech-Writer

# New Recipe
Architect → Drupal-Dev (recipe) → Tester → Tech-Writer

# Security Review
Security-Specialist → Drupal-Dev → Tester → Security-Specialist

# Performance Change
Architect → Performance-Eng → Drupal-Dev → Tester
```

## ⚡ Quick Commands

```bash
# Cache rebuild
ddev drush cr 2>&1

# Config export
ddev drush cex -y 2>&1

# Config import
ddev drush cim -y 2>&1

# Coding standards
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom 2>&1 | head -50

# PHPUnit
vendor/bin/phpunit 2>&1 | tail -40

# Composer validate
composer validate 2>&1
```

---

*For detailed descriptions and decision tree, see: `.github/AGENT_DIRECTORY.md`*
