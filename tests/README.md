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
