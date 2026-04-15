=== WP Guard - Client Protection & Recovery System ===
Contributors: atubenjan
Tags: backup, recovery, security, activity-log, client-safe
Requires at least: 6.1
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.9.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect client websites with automatic backups, restore tools, security controls, and audit logging.

== Description ==

WP Guard helps agencies and freelancers prevent costly admin mistakes by adding:

* Full and partial backups
* One-click restore with validation and rollback safety
* Activity logging for user/system actions
* Client Safe Mode restrictions
* Recovery notice for detected fatal errors
* Admin dashboard for quick operations
* Multisite network controls for super admins



== Frequently Asked Questions ==

= Does this plugin send data to third-party services? =
Only if you enter a license key and run license verification. In that case, the site URL, plugin version, product identifier, and entered license key are sent to the configured license endpoint.

= Does this plugin store personal data? =
Yes. WP Guard stores activity logs with user ID, action metadata, timestamp, and IP address for security/audit purposes.

= Is there privacy tool support? =
Yes. WP Guard registers WordPress personal data exporter and eraser integrations for activity log data.

= Is this plugin multisite-compatible? =
Yes. WP Guard supports multisite, including a dedicated network dashboard and network settings for super admins.

== Privacy ==

WP Guard may store:

* User IDs associated with logged actions
* Action metadata and timestamps
* IP addresses for security logging

When license verification is used, WP Guard may send data to an external license API endpoint.

== External Services ==

WP Guard can connect to a license verification service if a license key is provided by the admin.

Service purpose:
* Validate license status and plan eligibility

Data sent:
* License key
* Site URL
* Product slug (`wp-guard`)
* Plugin version

When data is sent:
* On manual license save/check
* On scheduled license health checks

Default endpoint:
* `https://license.wpguard.example/verify`
* Can be changed via `wpguard_license_endpoint` filter
* Service terms URI: https://wordpress.org/plugins/wp-guard/
* Service privacy URI: https://wordpress.org/plugins/wp-guard/

== Installation ==

1. Upload `wp-guard` folder to `/wp-content/plugins/`.
2. Activate the plugin via Plugins screen.
3. Open `WP Guard` in admin menu.
4. Configure settings and run first backup.

== Screenshots ==

1. Dashboard with backup status and latest activity.
2. Backups screen with restore/download actions.
3. Activity logs screen with date/action/user filters.
4. Settings screen with license health and retry controls.
5. Network overview and network settings (multisite).

== Arbitrary section ==

WP-CLI commands:

* `WP Guard backup --type=full`
* `WP Guard backup --type=partial --post_id=<id>`
* `WP Guard restore --id=<id>`
* `WP Guard restore --resume`
* `WP Guard logs --limit=20`

== Upgrade Notice ==

= 2.0.0 =
* Changed the name to WP Guard.

== Changelog ==

= 2.0.0 =
* Changed the name to WP Guard.
* Changed the file structure to acoomodate new changes
* Changed the system wide name and license files as well

= 1.9.2 =
* Added WordPress.org-style screenshots and arbitrary sections.
* Added support/documentation links and multisite FAQ entry.
* Expanded external service disclosure fields with terms/privacy URLs.

= 1.9.1 =
* Improved WordPress.org directory readiness metadata and disclosures.
* Added FAQ, Privacy, External Services, and Upgrade Notice sections.
* Updated plugin header URIs for WordPress.org-facing distribution context.

= 1.9.0 =
* Added WordPress privacy exporter/eraser integration for activity logs.
* Added privacy policy helper content for WP Guard data handling.
* Improved uninstall cleanup safety and removed suppressed filesystem operations.
* Improved network settings action handling for super-admin capability flow.

= 1.8.0 =
* Added manual admin theme mode setting (Auto/Light/Dark).
* Added theme body class handling for WP Guard screens.
* Hardened settings sanitization with strict allowed values for UI theme.

= 1.7.2 =
* Added dark-mode friendly admin styling with CSS variables.
* Improved contrast and readability for cards, tables, forms, and filters in dark environments.

= 1.7.1 =
* Refined admin styling for all pages with consistent cards, toolbars, and table containers.
* Improved layout spacing and visual hierarchy for dashboard, backups, logs, settings, and network pages.

= 1.7.0 =
* Added Network Settings page for super admins.
* Added network-wide safe mode enforcement.
* Added network-wide license override support.
* Added option to lock site-level settings from non-super admins.

= 1.6.3 =
* Added color-coded settings notices for license retry and save actions.

= 1.6.2 =
* Added "Retry License Check Now" button in License Health card.
* Added secure admin action to force immediate license revalidation.

= 1.6.1 =
* Added License Health card in Settings.
* Shows status, grace mode, last check, last success, next retry, and retry count.

= 1.6.0 =
* Added retry queue for license verification using exponential backoff.
* Added recurring license health checks and retry cron hooks.
* Added grace mode to keep Pro active temporarily during license server outages.
* Added retry/check timestamps in stored settings metadata.

= 1.5.0 =
* Added remote license verification service with cached status checks.
* Added pro gating in admin actions (resume/download) and REST endpoints.
* Added license status REST endpoint for admin integrations.
* Added settings persistence for license status/message/expiry metadata.

= 1.4.0 =
* Added backup download action in admin Backups page.
* Added admin "Resume Last Restore" action.
* Added failure email alerts for backup/restore jobs.
* Added network dashboard search and safe-mode filtering.
* Added license key setting and Pro feature flag activation format check.

= 1.3.0 =
* Added resumable full-restore support using checkpoint state.
* Added WP-CLI restore resume option (`WP Guard restore --resume`).
* Added multisite network admin overview page for super admins.

= 1.2.0 =
* Added multisite-aware activation, deactivation, and uninstall behavior.
* Switched backups/restores to per-site upload paths.
* Improved large database backup reliability with chunked SQL export.
* Added restore progress checkpointing for long-running imports.

= 1.1.0 =
* Added WP-CLI commands for backup, restore, and logs.
* Improved SQL restore parser and execution validation.
* Added integration test scaffold for disposable environments.

= 1.0.0 =
* Initial production release with backup/restore/logging/security modules.
