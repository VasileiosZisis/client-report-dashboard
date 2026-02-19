# includes/AGENTS.md — WordPress PHP/backend rules

Inherits all rules from /AGENTS.md.

## Nonces + permissions are required before trusting any input

1. Scope

- Any access to `$_GET`, `$_POST`, or `$_REQUEST` that influences behavior MUST be gated by:
  1. capability check (`current_user_can()` as appropriate), AND
  2. nonce validation (`check_admin_referer`, `check_ajax_referer`, or `wp_verify_nonce`).
- If the request is not privileged (e.g., public display-only), still sanitize/validate and escape, but do not pretend nonces provide security.

2. Nonces are not authorization

- Nonce checks alone are not bulletproof and MUST NOT be used as the only protection.
- Always pair with `current_user_can()` for privileged actions.

3. Nonce logic must be non-bypassable

- Do not mix nonce/capability checks with unrelated conditions in a way that creates an alternate “success” path.
- Use clear, linear “deny early” gating:
  - if capability fails → deny
  - if nonce/state fails → deny
  - else → process

4. Performance constraint

- Do NOT perform “is this a submission?” checks at plugin load time.
- Only check/process inside the specific callback that handles the request (AJAX handler, settings handler, admin page action, etc.).

## Sanitization, validation, and escaping (required)

1. Sanitize early

- Sanitize input immediately after reading it from:
  - `$_GET`, `$_POST`, `$_REQUEST`, `$_FILES`, `wp_remote_*()` responses, webhooks, etc.
- If reading from superglobals, unslash first:
  - `wp_unslash( $_POST['field'] )` then sanitize.

2. Always validate

- Validation is separate from sanitization.
- Enforce correct types and allowed values:
  - IDs: `absint()` and verify existence/ownership where relevant
  - Booleans: strict allow-list (`'0'/'1'`, `true/false`)
  - Enums: allow-list expected strings only
  - Numbers: validate ranges (min/max) when meaningful

3. Escape late (output context matters)

- Escape all output right before echo/print:
  - HTML text: `esc_html()`
  - HTML attributes: `esc_attr()`
  - URLs: `esc_url()`
  - Inline JS data: `wp_json_encode()` and/or `esc_js()` as appropriate
  - Allowed HTML output: `wp_kses_post()` or a custom `wp_kses()` allow-list
- Never output raw input values (including values read with `FILTER_UNSAFE_RAW`).

4. Use the most appropriate sanitizer for the data

- Email: `sanitize_email()`
- Keys/slugs: `sanitize_key()`
- Text fields: `sanitize_text_field()`
- Textarea: `sanitize_textarea_field()`
- URLs: `esc_url_raw()` (sanitize), `esc_url()` (escape for output)

5. PHP filter\_\* functions must actually sanitize

- When using `filter_input()`, `filter_input_array()`, `filter_var()`, or `filter_var_array()`:
  - NEVER omit the filter argument.
  - NEVER rely on PHP’s default (`FILTER_DEFAULT`) because it does not sanitize.
  - Use an appropriate sanitizing/validating filter (e.g., `FILTER_SANITIZE_NUMBER_INT` for integer-like input).
- Do not treat PHP filter\_\* sanitization as sufficient on its own for privileged actions; still validate and (when required) nonce/capability gate.

## Nonces (request origin validation)

1. When a nonce is required

- Any state-changing operation (create/update/delete, settings save, cache clear, connect/disconnect integrations, etc.)
  MUST require a nonce.
- Any input that triggers privileged behavior (even via `$_GET`) MUST be ignored unless a valid nonce is present.

2. What nonces do (and do not do)

- Nonces help protect against CSRF by validating request intent/origin.
- Nonces do NOT provide authorization and do NOT prevent replay attacks.
- Always pair nonce validation with a capability check (e.g., `current_user_can()`).

3. Use the correct nonce function by context

- Admin forms:
  - Output nonce fields with `wp_nonce_field( $action, $name )`.
  - Verify with `check_admin_referer( $action, $name )` before processing.
- Admin action links/redirects:
  - Add nonces with `wp_nonce_url( $url, $action, $name )`.
  - Verify with `wp_verify_nonce( $_GET[$name], $action )` before using any query args.
- AJAX (`wp_ajax_*`):
  - Verify early in the handler with `check_ajax_referer( $action, $query_arg )` OR `wp_verify_nonce()`.
  - Also enforce capabilities with `current_user_can()`.
- General verification:
  - `wp_verify_nonce()` returns false on failure; treat missing/invalid nonce as a hard fail (deny or ignore input).

4. Fail closed

- If nonce is missing/invalid: do not proceed. Use `wp_die( ..., 403 )` in admin contexts or return an error in APIs/AJAX.
- Do not write logic where a different branch bypasses the nonce check.

## Mandatory pattern for privileged request handlers (especially AJAX)

Any handler that changes data, exposes sensitive data, or performs privileged actions MUST follow this order:

1. Nonce check FIRST (fail closed)
2. Capability check SECOND (fail closed)
3. Validate + sanitize inputs
4. Perform action
5. Return a safe response (`wp_send_json_*`)

## Settings API (preferred for plugin settings)

1. Prefer Settings API over custom settings handling

- Use `register_setting()` to register options and centralize sanitization.
- Use a sanitize callback to validate and sanitize settings input.
- Use `add_settings_error()` to report validation problems.
- Render messages with `settings_errors()` on the settings page.

2. Do not implement custom settings submission endpoints unless necessary

- If you must implement custom submission, it MUST include:
  - capability checks
  - nonce verification
  - strict sanitization/validation
  - safe feedback messages (no raw input echoed)

3. Do not nest forms inside the Settings API form

- If using the standard Settings API `<form method="post" action="options.php">`,
  DO NOT place any additional `<form>` tags inside it.
- For additional actions on the settings page (e.g., clear cache, disconnect account):
  - use nonce-protected links (`wp_nonce_url()`) OR separate, standalone forms outside the Settings API form
  - OR use `admin-post.php` actions with their own nonce + capability checks.

4. Do not duplicate core success notices

- The Settings API already provides the “settings-updated” success notice.
- Do not add redundant “Settings saved” success notices when the core notice is present.
- Use `add_settings_error()` for validation issues; let core handle the default success flow.

## Redirect safety (especially OAuth flows)

1. Prefer safe redirects

- Use `wp_safe_redirect()` for redirects whenever possible.
- Only use `wp_redirect()` if you are intentionally redirecting to an external host and you have a strict allow-list.

2. External redirects must be allow-listed

- If redirecting to an external domain (OAuth, third-party tools):
  - allow-list the host(s) explicitly before redirecting
  - never redirect to a URL taken directly from user input
  - deny/ignore redirect requests that fail allow-list validation

3. Always `exit;` after redirect

- After `wp_safe_redirect()` / `wp_redirect()`, always call `exit;`.

## Enqueue hooks reminder (PHP side)

- Register/enqueue front-end assets on `wp_enqueue_scripts`.
- Register/enqueue admin assets on `admin_enqueue_scripts`.
- Do not print `<script>`/`<style>` tags directly from PHP except for rare, justified cases (prefer enqueue + inline helpers).

## Plugin and content directories/URLs (no hardcoding)

WordPress installations can move/rename directories. Never assume `wp-content` paths/URLs.

1. Never hardcode paths/URLs

- Do NOT assume:
  - plugins live in `wp-content/plugins`
  - uploads live in `wp-content/uploads`
  - themes live in `wp-content/themes`
- Do NOT build URLs by string concatenation to `wp-content`.

2. Use WordPress functions for paths and URLs

- For the current file:
  - `plugin_dir_path( __FILE__ )` for filesystem paths
  - `plugin_dir_url( __FILE__ )` for URLs
- For plugin-relative URLs:
  - `plugins_url( $relative, $plugin_file )`
- For content directory:
  - use WordPress-provided APIs (`content_url()`, `WP_CONTENT_DIR` only when appropriate)
- For uploads:
  - use `wp_upload_dir()` (never assume `/uploads/`).

3. Script/style enqueue URLs

- Build asset URLs using `plugin_dir_url()` / `plugins_url()` and enqueue with `wp_enqueue_script()` / `wp_enqueue_style()`.
- Do not reference assets via hardcoded `/wp-content/...` URLs.
