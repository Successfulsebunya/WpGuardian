# WP Guardian Integration Test Scaffold

This folder provides a practical smoke/integration checklist for disposable test sites.

## Suggested environment

- Local WordPress install (or staging clone).
- PHP with `ZipArchive` enabled.
- WP-CLI available.
- Plugin active: `wp plugin activate wp-guardian`.

## Test script

1. **Activation + schema**
   - Activate plugin and verify both tables exist:
     - `wp_guardian_logs`
     - `wp_guardian_backups`
2. **Manual full backup**
   - Run `wp guardian backup --type=full`
   - Confirm new backup row and ZIP file.
3. **Partial backup**
   - Edit an existing post, save, confirm partial backup row.
   - Run `wp guardian backup --type=partial --post_id=<id>`
4. **Restore validation**
   - Pick recent backup ID.
   - Run `wp guardian restore --id=<id>`
   - Confirm command success and site loads.
5. **Log integrity**
   - Run `wp guardian logs --limit=10`
   - Ensure login/post/update events are visible.
6. **Safe mode controls**
   - Enable Safe Mode in settings.
   - Validate non-admin roles cannot install plugins/themes.
7. **Cron cleanup**
   - Set low retention in settings (test site only).
   - Trigger cleanup: `wp cron event run wpguardian_cleanup_event`
   - Confirm old rows/files are pruned.

## Conflict simulation

- Activate common plugins (security/cache/page builder).
- Repeat backup + restore commands.
- Ensure no fatals and dashboard remains reachable.

## Shared hosting simulation

- Use a large database with many posts/options.
- Execute full backup/restore during off-peak.
- Validate command memory/time and successful completion.
