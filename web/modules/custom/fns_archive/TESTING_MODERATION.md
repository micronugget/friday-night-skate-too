# Testing the Moderation Workflow

## Prerequisites

This module requires a fully installed Drupal environment with DDEV for testing.

## Setup Testing Environment

```bash
# Install Drupal and dependencies
ddev composer install

# Enable the module
ddev drush en fns_archive -y

# Clear cache
ddev drush cr

# Import configuration
ddev drush cim -y
```

## Running Tests

### All Moderation Tests

Run all tests in the moderation group:

```bash
ddev phpunit --group moderation
```

### Specific Test Classes

Run unit tests only:
```bash
ddev phpunit web/modules/custom/fns_archive/tests/src/Unit/
```

Run kernel tests only:
```bash
ddev phpunit web/modules/custom/fns_archive/tests/src/Kernel/
```

### Individual Test Methods

Run a specific test method:
```bash
ddev phpunit --filter testNotifyOnSubmission web/modules/custom/fns_archive/tests/src/Unit/ModerationNotifierTest.php
```

## Test Coverage

### Unit Tests (`tests/src/Unit/ModerationNotifierTest.php`)

Tests the `ModerationNotifier` service in isolation:

- ✓ `testNotifyOnSubmission()` - Verifies submission emails sent to all moderators
- ✓ `testNotifyOnApproval()` - Verifies approval email sent to content author
- ✓ `testNotifyOnRejection()` - Verifies rejection email with reason sent to author
- ✓ `testNotifySkipsEmptyEmail()` - Ensures no emails sent when address is empty

**Coverage:** Service methods, email parameter construction, moderator lookup logic

### Kernel Tests (`tests/src/Kernel/WorkflowTransitionTest.php`)

Tests workflow transitions in a real Drupal environment:

- ✓ `testNewContentStartsInDraft()` - New content defaults to draft state
- ✓ `testDraftToReviewTransition()` - Skaters can submit content for review
- ✓ `testReviewToPublishedTransition()` - Moderators can publish content
- ✓ `testReviewToDraftTransition()` - Moderators can reject content with reason
- ✓ `testPublishedToArchivedTransition()` - Moderators can archive content
- ✓ `testArchivedToPublishedRestore()` - Moderators can restore archived content
- ✓ `testWorkflowCreatesRevisions()` - Each state change creates new revision

**Coverage:** State transitions, permission enforcement, revision creation, publish status

## Manual Testing Checklist

### As a Skater (Content Uploader)

- [ ] Create new archive_media content
- [ ] Verify starts in Draft state
- [ ] Edit content and submit for review
- [ ] Verify cannot directly publish
- [ ] Check "My Archive Uploads" view shows own content
- [ ] Verify cannot see other users' drafts

### As a Moderator

- [ ] Access moderation dashboard at `/admin/content/moderation`
- [ ] Verify can see all content in Review state
- [ ] Approve content (Review → Published)
- [ ] Verify uploader receives approval email
- [ ] Reject content (Review → Draft) with reason
- [ ] Verify uploader receives rejection email with reason
- [ ] Archive published content
- [ ] Restore archived content
- [ ] Verify can edit any archive_media content

### Email Notifications

- [ ] Submit content as skater
- [ ] Verify all moderators receive submission email
- [ ] Approve content as moderator
- [ ] Verify uploader receives approval email
- [ ] Reject content as moderator with reason in revision log
- [ ] Verify uploader receives rejection email with reason
- [ ] Check email format and links work

### Permission Testing

- [ ] Verify anonymous users see only Published content
- [ ] Verify skaters cannot access moderation dashboard
- [ ] Verify skaters cannot edit others' content
- [ ] Verify moderators can access all moderation features
- [ ] Test each state transition permission

### View Testing

- [ ] Moderation dashboard shows correct content
- [ ] Dashboard filtering works
- [ ] Dashboard sorting works
- [ ] "My Archive Uploads" view shows only user's content
- [ ] Views update after state changes

## Debugging Tips

### Enable Debug Logging

Add to `settings.local.php`:
```php
$config['system.logging']['error_level'] = 'verbose';
```

### Check Workflow Logs

```bash
ddev drush watchdog:show --filter=fns_archive
```

### Test Email Configuration

```bash
# Check site email settings
ddev drush config:get system.site mail

# Test email sending
ddev drush php-eval "mail('test@example.com', 'Test', 'Test message');"
```

### Verify Workflow Configuration

```bash
# List workflows
ddev drush config:get workflows.workflow.archive_review

# Check content type moderation
ddev drush config:get node.type.archive_media
```

### Clear Cache After Changes

```bash
ddev drush cr
```

## Known Issues

### Issue: Emails Not Sending in Local Dev

**Solution:** Configure local mail handling with MailHog or similar:
```bash
ddev config --mailhog-http-port=8026
ddev restart
```
Access MailHog at: `http://fridaynightskate.ddev.site:8026`

### Issue: State Transitions Not Available

**Solution:** 
1. Clear cache: `ddev drush cr`
2. Verify workflow is applied: Check content type settings
3. Rebuild permissions: `ddev drush php-eval "node_access_rebuild();"`

### Issue: Tests Fail with Database Errors

**Solution:** 
1. Ensure test database is configured in `phpunit.xml`
2. Run: `ddev drush sql-create --db-su=db --db-su-pw=db`

## Continuous Integration

For CI environments (GitHub Actions, etc.), ensure:

1. Drupal is fully installed before running tests
2. Database is available and configured
3. Test modules are enabled
4. Cache is cleared before test run

Example CI command:
```bash
composer install
drush site:install --db-url=mysql://user:pass@localhost/db -y
drush en fns_archive -y
drush cr
vendor/bin/phpunit --group moderation
```

## Test Maintenance

When modifying the workflow:

1. Update tests to match new states/transitions
2. Add tests for new notification scenarios
3. Update this document with new test cases
4. Ensure all tests pass before committing
5. Update test coverage percentage in documentation

## Performance Testing

For high-volume scenarios:

```bash
# Test with 100 moderators
ddev drush php-eval "for(\$i=0;\$i<100;\$i++) { \Drupal\user\Entity\User::create(['name'=>'mod'.\$i,'mail'=>'mod'.\$i.'@test.com','roles'=>['moderator']])->save(); }"

# Create 1000 test nodes
# (Add performance test script as needed)
```

## Related Documentation

- [PHPUnit Testing in Drupal](https://www.drupal.org/docs/automated-testing/phpunit-in-drupal)
- [Writing Kernel Tests](https://www.drupal.org/docs/automated-testing/phpunit-in-drupal/kernel-tests)
- [MODERATION_WORKFLOW.md](./MODERATION_WORKFLOW.md)
