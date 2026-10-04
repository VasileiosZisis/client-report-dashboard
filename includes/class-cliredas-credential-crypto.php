<?php
/**
 * Authenticated OAuth credential encryption.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Encrypt credentials with native, authenticated encryption APIs.
 */
final class CLIREDAS_Credential_Crypto
{
    const PREFIX = 'cliredas_enc:';
    const FIELDS = array('ga4_client_secret', 'ga4_refresh_token', 'ga4_access_token');

    /**
     * Explicit backend for isolated tests.
     *
     * @var string|null
     */
    private $backend;
    /**
     * Explicit secret material for isolated tests.
     *
     * @var string|null
     */
    private $material;
    /**
     * Explicit site context for isolated tests.
     *
     * @var int|null
     */
    private $site_id;

    /**
     * Configure encryption, defaulting to the current WordPress context.
     *
     * @param string|null $backend  Optional backend override.
     * @param string|null $material Optional secret material override.
     * @param int|null    $site_id  Optional site context override.
     */
    public function __construct($backend = null, $material = null, $site_id = null)
    {
        $this->backend = $backend;
        $this->material = $material;
        $this->site_id = $site_id;
    }

    /**
     * Return the preferred available native backend.
     *
     * @return string
     */
    public function get_backend()
    {
        if (null !== $this->backend) {
            return $this->backend;
        }
        if ($this->supports('sodium')) {
            return 'sodium';
        }
        return $this->supports('aes-256-gcm') ? 'aes-256-gcm' : '';
    }

    /**
     * Check support for the backend recorded in a ciphertext envelope.
     *
     * @param string $backend Backend name.
     * @return bool
     */
    private function supports($backend)
    {
        if ('' === $this->backend) {
            return false;
        }
        if ('sodium' === $backend) {
            return extension_loaded('sodium') && function_exists('sodium_crypto_secretbox') && function_exists('sodium_crypto_secretbox_open');
        }
        return 'aes-256-gcm' === $backend
            && function_exists('openssl_encrypt') && function_exists('openssl_decrypt')
            && function_exists('openssl_get_cipher_methods')
            && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
    }

    /**
     * Derive a key bound to one field, algorithm, and WordPress site.
     *
     * @param string $field   Sensitive field name.
     * @param string $backend Backend name.
     * @return string
     */
    private function key($field, $backend)
    {
        $material = null !== $this->material ? $this->material : wp_salt('auth') . '\0' . wp_salt('secure_auth');
        $site_id = null !== $this->site_id ? $this->site_id : get_current_blog_id();
        return hash_hkdf('sha256', $material, 32, 'cliredas:v1:' . $site_id . ':' . $field . ':' . $backend);
    }

    /**
     * Encrypt a plaintext credential and verify it before storage.
     *
     * @param string $value Plaintext credential.
     * @param string $field Sensitive field name.
     * @return string|WP_Error
     */
    public function encrypt($value, $field)
    {
        // phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode binary ciphertext, never executable code.
        if (! in_array($field, self::FIELDS, true) || ! is_string($value)) {
            return CLIREDAS_Credential_Store::error('credential_encryption_failed');
        }
        if ('' === $value) {
            return '';
        }
        $backend = $this->get_backend();
        if ('' === $backend) {
            return $value;
        }
        if (! $this->supports($backend)) {
            return CLIREDAS_Credential_Store::error('credential_backend_unavailable');
        }
        try {
            $nonce = random_bytes('sodium' === $backend ? 24 : 12);
            $tag = '';
            if ('sodium' === $backend) {
                $ciphertext = sodium_crypto_secretbox($value, $nonce, $this->key($field, $backend));
            } else {
                $ciphertext = openssl_encrypt($value, $backend, $this->key($field, $backend), OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
            }
            if (false === $ciphertext || ('aes-256-gcm' === $backend && 16 !== strlen($tag))) {
                return CLIREDAS_Credential_Store::error('credential_encryption_failed');
            }
            $envelope = self::PREFIX . 'v1:' . base64_encode(wp_json_encode(array(
                'algorithm' => $backend,
                'nonce' => base64_encode($nonce),
                'tag' => base64_encode($tag),
                'ciphertext' => base64_encode($ciphertext),
            )));
            $verified = $this->decrypt($envelope, $field);
            if (is_wp_error($verified) || ! hash_equals($value, $verified)) {
                return CLIREDAS_Credential_Store::error('credential_encryption_failed');
            }
            return $envelope;
        } catch (Throwable $exception) {
            return CLIREDAS_Credential_Store::error('credential_encryption_failed');
        }
        // phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    /**
     * Decrypt an envelope or return an unchanged legacy plaintext value.
     *
     * @param string $value Stored value.
     * @param string $field Sensitive field name.
     * @return string|WP_Error
     */
    public function decrypt($value, $field)
    {
        // phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Validate binary ciphertext encoding, never executable code.
        if (! is_string($value) || ! in_array($field, self::FIELDS, true)) {
            return CLIREDAS_Credential_Store::error('credential_decryption_failed');
        }
        if (0 !== strpos($value, self::PREFIX)) {
            return $value;
        }
        if (0 !== strpos($value, self::PREFIX . 'v1:') || strlen($value) > 65536) {
            return CLIREDAS_Credential_Store::error('credential_decryption_failed');
        }
        $encoded = substr($value, strlen(self::PREFIX . 'v1:'));
        $json = base64_decode($encoded, true);
        $data = false !== $json && base64_encode($json) === $encoded ? json_decode($json, true) : null;
        if (! is_array($data) || 4 !== count($data)) {
            return CLIREDAS_Credential_Store::error('credential_decryption_failed');
        }
        foreach (array('algorithm', 'nonce', 'tag', 'ciphertext') as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key])) {
                return CLIREDAS_Credential_Store::error('credential_decryption_failed');
            }
        }
        $backend = $data['algorithm'];
        if (! in_array($backend, array('sodium', 'aes-256-gcm'), true)) {
            return CLIREDAS_Credential_Store::error('credential_decryption_failed');
        }
        foreach (array('nonce', 'tag', 'ciphertext') as $key) {
            $decoded = base64_decode($data[$key], true);
            if (false === $decoded || base64_encode($decoded) !== $data[$key]) {
                return CLIREDAS_Credential_Store::error('credential_decryption_failed');
            }
            $data[$key] = $decoded;
        }
        if (
            ('sodium' === $backend && (24 !== strlen($data['nonce']) || '' !== $data['tag'] || strlen($data['ciphertext']) <= 16))
            || ('aes-256-gcm' === $backend && (12 !== strlen($data['nonce']) || 16 !== strlen($data['tag']) || '' === $data['ciphertext']))
        ) {
            return CLIREDAS_Credential_Store::error('credential_decryption_failed');
        }
        if (! $this->supports($backend)) {
            return CLIREDAS_Credential_Store::error('credential_backend_unavailable');
        }
        try {
            $plaintext = 'sodium' === $backend
                ? sodium_crypto_secretbox_open($data['ciphertext'], $data['nonce'], $this->key($field, $backend))
                : openssl_decrypt($data['ciphertext'], $backend, $this->key($field, $backend), OPENSSL_RAW_DATA, $data['nonce'], $data['tag']);
            return false === $plaintext ? CLIREDAS_Credential_Store::error('credential_decryption_failed') : $plaintext;
        } catch (Throwable $exception) {
            return CLIREDAS_Credential_Store::error('credential_decryption_failed');
        }
        // phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
    }
}
