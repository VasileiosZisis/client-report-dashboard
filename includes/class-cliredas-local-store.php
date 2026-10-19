<?php
/**
 * Conditional writes for small local records.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Avoid overwriting records changed by another request.
 */
final class CLIREDAS_Local_Store
{
    /**
     * Replace an existing option only if its stored value still matches.
     *
     * @param string $key Option name.
     * @param mixed  $old Expected value.
     * @param mixed  $replacement Replacement value.
     * @return bool
     */
    public static function compare_and_swap($key, $old, $replacement)
    {
        global $wpdb;

        if (! in_array($key, array('cliredas_settings', 'cliredas_audit_log', 'cliredas_release_state', 'cliredas_report_cache'), true)) {
            return false;
        }
        if ($replacement === $old) {
            return true;
        }

        // The byte comparison also guards against case-insensitive database collations.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Options API has no conditional update primitive; invalidate option caches immediately below.
        $changed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $wpdb->options SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
                maybe_serialize($replacement),
                $key,
                maybe_serialize($old)
            )
        );
        self::invalidate($key);
        if (1 !== $changed) {
            return false;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Preserve the WordPress Options API lifecycle for conditional writes.
        do_action("update_option_{$key}", $old, $replacement, $key);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is the core Options API hook, not a plugin-defined hook.
        do_action('updated_option', $key, $old, $replacement);
        return true;
    }

    /**
     * Discard option caches before reading a changed or conflicting record.
     *
     * @param string $key Option name.
     * @return void
     */
    public static function invalidate($key)
    {
        wp_cache_delete($key, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }
}
