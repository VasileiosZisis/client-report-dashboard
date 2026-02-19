# AGENTS.md — Rules for building WordPress plugins (WordPress.org directory compatible)

These rules apply to ALL files in this repository.

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

- Respect trademarks/copyrights/project names.
- Do not use protected product names as the leading term in slugs/names without permission/ownership.

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
