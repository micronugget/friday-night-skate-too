# Drupal CMS — AI Agent Team

This directory contains agent definitions for the AI agent team working on this Drupal CMS project.

## Quick Start

1. **Read the guidance document first:** `guidance.agent.md` contains the operational framework
2. **Identify your task type** and follow the appropriate workflow
3. **Use handoff protocols** for all agent transitions
4. **Follow terminal command patterns** in `.github/copilot-terminal-guide.md`

## Agent Directory

### Core Development

| File | Agent | Quick Description |
|------|-------|-------------------|
| `architect.agent.md` | **Architect (Mission Control)** | Task decomposition, workflow orchestration, architecture |
| `developer_drupal.agent.md` | **Drupal Developer** | PHP, modules, themes, recipes, Drush, Composer |

### Quality & Documentation

| File | Agent | Quick Description |
|------|-------|-------------------|
| `tester.agent.md` | **Tester** | PHPUnit, PHPCS coding standards, regression tests |
| `technical-writer.agent.md` | **Technical Writer** | READMEs, guides, changelog |

### Specialists

| File | Agent | Quick Description |
|------|-------|-------------------|
| `security-specialist.agent.md` | **Security Specialist** | Secrets, settings.php, Drupal hardening |
| `database-administrator.agent.md` | **Database Administrator** | MySQL optimization, backups, schema |
| `performance-engineer.agent.md` | **Performance Engineer** | Drupal caching, Core Web Vitals, opcache |
| `environment-manager.agent.md` | **Environment Manager** | DDEV, staging environments, CI/CD |
| `ux-ui-designer.agent.md` | **UX/UI Designer** | Frontend design, Twig themes, CSS |

### Framework

| File | Agent | Quick Description |
|------|-------|-------------------|
| `guidance.agent.md` | **Guidance** | Operational framework, handoff protocols, conventions |

## Standard Workflows

```
# Module/Recipe Development
Architect → Drupal Developer → Tester → Technical Writer

# New Recipe Creation
Architect → Drupal Developer → Tester → Architect

# Security Review
Security Specialist → Drupal Developer → Tester → Security Specialist

# Documentation Update
Drupal Developer → Technical Writer → Architect
```

## Key Resources

- **Full directory**: `.github/AGENT_DIRECTORY.md`
- **Quick reference**: `.github/AGENT_QUICK_REFERENCE.md`
- **Operational guidance**: `.github/agents/guidance.agent.md`
- **Project standards**: `.github/copilot-instructions.md`
- **Terminal patterns**: `.github/copilot-terminal-guide.md`
