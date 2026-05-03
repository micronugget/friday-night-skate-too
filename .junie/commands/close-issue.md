# /close-issue

## Usage
```
/close-issue issue=<N>
```

## Purpose
Full flow for working an issue: read → branch → implement → test → commit → push.

> **Important:** Never merge PRs, auto-close issues, or run `gh pr create` automatically. Always stop after pushing. The user closes every GitHub issue manually.

## Steps

1. **Read the issue**
   ```bash
   grep -A 60 "^### #<N> " ISSUES.md
   ```

2. **Create a branch**
   ```bash
   git checkout master && git pull origin master && git checkout -b issue/<N>-<slug>
   ```

3. **Read relevant `.junie` instructions**
   - Module work → `.junie/instructions/custom-modules.md`
   - Theme work → `.junie/instructions/theme-fridaynightskate.md`
   - Always → `.junie/terminal-guide.md`

4. **Check v1 for reference** (READ-ONLY)
   ```bash
   # Example: find equivalent file in v1
   ls /home/lee/ams_projects/2025/week-21/v2/fridaynightskate/web/modules/custom/<module>/
   ```

5. **Implement** the changes in v2 only.

6. **Export config** if any config was touched
   ```bash
   ddev drush cex -y 2>&1 | tail -10
   ```

7. **Clear caches**
   ```bash
   ddev drush cr 2>&1 | tail -5
   ```

8. **Run tests**
   ```bash
   ddev phpunit 2>&1 | tail -30
   ```

9. **Run PHPCS**
   ```bash
   ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/ 2>&1 | head -30
   ```

10. **Commit**
    ```bash
    git add -A && git commit -m "Issue #<N>: <short description>"
    ```

11. **Push to origin**
    ```bash
    git push origin issue/<N>-<slug>
    ```

    Stop here. Do **not** open a PR, merge, or close the issue. The user handles all of that manually.

## Notes
- All `ddev` commands run inside the v2 project directory only.
- Never modify v1 (`/home/lee/ams_projects/2025/week-21/v2/fridaynightskate/`).
- Branch naming: `issue/$N-<slug>` where slug is a short kebab-case title.
- All PRs target `master`.
