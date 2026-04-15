# WP Guard WordPress.org Submission Checklist

Use this checklist before uploading to the WordPress.org plugin directory.

## 1) Metadata and Packaging

- [ ] `wp-guard.php` plugin header values are correct (`Plugin Name`, `Version`, `Requires at least`, `Requires PHP`, `Text Domain`).
- [ ] `readme.txt` `Stable tag` matches plugin `Version`.
- [ ] No development artifacts are included in the ZIP (temp files, local configs, debug logs).
- [ ] Final package file is generated from plugin root only.

## 2) Readme Completeness

- [ ] Description clearly explains core value and features.
- [ ] FAQ answers user-impacting questions (data collection, external calls, multisite support).
- [ ] `External Services` section discloses:
  - [ ] what service is used,
  - [ ] what data is sent,
  - [ ] when data is sent,
  - [ ] service terms URL,
  - [ ] service privacy URL.
- [ ] `Screenshots` section matches actual submitted screenshots.
- [ ] `Upgrade Notice` is updated for current release.
- [ ] Changelog includes current version entry.

## 3) Security and Permissions

- [ ] All POST actions are protected with nonces.
- [ ] Capability checks are in place for all admin/network actions.
- [ ] Direct file access is blocked (`if ( ! defined( 'ABSPATH' ) ) exit;`).
- [ ] Remote requests validate/sanitize input and use escaped URLs.
- [ ] File downloads are capability-protected and path-validated.

## 4) Privacy and Data Handling

- [ ] Privacy policy content is registered via `wp_add_privacy_policy_content`.
- [ ] Personal data exporter callback works for activity logs.
- [ ] Personal data eraser callback anonymizes/removes user-linked data.
- [ ] Stored personal data is documented in readme/privacy section.

## 5) Multisite and Super Admin

- [ ] Network menu/pages are available only to `manage_network_options`.
- [ ] Network settings save path is nonce/capability protected.
- [ ] Site-level lock behavior is verified for non-super admins.
- [ ] Network option cleanup is included on uninstall.

## 6) i18n and Coding Standards

- [ ] User-facing strings are translatable with `wp-guard` text domain.
- [ ] Output is escaped (`esc_html`, `esc_attr`, `esc_url`) in views.
- [ ] Input is sanitized (`sanitize_text_field`, `sanitize_email`, `absint`, `sanitize_key`).
- [ ] No PHP syntax errors; no linter errors.

## 7) Runtime Validation

- [ ] Activation creates required tables and options.
- [ ] Deactivation clears scheduled hooks.
- [ ] Uninstall removes plugin data as intended.
- [ ] Backup, restore, logging, and settings flows work on clean install.
- [ ] License verify/retry/grace mode works as expected.

## 8) Release Readiness

- [ ] Current version ZIP created (example: `wp-guard-x.y.z.zip`).
- [ ] Changelog summary prepared for release note.
- [ ] Terms/privacy/support/doc URLs updated from placeholders to production URLs.

## Notes (WP Guard-specific)

- Default license endpoint currently uses a placeholder and should be replaced or filtered for production.
- Keep external-service disclosure synchronized with code behavior whenever endpoint/request payload changes.
