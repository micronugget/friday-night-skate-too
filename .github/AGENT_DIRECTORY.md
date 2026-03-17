# Drupal CMS — AI Agent Directory

This directory lists all available specialized agents for the Drupal CMS project.

---

## 🏗️ Core Development Agents

### Architect
**File**: `architect.agent.md`
**Use For**:
- High-level planning and task decomposition
- Workflow orchestration across agents
- Architecture decisions and system design
- Resolving technical conflicts between agents

**Keywords**: architect, plan, design, coordinate, workflow, roadmap

---

### Drupal Developer
**File**: `developer_drupal.agent.md`
**Use For**:
- Custom module and theme development
- Drupal recipes authoring
- Drush command automation
- Configuration management (`drush cex`/`cim`)
- Deploy hook development
- Composer dependency management

**Keywords**: drupal, php, module, theme, recipe, drush, composer, config, twig

---

## ✅ Quality & Documentation Agents

### Tester (QA/QC)
**File**: `tester.agent.md`
**Use For**:
- PHPUnit test authoring and execution
- PHPCS coding standards validation
- Check mode analysis
- Regression test planning
- Post-deployment smoke testing

**Keywords**: test, phpunit, phpcs, lint, check, validate, qa

---

### Technical Writer
**File**: `technical-writer.agent.md`
**Use For**:
- Updating `README*.md` files
- Maintaining `changelog.md`
- Step-by-step "How-To" guides
- Documenting new recipe variables and config

**Keywords**: documentation, readme, guide, changelog, tutorial

---

## 🔧 Infrastructure & Operations Agents

### Environment Manager
**File**: `environment-manager.agent.md`
**Use For**:
- DDEV local environment setup
- CI/CD pipeline configuration (GitHub Actions)
- Environment parity between dev/staging/prod
- Docker and container configuration

**Keywords**: environment, ddev, staging, ci/cd, docker, github-actions

---

## 🛡️ Specialist Agents

### Database Administrator
**File**: `database-administrator.agent.md`
**Use For**:
- MySQL/MariaDB schema review
- Database backup strategy
- Query optimization and slow log analysis
- Drupal database update procedures

**Keywords**: database, mysql, mariadb, backup, restore, query, schema

---

### Performance Engineer
**File**: `performance-engineer.agent.md`
**Use For**:
- Drupal caching layer configuration
- Core Web Vitals optimization
- CSS/JS aggregation setup
- PHP opcache and Composer autoload tuning

**Keywords**: performance, caching, drupal, opcache, optimization, core-web-vitals

---

### Security Specialist
**File**: `security-specialist.agent.md`
**Use For**:
- Drupal security audits
- SSL/TLS configuration review
- Secrets and settings.php review
- OWASP Top 10 code reviews
- Deployment security review

**Keywords**: security, ssl, hardening, secrets, owasp, audit, permissions

---

### UX/UI Designer
**File**: `ux-ui-designer.agent.md`
**Use For**:
- Drupal theme design and Twig templates
- CSS/SCSS component development
- Accessible interface design (WCAG)
- Frontend prototypes for Drupal sites

**Keywords**: ux, ui, design, css, frontend, twig, theme, accessibility

---

### Guidance
**File**: `guidance.agent.md`
**Use For**:
- Understanding operational procedures
- Handoff protocol reference
- Standard workflow reference
- Project conventions clarification

---

## 🔄 Standard Workflows

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
Architect → Performance Engineer → Drupal Developer → Tester
```

### Documentation Update
```
Drupal Developer (change) → Technical Writer → Architect (review)
```

---

## 🎯 Task Matching Guide

1. **Is it about planning/architecture?** → **Architect**
2. **Is it writing a module, theme, or recipe?** → **Drupal Developer**
3. **Is it about testing or coding standards?** → **Tester**
4. **Is it about documentation?** → **Technical Writer**
5. **Is it about dev/staging environments?** → **Environment Manager**
6. **Is it about MySQL/database?** → **Database Administrator**
7. **Is it about caching/performance?** → **Performance Engineer**
8. **Is it about security/secrets/SSL?** → **Security Specialist**
9. **Is it about CSS/UX/theme design?** → **UX/UI Designer**

---

## 🚨 Important Notes

### All Agents Must:
- Follow terminal command patterns from `.github/copilot-terminal-guide.md`
- Follow **Drupal Coding Standards** for all PHP code
- Never hack core — all customizations in custom modules/themes or config
- Run `drush cex` before committing configuration changes
- Use `ddev drush` for local Drush commands

### Handoff Protocol:
When one agent completes work:
1. Document what was done (files modified and how)
2. Specify what still needs to be done
3. Identify the next agent in the workflow
4. Provide relevant test results or validation commands

---

*Last Updated: March 2026*
