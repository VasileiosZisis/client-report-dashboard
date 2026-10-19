# Security Regression Tests

Run against a local WordPress installation with the plugin active:

```powershell
wp --path="C:/path/to/wordpress" eval-file "C:/path/to/plugin/tests/security.php"
```

The runner uses real WordPress APIs with an in-memory database adapter for the
plugin's settings and audit options, plus intercepted HTTP responses. Fixture
writes and Google requests cannot reach the live site. The original database,
user, request state, and filters are restored afterwards. Never run uninstall
or change live salts to test recovery.

Available native Sodium and OpenSSL AES-256-GCM backends are tested separately.
Unavailable backends are explicitly skipped; at least one must be available.
Cases cover authenticated round trips, random nonces, malformed/tampered
envelopes, tag/nonce validation, field/site/salt separation, missing support,
legacy migration, unchanged ciphertext, blank-secret preservation, clearing,
unreadable values, persistence failures, concurrent writes, token reuse and
refresh outcomes, fingerprint invalidation, permissions, and bounded audit
retention.

Release tracking and dismissal regressions run separately:

```powershell
wp --path="C:/path/to/wordpress" eval-file "C:/path/to/plugin/tests/release-notices.php"
```

This runner isolates release options and user metadata. It covers fresh and
legacy installs, tracked/skipped upgrades, reactivation, downgrades, guarded
concurrent writes, per-site dismissal, persistence failures, page scoping,
administrator permissions, nonce-first validation, AJAX responses, and POST
fallback redirects. It does not activate, deactivate, or uninstall the plugin
on the live site.

## FREE-04 report-cache regressions

```powershell
wp --path="C:/path/to/disposable-wordpress" eval-file "C:/path/to/plugin/tests/report-cache.php"
```

Use a disposable installation without a persistent object-cache drop-in. The
runner swaps in a fresh request cache and an in-memory options/database adapter,
including WP-Cron storage, then restores the original objects, actor and filters.
HTTP responses are intercepted; fixture credentials never contact Google.

Cases cover complete/zero/partial reports, fresh and stale hits, cache-only
status, duplicate events, refresh success/failure/quota/cooldown, retention
expiry, lease ownership/recovery, concurrent conditional writes, clear and
disconnect during HTTP, storage/scheduling failures, connection/access
invalidation, equivalent encryption migration, site/timezone identities, leap
years/DST, bounded storage, cleanup and deactivation. Status handlers also test
nonce/capability/context rejection, initial snapshot reuse and CSV metadata
with formula-injection protection.

For browser checks, use intercepted GA4 responses in a disposable site. Load a
stale report, run its `cliredas_refresh_report` event, and verify automatic
replacement, range-switch cancellation, sorting/metric persistence and safe
failure feedback. Check keyboard use and desktop/mobile layout. Never seed
fixture credentials into a real connected site or execute uninstall there.

The polling controller has dependency-free timer/DOM regressions:

```powershell
node tests/dashboard-polling.js
```

They cover hidden-tab pause/resume, automatic replacement, the three-minute
deadline, obsolete-response cancellation, retention expiry while open, and
recoverable status errors. These complement the real-browser refresh check.

### FREE-04 validation record

Validated against disposable WordPress 7.1.3 on PHP 8.4.10: 87 security,
78 release-notice and 87 cache/provider assertions passed, alongside the Node
polling regressions, PHP/JavaScript syntax, PHPCS and a clean Plugin Check.
Browser checks covered automatic Refreshing-to-Fresh replacement after running
the owned cron event, range changes, persistent sorting/chart controls, Clear
Cache, console errors, desktop layout and a 390-pixel same-origin mobile frame.
Actual deactivation/uninstall removed the disposable site's cache option and
owned events. No fixture requests reached Google or an existing site's database.

Live Google OAuth/API behavior, a real multisite network and the minimum
WordPress 6.0/PHP 7.4 runtime were not exercised. Site identity/calendar boundaries
were simulated in isolated fixtures; compatibility with the declared minimums
was checked against the APIs and PHP syntax used.
