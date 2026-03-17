---
applyTo: "web/modules/custom/**"
description: Drupal coding standards for custom module development
---

# Drupal Custom Module Standards

Read this before creating or modifying code in `web/modules/custom/`.

## Module Structure

```
web/modules/custom/<module_name>/
  <module_name>.info.yml      # Required — module metadata
  <module_name>.module        # Optional — hook implementations
  <module_name>.install       # Optional — install/update hooks
  src/
    Controller/               # Route controllers
    Form/                     # Form classes
    Plugin/                   # Plugins (blocks, fields, etc.)
    Service/                  # Services
  config/
    install/                  # Default config on module install
    schema/                   # Config schema definitions
  templates/                  # Twig templates
  tests/
    src/
      Unit/                   # PHPUnit unit tests
      Kernel/                 # Kernel tests
      Functional/             # Functional tests
```

## Coding Standards

### PHP
- Follow **Drupal Coding Standards**: https://www.drupal.org/docs/develop/standards
- Use **FQCN** for all Drupal service class references
- Add **PHPDoc blocks** on all classes, methods, and properties
- Use type hints on all method parameters and return values (PHP 8.3+)

### Services
```php
// ✅ CORRECT — use dependency injection
class MyService {
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}
}

// ❌ WRONG — never use \Drupal::service() in classes
$entity_manager = \Drupal::service('entity_type.manager');
```

### Database queries
```php
// ✅ CORRECT — use Entity Query API
$query = $this->entityTypeManager->getStorage('node')->getQuery();

// ✅ CORRECT — use DBTNG with placeholders
$result = $connection->query('SELECT * FROM {node} WHERE nid = :nid', [':nid' => $nid]);

// ❌ WRONG — never interpolate variables into SQL
$result = $connection->query("SELECT * FROM {node} WHERE nid = $nid");
```

### Output escaping
```php
// ✅ CORRECT — escape output in render arrays
'#markup' => $this->t('Hello @name', ['@name' => $name]),

// ✅ CORRECT — use Xss::filter() for user HTML
$clean = Xss::filter($user_html);
```

## Validation Commands

```bash
# Check coding standards
echo "=== PHPCS ===" && \
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom 2>&1 | head -50 && \
echo "=== Exit: $? ==="

# Auto-fix coding standards
vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom 2>&1 | head -30

# Run PHPUnit tests
echo "=== PHPUnit ===" && \
vendor/bin/phpunit web/modules/custom/<module>/tests 2>&1 | tail -20 && \
echo "=== Exit: $? ==="
```

