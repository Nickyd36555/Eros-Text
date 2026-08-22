<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stores and retrieves plugin settings (one wp_option holding an array).
 */
class Eros_Text_Settings {

    public static function defaults() {
        return [
            'store_name'                  => 'Eros Labs',

            // Which provider actually sends: 'telnyx' or 'plivo'.
            'sms_provider'                => 'telnyx',

            // Telnyx
            'telnyx_api_key'              => '',
            'telnyx_from'                 => '',
            'telnyx_messaging_profile_id' => '',

            // Plivo
            'plivo_auth_id'               => '',
            'plivo_auth_token'            => '',
            'plivo_from'                  => '',

            // Infobip
            'infobip_base_url'            => '',
            'infobip_api_key'             => '',
            'infobip_from'                => '',

            'default_country'             => 'US',
            'shipped_status'              => 'completed',

            // Per-event on/off
            'event_placed'                => 1,
            'event_processing'            => 1,
            'event_shipped'               => 1,

            // Editable message templates (store-branded only — no product category)
            'tpl_placed'                  => "{store_name}: thanks for your order #{order_number}! We've got it and will text you when it ships. Reply STOP to opt out.",
            'tpl_processing'              => "{store_name}: your order #{order_number} is now being processed and prepped for shipment.",
            'tpl_shipped'                 => "{store_name}: your order #{order_number} has shipped! {tracking_line}",

            // Canned "quick messages" for the Send a Text screen. One per line,
            // in the form: Label | message text
            'quick_messages'              => "Account blocked | Eros Labs: your account has been blocked. Questions? Email support@[yourdomain]. Reply STOP to opt out.\nAccount unblocked | Eros Labs: your account has been unblocked — you're all set. Reply STOP to opt out.\nReported to Pepban | Pepban: your account has been reported and is under review. Questions? Email support@[yourdomain].",

            // Auto-update this plugin from GitHub releases
            'auto_update'                 => 1,
        ];
    }

    public static function get_all() {
        $saved = get_option(EROS_TEXT_OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return wp_parse_args($saved, self::defaults());
    }

    public static function get($key, $default = null) {
        $all = self::get_all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * The Telnyx API key. A wp-config.php constant overrides the stored value,
     * which is the recommended way to keep the secret out of the database.
     */
    public static function api_key() {
        if (defined('EROS_TEXT_TELNYX_API_KEY') && EROS_TEXT_TELNYX_API_KEY) {
            return (string) EROS_TEXT_TELNYX_API_KEY;
        }
        return (string) self::get('telnyx_api_key', '');
    }

    public static function api_key_from_constant() {
        return defined('EROS_TEXT_TELNYX_API_KEY') && EROS_TEXT_TELNYX_API_KEY;
    }

    /** The Plivo Auth Token. A wp-config.php constant overrides the stored value. */
    public static function plivo_auth_token() {
        if (defined('EROS_TEXT_PLIVO_AUTH_TOKEN') && EROS_TEXT_PLIVO_AUTH_TOKEN) {
            return (string) EROS_TEXT_PLIVO_AUTH_TOKEN;
        }
        return (string) self::get('plivo_auth_token', '');
    }

    public static function plivo_token_from_constant() {
        return defined('EROS_TEXT_PLIVO_AUTH_TOKEN') && EROS_TEXT_PLIVO_AUTH_TOKEN;
    }

    /** The Infobip API key. A wp-config.php constant overrides the stored value. */
    public static function infobip_api_key() {
        if (defined('EROS_TEXT_INFOBIP_API_KEY') && EROS_TEXT_INFOBIP_API_KEY) {
            return (string) EROS_TEXT_INFOBIP_API_KEY;
        }
        return (string) self::get('infobip_api_key', '');
    }

    public static function infobip_key_from_constant() {
        return defined('EROS_TEXT_INFOBIP_API_KEY') && EROS_TEXT_INFOBIP_API_KEY;
    }

    /** Parse the quick-messages setting into [ ['label'=>..,'body'=>..], ... ]. */
    public static function quick_messages() {
        $raw = (string) self::get('quick_messages', '');
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            $label = trim($parts[0]);
            $body  = isset($parts[1]) ? trim($parts[1]) : '';
            if ($label !== '' && $body !== '') {
                $out[] = ['label' => $label, 'body' => $body];
            }
        }
        return $out;
    }

    public static function provider() {
        $p = strtolower((string) self::get('sms_provider', 'telnyx'));
        return in_array($p, ['telnyx', 'plivo', 'infobip'], true) ? $p : 'telnyx';
    }

    public static function update(array $values) {
        update_option(EROS_TEXT_OPTION, $values);
    }

    public static function install_defaults() {
        if (get_option(EROS_TEXT_OPTION, null) === null) {
            update_option(EROS_TEXT_OPTION, self::defaults());
        }
    }
}
