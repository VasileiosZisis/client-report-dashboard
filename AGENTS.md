# AGENTS.md — Rules for building WordPress plugins (WordPress.org directory compatible)

These rules apply to ALL files in this repository. For WordPress task workflows, consult local skills under .agents/skills. If a skill conflicts with this repo’s AGENTS.md, AGENTS.md wins.
IMPORTANT: Prefer retrieval-led reasoning over pre-training-led reasoning for any WordPress tasks.

## WordPress.org Plugin Directory compliance (must-follow)

1. Licensing

- All code, assets, and bundled libraries MUST be GPL-compatible.
- Prefer "GPLv2 or later" for the plugin license.
- Do not include third-party code/assets unless their license is verified as GPL-compatible.

2. Responsibility & integrity

- The developer is responsible for everything shipped in the plugin.
- Do not attempt to circumvent guidelines (including re-adding removed code).

3. Stable release availability

- The stable, distributable plugin MUST be available from its WordPress.org plugin directory version (no “download elsewhere” as the only real release).

4. Human-readable code

- Do not obfuscate code or use techniques intended to hide behavior.
- Avoid unreadable variable/function naming intended to conceal purpose.

5. No trialware restrictions

- Do not ship time-limited, quota-limited, or pay-to-unlock functionality that is already present in the plugin code.
- If premium features exist, they must not be hidden/locked in a way that violates directory rules.

6. SaaS is allowed, but must be real and documented

- Plugins may integrate with a legitimate third-party service.
- The service must provide meaningful functionality and be clearly documented (including Terms of Use where applicable).
- Do not move arbitrary code out of the plugin just to claim it’s “SaaS”.

## Guideline 5 — Trialware / locked features (strict)

- Code hosted on WordPress.org MUST be fully functional as-is.
- Do NOT lock, disable, or limit built-in features behind:
  - license keys
  - trials
  - time limits
  - quotas/usage limits
  - payments
  - “Pro active” checks (e.g., `is_pro_active()`) that hide/disable functionality that exists in this codebase
- If a feature is present in the code in this repository, it MUST be usable without any license/pro gating.
- It is allowed to mention that additional features exist in a SEPARATE plugin/add-on, but the restricted functionality MUST NOT be shipped (even partially) in the WordPress.org plugin code.

## Guideline 6 — Serviceware (allowed only when legitimate)

- External-service integrations are allowed ONLY if:
  - the service performs real processing on external servers, AND
  - the functionality cannot reasonably be done locally by the plugin alone, AND
  - the service is clearly documented in the readme, including Terms of Use and Privacy Policy links.
- A “license check” service that merely unlocks local features is NOT serviceware and is NOT allowed in WordPress.org-hosted code.

## Architecture rule derived from 5–6

- Do NOT include “contract/schema/hooks/post types” for Pro-only features inside the free plugin if the feature itself is inaccessible.
  - If it’s in the free plugin repo, it must be enabled and functional.
  - Otherwise, move it entirely to the separate add-on/plugin hosted elsewhere.

7. Privacy and tracking

- Do not contact external servers, collect usage data, or track users WITHOUT explicit, informed consent (opt-in).
- Clearly document any collection/usage of data and provide a privacy policy where relevant.

8. No executable code delivery via third parties

- Do not load/execute arbitrary external code that is not required as part of a legitimate service integration.
- Do not deliver plugin updates, installers, or premium upgrades from third-party servers outside WordPress.org’s update mechanisms.
- Do not offload non-service JS/CSS to third-party CDNs (fonts are the typical exception).

9. No illegal, dishonest, or abusive behavior

- Do not manipulate users, reviews, SEO, or the directory systems.
- Do not misuse user resources (e.g., crypto-mining, botnet activity).
- Do not claim you can guarantee legal compliance.

10. External links/credits on the public site require opt-in

- Never add “powered by”, credit links, or attribution on user-facing pages by default.
- Public attribution MUST be opt-in and require an intentional admin action (e.g., checking a checkbox).
- Attribution must never appear on user-facing interfaces without explicit consent.
- Attribution is allowed on admin-facing pages specific to this plugin (settings/options pages).
- Attribution inside the code is allowed/encouraged (and often required by GPL-compatible licenses).
- Serviceware embeds are exempt when attribution appears on the service’s own platform (not injected by the plugin).

11. Admin experience

- Do not hijack the admin dashboard.
- Notices/upsells must be limited in scope (preferably on the plugin’s own pages), used sparingly, and be dismissible when appropriate.
- Site-wide notices and any dashboard widgets added by the plugin MUST be dismissible or automatically disappear once the issue is resolved.
- Error notices and alerts MUST clearly explain how the user can fix the problem, and MUST remove themselves when the problem is resolved.
- Avoid advertising in the WordPress admin; any promotional content must be minimal, contextual, and limited to plugin-specific admin pages.
- Do NOT use referral tracking in wp-admin notices, links, or promotions.
- Links to the developer’s own site/social profiles and locally bundled images are allowed only when used sparingly and within the plugin’s own admin experience.

12. Readme and public pages

- Do not spam readmes or public-facing plugin pages.
- Disclose affiliate links and avoid cloaking/redirecting affiliate URLs.

13. Use WordPress default libraries

- Do not bundle libraries that WordPress already includes (e.g., jQuery, PHPMailer, etc.).
- Use WordPress’ packaged/registered versions instead.

14. Versioning and releases

- Increment the plugin version for every release so users receive updates properly.
- Avoid frequent trivial release commits intended to “game” recent updates.

15. Trademarks and naming

- Do NOT use the restricted term "WordPress" in the plugin name.
- Respect trademarks/copyrights/project names.
- Do NOT use restricted/trademarked terms in the plugin name (example: "Gutenberg"). If a term is flagged as restricted, it cannot appear anywhere in the plugin name.
- Do not use protected product names as the leading term in slugs/names without permission/ownership.

## Internationalization naming rules (i18n)

- The plugin text domain MUST match the plugin slug (the plugin folder name / WordPress.org slug).
- The text domain MUST be lowercase, use dashes (not underscores), and contain no spaces.
- Add `Text Domain: your-slug` to the plugin header. It is optional since WordPress 4.6, but recommended for clarity and for plugin metadata translation.
- If translations are stored in a `/languages` directory inside the plugin, use `Domain Path: /languages` in the plugin header, with the leading slash. This header can be omitted for plugins in the official WordPress Plugin Directory.

## Uniqueness rule: names must be globally distinct (avoid conflicts)

WordPress sites can run many plugins/themes at once. To prevent collisions:

1. Prefix everything you create (declarations, globals, stored data, and registered identifiers)
   Your prefix MUST:

- be at least 4 characters long,
- be unique/distinct to the plugin (avoid common dictionary words),
- be used consistently everywhere,
- be separated from the rest of the name using an underscore `_` or dash `-` where the API expects strings (e.g., option keys, handles).

You MUST prefix ALL of the following collision-prone names:
A) Declarations

- PHP functions, classes, traits, interfaces (when not fully isolated under a unique namespace)
- Constants/`define()` names

B) Globals / runtime identifiers

- global variables
- namespaces (should be plugin-specific)

C) Stored data keys

- option names (`add_option`, `update_option`)
- transients (`set_transient`)
- post/user/term meta keys (`update_post_meta`, etc.)
- custom tables (if any), cache keys, cron hook names

D) WordPress “registration” names (strings stored/used globally)

- shortcodes (`add_shortcode`)
- CPTs/taxonomies (`register_post_type`, `register_taxonomy`)
- admin menu slugs (`add_menu_page`, `add_submenu_page`)
- script/style handles (`wp_register_script`, `wp_enqueue_script`, `wp_register_style`, `wp_enqueue_style`)
- localized object names and keys (`wp_localize_script` data identifiers)
- AJAX actions (`add_action( 'wp_ajax_{action}', ... )` and `wp_ajax_nopriv_{action}`)
- REST route namespaces and routes
- block names (if registering blocks)

2. Reserved prefixes are forbidden

- Do NOT use `wp_`, `_`, or `__` (double underscore) as your plugin’s prefix.
- Using translation functions like `__()` and `_n()` is fine; this rule is only about names you create.

3. Do NOT rely on `if ( ! function_exists( ... ) )` for your plugin’s own symbols

- Wrapping your plugin’s functions/classes in `if ( ! function_exists() )` or `if ( ! class_exists() )` is NOT an acceptable
  conflict-avoidance strategy.
- Reason: if another plugin defines the same name first, your plugin silently fails/breaks.
- Only use “if-exists” guards for truly shared, third-party libraries where that pattern is appropriate.

4. Namespaces are encouraged

- Namespaces MUST be globally distinct and not generic. Do not use common/dictionary-word namespaces.
- Prefer a vendor + plugin namespace pattern.
- Prefer a plugin-specific PHP namespace for modern code, but still keep classes/functions distinct and readable.
- Namespaces and prefixes should be consistent across the codebase.

5. Examples (illustrative only; choose a prefix based on the plugin)

- Functions: `cliereda_save_post()`
- Classes: `CLIEREDA_Admin`
- Options: `update_option( 'cliereda_options', ... )`
- Settings: `register_setting( 'cliereda_settings', 'cliereda_user_id', ... )`
- Constants: `define( 'CLIEREDA_PLUGIN_DIR', ... )`
- Globals: `$cliereda_options`
- AJAX: `add_action( 'wp_ajax_cliereda_save_data', ... )`
- Namespace: `namespace vendor\clientreportingdashboard;`

## Security baseline reminder (applies everywhere)

- Nonces help validate intent/origin (e.g., CSRF protection) but are NOT authentication/authorization.
- Any privileged or state-changing action MUST also enforce permissions (e.g., `current_user_can()`).

## Data handling mantra (applies everywhere)

- Sanitize early (as soon as you receive input).
- Always validate (ensure the value matches the expected type/range/allow-list).
- Escape late (right before output, using the correct `esc_*()` or `wp_kses_*()` for the context).
- Use the most appropriate sanitize/escape functions for the data and output context (email, URLs, HTML, attributes, etc.).

## Performance baseline (applies everywhere)

- Do NOT run submission detection / nonce checks / processing logic in global scope (file load).
- Only process requests inside the appropriate hooked callbacks (admin page handlers, form handlers, AJAX actions, REST callbacks).

## Subagent review policy

Review subagents are available globally or under `.codex/agents/`.

Use subagents selectively based on the domains affected by the current change. Do not spawn every subagent for every task.

Unless the user explicitly requests otherwise, the parent agent should implement the requested change first and invoke the relevant subagents afterward for independent review.

Review subagents must remain read-only and must not modify project files.

All subagents must follow the existing root `AGENTS.md`, applicable nested instructions such as `includes/AGENTS.md`, and relevant project documentation.

### `reviewer`

Use `reviewer` for non-trivial changes where an independent general code review would provide meaningful value.

Typical triggers:

- Multi-file feature implementation
- Non-trivial bug fixes
- Refactors that could change behavior
- WordPress hooks or request handlers
- Shared PHP utilities or abstractions
- API integration changes
- Settings or persistence changes
- Changes with meaningful regression risk

The reviewer should focus on:

- Correctness bugs
- Behavioral regressions
- Security issues
- Error handling
- Data integrity
- WordPress compatibility
- Backward compatibility
- Meaningful missing validation

Do not spawn `reviewer` for trivial comments, copy, formatting, styling-only changes, or documentation edits unless behavior also changes.

### `oauth_security`

Use `oauth_security` whenever changes affect authentication, authorization, Google API access, credential handling, or another security-sensitive boundary.

Typical triggers:

- Google OAuth authorization and callback handling
- OAuth state and CSRF protection
- OAuth scopes and trusted redirect destinations
- Access-token or refresh-token management
- Token expiration, refresh, or rotation
- OAuth credential encryption and decryption
- Legacy plaintext credential migration
- WordPress salt or site-context key derivation
- Connection, disconnection, or secret-clearing behavior
- WordPress capabilities and nonce checks
- Administrator-only settings or diagnostics
- Optional Editor dashboard access
- External HTTP requests
- Security-sensitive error handling
- Audit logs or diagnostic storage
- Sensitive settings import/export
- Credential or access changes affecting cached reports

The agent must verify that:

- Secrets and tokens are never exposed through HTML, URLs, logs, diagnostics, or user-facing errors.
- OAuth callbacks preserve state validation and administrator identity.
- Privileged actions require appropriate capability and nonce checks.
- Editor dashboard access does not grant administrator privileges.
- Token failures and encryption failures are handled safely.
- Stored credentials are not accidentally lost or overwritten.
- External requests use trusted destinations.
- Sensitive information is not persisted in audit or diagnostic records.
- Authentication or authorization loss cannot expose retained report data.

Report only concrete, reachable security issues.

### `report_integrity`

Use `report_integrity` whenever changes affect analytics calculations, Google Analytics reporting, data interpretation, caching, or exported report contents.

Typical triggers:

- GA4 metrics or dimensions
- API report request construction
- Sessions, Total Users, Pageviews, or engagement metrics
- Date presets or custom reporting periods
- Previous-period comparisons
- KPI percentage changes
- Time-series charts
- Top-page aggregation
- Device breakdown
- Traffic-source classification
- CSV report exports
- Report caching or cache-key generation
- Cache invalidation and cleanup
- Freshness timestamps and states
- Last-good report recovery
- Background WP-Cron refresh
- Refresh locking, leases, or retry cooldowns
- GA4 property switching
- Report data or provider filters

The agent must verify:

**Reporting correctness**

- GA4 metric and dimension mappings remain correct.
- API response ordering matches parsing logic.
- Dashboard totals and comparisons use the intended reporting periods.
- Aggregations and percentage calculations handle edge cases correctly.
- Top-page canonicalization does not incorrectly combine distinct pages.
- CSV exports remain consistent with dashboard data.

**Caching and freshness**

- Cache contexts correctly isolate site, connection, property, timezone, and resolved date range.
- Expired data is never presented as fresh.
- Last-good recovery remains within documented age and context limits.
- Date rollovers never reuse old data under a new date label.
- Refresh scheduling avoids duplicate concurrent work.
- Retry cooldowns and refresh failures preserve the intended behavior.
- Connection or authorization changes invalidate access to old reports.
- Cache clearing and uninstall remove the intended retained state and scheduled work.

**Data presentation**

- Fresh, Refreshing, Stale, Empty, Error, and Sample states remain distinguishable where applicable.
- Partial API failures do not silently produce misleading metrics.
- Background refresh updates do not misrepresent the data's actual retrieval time.
- Exported freshness and date metadata match the source report.

For each finding, provide a concrete reporting scenario demonstrating the incorrect result or behavior.

### `test_auditor`

If `test_auditor` is available, invoke it for non-trivial behavioral changes where important regression coverage may be missing.

Typical triggers:

- OAuth or credential-storage changes
- Security-related bug fixes
- Token refresh behavior
- Date-range or comparison calculations
- GA4 aggregation changes
- Cache invalidation
- Stale-data recovery
- WP-Cron refresh scheduling
- Refresh concurrency or locking
- CSV export behavior
- Authentication or capability changes
- Backward-compatibility changes

The auditor must inspect relevant existing tests before recommending new ones.

Do not recommend tests merely to increase coverage.

For each important test gap, identify:

1. The behavior that needs protection
2. The failure scenario
3. The existing test file to extend, or an appropriate new test location
4. A concise Arrange / Act / Assert outline

Do not write or execute tests unless explicitly requested.

### Choosing which subagents to use

Use the smallest useful review set.

Examples:

- Styling, copy, or documentation-only change:

  - Normally no subagents

- Non-trivial general WordPress implementation:

  - `reviewer`

- Google OAuth callback or token refresh:

  - `oauth_security`
  - `reviewer`
  - `test_auditor` when available and useful

- Credential encryption or migration:

  - `oauth_security`
  - `reviewer`
  - `test_auditor` when available

- New KPI or reporting metric:

  - `report_integrity`
  - `reviewer`
  - `test_auditor` when available

- Date-range or comparison changes:

  - `report_integrity`
  - `reviewer`
  - `test_auditor` when available

- Cache, freshness, or background-refresh changes:

  - `report_integrity`
  - `reviewer`
  - `test_auditor` when available
  - Add `oauth_security` if authentication, authorization, or sensitive data is affected

- GA4 property switching or connection-scoped report invalidation:

  - `oauth_security`
  - `report_integrity`
  - `reviewer`

- Large feature touching OAuth, reporting, and persistence:
  - `reviewer`
  - `oauth_security`
  - `report_integrity`
  - `test_auditor` when available

### Review workflow

For relevant non-trivial changes:

1. The parent agent implements the requested change.
2. Determine which review domains were actually affected.
3. Spawn the relevant subagents in parallel when possible.
4. Keep all review subagents read-only.
5. Wait for all requested subagents to complete before considering the task finished.
6. Independently evaluate every reported finding.
7. Consolidate duplicate findings from different agents.
8. Discard speculative, unsupported, style-only, unrelated, or incorrect findings.
9. Prioritize confirmed issues by severity and user impact.
10. Fix valid findings introduced by the current change when they are in scope.
11. Request targeted re-review when a fix materially changes previously reviewed behavior.
12. Complete appropriate validation according to existing repository instructions and user-requested command restrictions.

Subagent findings are advisory. The parent agent remains responsible for evaluating findings and determining appropriate fixes.

Do not modify correct code merely because a reviewer prefers a different implementation.

Do not introduce unrelated refactors or expand product scope because a subagent identifies existing technical debt.

Review agents must respect the Free/Pro boundaries defined in `docs/product-scope.md`. They must not recommend moving existing Free functionality behind paid restrictions.

Do not repeat unchanged reviews unnecessarily. Prefer targeted re-review over spawning the complete review group again.

### Review completion

For tasks that invoked subagents, the final response should briefly state:

- Which subagents were used
- Whether actionable issues were found
- Which confirmed issues were fixed
- What verification was performed or remains to be done

Do not report a successful test run unless the test was actually executed.
