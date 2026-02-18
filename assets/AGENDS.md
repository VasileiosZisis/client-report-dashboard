# assets/AGENTS.md — WordPress JS/CSS/AJAX rules

Inherits all rules from /AGENTS.md.

## Enqueue scripts and styles (no raw <script>/<style> output)

1. Do not print scripts/styles directly

- Avoid outputting `<script>` or `<style>` tags directly in PHP templates/admin pages.
- Prefer WordPress enqueue APIs for performance, caching, dependency management, and compatibility.

2. Use the correct enqueue APIs

- Static JS:
  - `wp_register_script()`, `wp_enqueue_script()`
- Inline JS:
  - `wp_add_inline_script()`
- Static CSS:
  - `wp_register_style()`, `wp_enqueue_style()`
- Inline CSS:
  - `wp_add_inline_style()`

3. Use the correct hooks by context

- Front-end:
  - enqueue via `wp_enqueue_scripts`
- Admin:
  - enqueue via `admin_enqueue_scripts`
  - only use `admin_print_scripts` / `admin_print_styles` when necessary (prefer enqueue)

4. Prefer registered dependencies and plugin-relative URLs

- Use WordPress’ registered dependencies when available.
- Build asset URLs with `plugin_dir_url()` / `plugins_url()` (never hardcode `/wp-content/...`).
- Use unique, prefixed script/style handles (see /AGENTS.md prefix rules).

5. Avoid asset naming collisions (filenames and handles)

- Do not name plugin assets the same as common WordPress core/admin assets (e.g., `dashboard.css`).
- Use unique, plugin-prefixed filenames (e.g., `{prefix}-dashboard.css`, `{prefix}-admin.js`).
- Handles MUST also be unique and prefixed.

6. Script loading attributes (async/defer strategy)

- When you need `async` or `defer`, use WordPress-supported mechanisms:
  - Prefer the modern script tag attribute APIs/filters rather than manually printing script tags.
  - Keep behavior deterministic: only add attributes to the specific registered handles that need them.
- Do not add async/defer broadly to all scripts; apply narrowly to avoid dependency order bugs.

## AJAX nonce requirements (client-side)

1. All privileged AJAX requests MUST include a nonce

- Any AJAX request that changes data or returns privileged data MUST send a nonce value.

2. Nonce must be provided by PHP (not hardcoded)

- Nonces MUST be generated server-side and passed to JS via localized data (e.g., `wp_localize_script`) or an inline script.
- Do not hardcode nonce strings in JS.

3. Request must include the expected nonce parameter name

- If PHP uses `check_ajax_referer( 'action_name', 'nonce' )`, JS must send `{ nonce: <value> }`.
- Keep the parameter name consistent across JS and PHP.

4. Never call privileged endpoints without a nonce

- JS must not issue requests that change server state unless the nonce is present.
- Do not attempt to “guess”/reuse nonces outside the data provided by PHP.

5. Handle failures

- If the server responds with a nonce/capability error, JS must not retry blindly.
- Display a safe, generic error message to the user (no leaking of sensitive info).
