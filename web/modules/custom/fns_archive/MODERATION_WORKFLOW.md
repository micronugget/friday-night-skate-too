# Content Moderation Workflow

## Overview

The FNS Archive module implements a comprehensive content moderation workflow for archive media uploads. All user-submitted content must be reviewed by moderators before publication.

## Workflow States

The **Archive Review** workflow includes four moderation states:

### 1. Draft
- **Published:** No
- **Default State:** Yes
- **Description:** Initial state for new content or content sent back for revision
- **Who can create:** Skaters (content uploaders)

### 2. In Review
- **Published:** No
- **Description:** Content submitted for moderator review
- **Who can transition:** Skaters submit their own content

### 3. Published
- **Published:** Yes
- **Description:** Approved content visible to public
- **Who can transition:** Moderators

### 4. Archived
- **Published:** No
- **Description:** Hidden but preserved content
- **Who can transition:** Moderators

## State Transitions

### Available Transitions

| Transition | From State | To State | Who Can Use |
|------------|-----------|----------|-------------|
| Create New Draft | Draft | Draft | Skaters, Moderators |
| Submit for Review | Draft | Review | Skaters, Moderators |
| Send Back to Draft | Review | Draft | Moderators |
| Publish | Review, Draft | Published | Moderators |
| Archive | Published | Archived | Moderators |
| Restore | Archived | Published | Moderators |

## User Roles

### Skater Role
Users with the Skater role can:
- Upload new archive media content (starts in Draft state)
- Edit their own content
- Delete their own content
- Submit content for review
- View their own unpublished content
- View revision history of their content

**Permissions:**
- `access content`
- `view own unpublished content`
- `create archive_media content`
- `edit own archive_media content`
- `delete own archive_media content`
- `view archive_media revisions`
- `view latest version`
- `use archive_review transition create_new_draft`
- `use archive_review transition submit_for_review`

### Moderator Role
Users with the Moderator role can:
- View all content (published and unpublished)
- Edit any archive media content
- Delete any archive media content
- Approve content for publication
- Send content back to draft with reason
- Archive published content
- Restore archived content
- Access moderation dashboard

**Permissions:**
- All Skater permissions, plus:
- `view any unpublished content`
- `access content overview`
- `edit any archive_media content`
- `delete any archive_media content`
- `revert archive_media revisions`
- `delete archive_media revisions`
- `view all revisions`
- `use archive_review transition publish`
- `use archive_review transition send_back_to_draft`
- `use archive_review transition archive`
- `use archive_review transition restore`

### Anonymous Users
Anonymous users can only view Published content.

## Email Notifications

The workflow automatically sends email notifications at key transition points:

### 1. Submission Notification
**Trigger:** Content transitions to "In Review" state
**Recipients:** All users with Moderator role
**Content:**
- Title of submitted content
- Author name
- Direct link to content for review
- Timestamp

### 2. Approval Notification
**Trigger:** Content transitions to "Published" state
**Recipient:** Content author (uploader)
**Content:**
- Title of approved content
- Moderator name who approved it
- Link to published content
- Timestamp

### 3. Rejection Notification
**Trigger:** Content transitions from "Review" back to "Draft"
**Recipient:** Content author (uploader)
**Content:**
- Title of content
- Moderator name who sent it back
- Reason for rejection (from revision log)
- Link to edit the content
- Timestamp

## Views and Dashboards

### Moderation Dashboard (`/admin/content/moderation`)
Available to: Moderators, Administrators

Displays:
- All archive media content in "In Review" state
- Sortable by: Title, Author, Submission date, Moderation state
- Actions: Edit, Moderate (change state), Delete
- Filterable by various criteria

### My Archive Uploads (`/user/my-archive-content`)
Available to: All authenticated users

Displays:
- User's own archive media content (all states)
- Current moderation state of each item
- Actions: Edit, View, Moderate (if permitted)
- Sortable by creation date

## Technical Implementation

### Service: ModerationNotifier

Location: `src/Service/ModerationNotifier.php`

The `ModerationNotifier` service handles all email notifications:

```php
// Get the service
$notifier = \Drupal::service('fns_archive.moderation_notifier');

// Send submission notification
$notifier->notifyOnSubmission($node);

// Send approval notification
$notifier->notifyOnApproval($node);

// Send rejection notification with reason
$notifier->notifyOnRejection($node, 'Needs better images');
```

### Hooks

The module implements several hooks to trigger notifications:

- `hook_ENTITY_TYPE_presave()`: Tracks state changes
- `hook_ENTITY_TYPE_insert()`: Handles new content notifications
- `hook_ENTITY_TYPE_update()`: Triggers notifications on state transitions
- `hook_mail()`: Defines email templates

### Configuration Files

All workflow configuration is stored in `config/install/`:

- `workflows.workflow.archive_review.yml`: Workflow definition
- `user.role.moderator.yml`: Moderator role and permissions
- `user.role.skater.yml`: Skater role and permissions
- `views.view.moderation_dashboard.yml`: Moderation queue view
- `views.view.my_archive_content.yml`: User content view
- `node.type.archive_media.yml`: Content type with moderation enabled

## Workflow Usage

### For Content Uploaders (Skaters)

1. **Create Content**
   - Navigate to `/node/add/archive_media`
   - Upload media and fill in metadata
   - Save (content starts in Draft state)

2. **Submit for Review**
   - Edit your content
   - Change moderation state to "In Review"
   - Save
   - Moderators receive email notification

3. **If Rejected**
   - You receive email with reason
   - Content returns to Draft state
   - Make requested changes
   - Re-submit for review

4. **If Approved**
   - You receive approval email
   - Content is published and visible to public

### For Moderators

1. **Access Moderation Queue**
   - Navigate to `/admin/content/moderation`
   - View all content awaiting review

2. **Review Content**
   - Click on content title to view
   - Verify media quality, metadata accuracy
   - Check GPS coordinates, dates, etc.

3. **Approve Content**
   - Change moderation state to "Published"
   - Save
   - Author receives approval email

4. **Request Changes**
   - Change moderation state to "Draft"
   - Add reason in revision log message
   - Save
   - Author receives rejection email with reason

5. **Archive Content**
   - For published content that should be hidden
   - Change moderation state to "Archived"
   - Content remains in database but not visible

## Testing

### Unit Tests
- `tests/src/Unit/ModerationNotifierTest.php`
- Tests notification service methods
- Verifies email parameters and recipient logic

### Kernel Tests
- `tests/src/Kernel/WorkflowTransitionTest.php`
- Tests all state transitions
- Verifies revision creation
- Validates publish status changes

Run tests with:
```bash
ddev phpunit --group moderation
```

## Troubleshooting

### Emails Not Sending

1. Check site email configuration: `/admin/config/system/site-information`
2. Verify moderator role assignment: `/admin/people`
3. Check logs: Reports > Recent log messages
4. Test PHP mail configuration

### State Transitions Not Working

1. Clear Drupal cache: `ddev drush cr`
2. Verify workflow is applied to content type
3. Check user permissions: `/admin/people/permissions`
4. Review workflow configuration: `/admin/config/workflow/workflows`

### Notifications Going to Wrong Users

1. Verify moderator role: `/admin/people`
2. Check user email addresses
3. Review service configuration in `fns_archive.services.yml`

## Security Considerations

- Only moderators can publish content (prevents spam)
- Revision logs track all state changes
- Email addresses never exposed to non-moderators
- Access control enforced at multiple levels
- All transitions logged for audit trail

## Future Enhancements

Potential improvements to consider:

1. **Bulk Moderation:** Allow moderators to approve/reject multiple items at once
2. **Moderation Comments:** Add internal commenting system between moderators and uploaders
3. **Auto-moderation:** Implement trusted uploader status to bypass review
4. **Moderation Statistics:** Dashboard showing approval rates, response times
5. **Email Customization:** Allow site admins to customize email templates
6. **SMS Notifications:** Optional SMS alerts for urgent reviews
7. **Escalation Rules:** Auto-escalate content pending review for X days

## Related Documentation

- [Drupal Content Moderation](https://www.drupal.org/docs/8/core/modules/content-moderation)
- [Workflows Module](https://www.drupal.org/docs/8/core/modules/workflows)
- [FNS Archive Module](./README.md)
