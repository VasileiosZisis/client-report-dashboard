# Plugin regression tests

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

## FREE-02 weekly owner digest regressions

```powershell
wp --path="C:/path/to/disposable-wordpress" eval-file "C:/path/to/plugin/tests/weekly-digest.php"
```

The digest and cache runners share `fixtures/class-cliredas-options-fixture.php`.
The digest runner replaces option storage, the request cache, the clock, report
loading and mail handoff, then restores the original database/cache/user and
request filters. It cannot send real mail or contact Google. Use a disposable
installation without a persistent object-cache drop-in.

Cases cover disabled 1.8.0 upgrade defaults with unchanged credentials,
non-autoloaded storage, recipient/header validation, conditional settings saves
and competing writes, opt-in, missing events, Chisinau spring/fall DST,
timezone changes, early/duplicate callbacks, Monday-only execution, crossing
midnight, fresh-only eligibility, partial/mock/stale/malformed rejection,
safe message content, lease conflicts/recovery, interrupted/unknown handoffs,
failed claim/outcome writes, scheduling failure/recovery, disable during a fetch,
connection/recipient changes, nonce/capability failures and safe bounded audit
fields. The cache runner separately exercises the built-in `get_fresh_report()`
with intercepted GA4 HTTP responses, cache reuse, stale/partial rejection,
obsolete context/date rejection and cache-disabled synchronous fetching.

For end-to-end mail capture, configure SMTP only in the disposable environment
(for example, an excluded MU plugin using `phpmailer_init`), with Mailpit on
`127.0.0.1:10013`. Never put SMTP configuration or fixture credentials into the
production plugin. Use a reserved `.test` recipient and intercepted GA4 data.
Inspect that recipient's messages in the existing Mailpit UI at
`http://localhost:10012/`; do not relay mail or delete unrelated messages.
Verify plain text, test labeling, actual dates, every report section, no HTML or
attachments, unchanged weekly scheduling after a test, no immediate email on
enablement, and duplicate callback prevention using an injected clock.

### 1.9.0 validation record

Validated on disposable WordPress 7.1.3 / PHP 8.4.10: **337 assertions** passed
(79 digest, 93 cache/provider, 87 security, 78 release notices). The JavaScript
polling regressions, PHP/JavaScript syntax checks, PHPCS and Plugin Check passed.
Release tests confirm that a 1.8.0 dismissal does not hide 1.9.0 and fresh
installations remain quiet.

LocalWP Mailpit captured exactly two messages to the reserved fixture recipient:
one controlled-Monday scheduled summary and one settings-page test using the
built-in GA4 provider with intercepted HTTP responses. A repeated scheduled
callback sent no additional message. Both captures had the expected recipient,
subject and report sections, plain-text bodies, no HTML and no attachments.
The test left weekly delivery disabled and scheduling unchanged; enabling
delivery afterwards sent no email. Brave UI checks covered saving, explicit
disable, outcome/status feedback, keyboard focus and a 390-pixel layout.

Actual disposable-site deactivation invalidated a seeded active lease and
removed digest events. Reactivation scheduled the next future Monday without
mail. Uninstall removed both digest options and a deliberately orphaned event.
Mailpit configuration remained outside distributed code. No real Google
credentials/API, live-site database, mail relay or unrelated inbox messages
were used or modified.

A real multisite network, live Google OAuth/API behavior, and the minimum
WordPress 6.0/PHP 7.4 runtime were unavailable for execution. Site isolation and
calendar boundaries use isolated fixtures; network cleanup and minimum-version
compatibility were reviewed against the existing per-site lifecycle pattern,
available APIs and PHP syntax. Mail capture verifies local handoff, not delivery
through a production mail provider or to an external inbox.
