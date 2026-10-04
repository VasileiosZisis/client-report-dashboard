<?php
/**
 * Centralized credential persistence and migration.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Protect sensitive option values on every normal Options API write.
 */
final class CLIREDAS_Credential_Store
{
    const OPTION_KEY = 'cliredas_settings';

    /**
     * Encryption service.
     *
     * @var CLIREDAS_Credential_Crypto
     */
    private $crypto;
    /**
     * Last storage error for this instance.
     *
     * @var WP_Error|null
     */
    private $last_error;
    /**
     * Shared storage service.
     *
     * @var CLIREDAS_Credential_Store|null
     */
    private static $instance;

    /**
     * Create a storage service.
     *
     * @param CLIREDAS_Credential_Crypto|null $crypto Optional encryption service.
     */
    public function __construct($crypto = null)
    {
        $this->crypto = $crypto ? $crypto : new CLIREDAS_Credential_Crypto();
    }

    /**
     * Return the service registering the storage boundary once.
     *
     * @return CLIREDAS_Credential_Store
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
            add_filter('sanitize_option_cliredas_settings', array(self::$instance, 'filter_storage'), 100);
            add_action('admin_init', array(self::$instance, 'maybe_migrate'), 20);
        }
        return self::$instance;
    }

    /**
     * Return only predefined errors suitable for HTML, notices, and audit mapping.
     *
     * @param string $code Error code.
     * @return WP_Error
     */
    public static function error($code)
    {
        $messages = array(
            'credential_decryption_failed' => __('Saved credentials cannot be decrypted. Restore the original WordPress security salts, or re-enter your Client Secret and reconnect Google Analytics.', 'cliredas-analytics-dashboard'),
            'credential_backend_unavailable' => __('This host cannot decrypt the saved credentials. Enable the encryption extension used when they were saved, or re-enter your Client Secret and reconnect.', 'cliredas-analytics-dashboard'),
            'credential_encryption_failed' => __('Credentials could not be encrypted. Existing settings were preserved. Check the host encryption support and try again.', 'cliredas-analytics-dashboard'),
            'credential_save_failed' => __('The connection could not be saved. Existing settings were preserved. Try again.', 'cliredas-analytics-dashboard'),
        );
        return new WP_Error($code, isset($messages[$code]) ? $messages[$code] : $messages['credential_save_failed']);
    }

    /**
     * Get raw settings without exposing ciphertext to UI callers.
     *
     * @return array
     */
    public function get_raw()
    {
        $stored = get_option(self::OPTION_KEY, array());
        return is_array($stored) ? $stored : array();
    }

    /**
     * Decode settings in memory; unreadable fields are represented by empty strings.
     *
     * @return array
     */
    public function read()
    {
        $settings = $this->get_raw();
        foreach (CLIREDAS_Credential_Crypto::FIELDS as $field) {
            if (array_key_exists($field, $settings)) {
                $value = $this->crypto->decrypt($settings[$field], $field);
                $settings[$field] = is_wp_error($value) ? '' : $value;
            }
        }
        return $settings;
    }

    /**
     * Check whether specified saved fields are readable.
     *
     * @param array $fields Sensitive field names.
     * @return true|WP_Error
     */
    public function check_readable($fields = CLIREDAS_Credential_Crypto::FIELDS)
    {
        $raw = $this->get_raw();
        foreach ($fields as $field) {
            if (array_key_exists($field, $raw)) {
                $value = $this->crypto->decrypt($raw[$field], $field);
                if (is_wp_error($value)) {
                    return $value;
                }
            }
        }
        return true;
    }

    /**
     * Prepare an entire raw settings array for storage.
     *
     * @param array $settings Candidate settings.
     * @param array $old      Current raw settings.
     * @return array|WP_Error
     */
    public function prepare(array $settings, array $old)
    {
        unset($settings['cliredas_clear_ga4_client_secret']);
        foreach (CLIREDAS_Credential_Crypto::FIELDS as $field) {
            if (! array_key_exists($field, $settings)) {
                continue;
            }
            $value = $settings[$field];
            if (array_key_exists($field, $old) && $value === $old[$field]
                && (! is_string($value) || 0 === strpos($value, CLIREDAS_Credential_Crypto::PREFIX))) {
                continue;
            }
            if (! is_string($value)) {
                return self::error('credential_encryption_failed');
            }
            if (0 === strpos($value, CLIREDAS_Credential_Crypto::PREFIX)) {
                $value = $this->crypto->decrypt($value, $field);
                if (is_wp_error($value)) {
                    return $value;
                }
            }
            if (isset($old[$field])) {
                $previous = $this->crypto->decrypt($old[$field], $field);
                if (! is_wp_error($previous) && $previous === $value && 0 === strpos($old[$field], CLIREDAS_Credential_Crypto::PREFIX)) {
                    $settings[$field] = $old[$field];
                    continue;
                }
            }
            $encrypted = $this->crypto->encrypt($value, $field);
            if (is_wp_error($encrypted)) {
                return $encrypted;
            }
            $settings[$field] = $encrypted;
        }
        return $settings;
    }

    /**
     * Encrypt after Settings API sanitization and reject a failed write atomically.
     *
     * @param mixed $value Sanitized settings.
     * @return array
     */
    public function filter_storage($value)
    {
        $this->last_error = null;
        $old = $this->get_raw();
        $prepared = is_array($value) ? $this->prepare($value, $old) : self::error('credential_save_failed');
        if (is_wp_error($prepared)) {
            $this->last_error = $prepared;
            add_settings_error(self::OPTION_KEY, $prepared->get_error_code(), $prepared->get_error_message());
            return $old;
        }
        return $prepared;
    }

    /**
     * Save only supplied changes, with conflict retries and safe persistence errors.
     *
     * @param array $changes Fields to replace.
     * @return true|WP_Error
     */
    public function save(array $changes)
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $old = $this->get_raw();
            $new = $this->prepare(array_replace($old, $changes), $old);
            if (is_wp_error($new)) {
                return $new;
            }
            if ($old === $new) {
                return true;
            }
            if (false === get_option(self::OPTION_KEY, false)) {
                if (add_option(self::OPTION_KEY, $new, '', false)) {
                    return true;
                }
                CLIREDAS_Local_Store::invalidate(self::OPTION_KEY);
            } elseif (CLIREDAS_Local_Store::compare_and_swap(self::OPTION_KEY, $old, $new)) {
                return true;
            }
        }
        return self::error('credential_save_failed');
    }

    /**
     * Migrate legacy plaintext during a verified administrator request.
     *
     * @return void
     */
    public function maybe_migrate()
    {
        if (! current_user_can('manage_options') || '' === $this->crypto->get_backend()) {
            return;
        }
        $this->last_error = null;
        $old = $this->get_raw();
        $prepared = $this->prepare($old, $old);
        if (is_wp_error($prepared)) {
            $this->last_error = $prepared;
        } elseif ($prepared !== $old && ! CLIREDAS_Local_Store::compare_and_swap(self::OPTION_KEY, $old, $prepared)) {
            $this->last_error = self::error('credential_save_failed');
        }
    }

    /**
     * Get local storage state without any network requests.
     *
     * @return array
     */
    public function get_status()
    {
        $raw = $this->get_raw();
        $encrypted = 0;
        $plaintext = 0;
        foreach (CLIREDAS_Credential_Crypto::FIELDS as $field) {
            if (! empty($raw[$field])) {
                if (is_string($raw[$field]) && 0 === strpos($raw[$field], CLIREDAS_Credential_Crypto::PREFIX)) {
                    ++$encrypted;
                } else {
                    ++$plaintext;
                }
            }
        }
        $readable = $this->check_readable();
        return array(
            'backend' => $this->crypto->get_backend(),
            'encrypted' => $encrypted,
            'plaintext' => $plaintext,
            'has_refresh' => ! empty($raw['ga4_refresh_token']),
            'error' => is_wp_error($readable) ? $readable : $this->last_error,
        );
    }
}
