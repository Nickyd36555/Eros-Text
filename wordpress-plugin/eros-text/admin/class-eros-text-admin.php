<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menu + pages: Settings, Send a Text (direct), Send Log.
 */
class Eros_Text_Admin {

    const CAP = 'manage_woocommerce';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu() {
        add_menu_page('Eros Text', 'Eros Text', self::CAP, 'eros-text', [$this, 'page_settings'], 'dashicons-email-alt', 56);
        add_submenu_page('eros-text', 'Eros Text Settings', 'Settings', self::CAP, 'eros-text', [$this, 'page_settings']);
        add_submenu_page('eros-text', 'Send a Text', 'Send a Text', self::CAP, 'eros-text-send', [$this, 'page_send']);
        add_submenu_page('eros-text', 'Send Log', 'Send Log', self::CAP, 'eros-text-log', [$this, 'page_log']);
    }

    private function guard() {
        if (!current_user_can(self::CAP)) {
            wp_die('You do not have permission to access this page.');
        }
    }

    public function page_settings() {
        $this->guard();
        $notice = '';

        if (isset($_POST['eros_text_settings_nonce'])
            && check_admin_referer('eros_text_save_settings', 'eros_text_settings_nonce')) {

            $in = wp_unslash($_POST);

            $provider = strtolower(sanitize_text_field($in['sms_provider'] ?? 'telnyx'));
            if (!in_array($provider, ['telnyx', 'plivo', 'infobip'], true)) {
                $provider = 'telnyx';
            }

            $values = [
                'store_name'                  => sanitize_text_field($in['store_name'] ?? 'Eros Labs'),
                'sms_provider'                => $provider,
                'telnyx_from'                 => sanitize_text_field($in['telnyx_from'] ?? ''),
                'telnyx_messaging_profile_id' => sanitize_text_field($in['telnyx_messaging_profile_id'] ?? ''),
                'plivo_auth_id'               => sanitize_text_field($in['plivo_auth_id'] ?? ''),
                'plivo_from'                  => sanitize_text_field($in['plivo_from'] ?? ''),
                'infobip_base_url'            => sanitize_text_field($in['infobip_base_url'] ?? ''),
                'infobip_from'                => sanitize_text_field($in['infobip_from'] ?? ''),
                'default_country'             => strtoupper(sanitize_text_field($in['default_country'] ?? 'US')),
                'shipped_status'              => sanitize_key($in['shipped_status'] ?? 'completed'),
                'event_placed'                => empty($in['event_placed']) ? 0 : 1,
                'event_processing'            => empty($in['event_processing']) ? 0 : 1,
                'event_shipped'               => empty($in['event_shipped']) ? 0 : 1,
                'tpl_placed'                  => sanitize_textarea_field($in['tpl_placed'] ?? ''),
                'tpl_processing'              => sanitize_textarea_field($in['tpl_processing'] ?? ''),
                'tpl_shipped'                 => sanitize_textarea_field($in['tpl_shipped'] ?? ''),
                'auto_update'                 => empty($in['auto_update']) ? 0 : 1,
            ];

            // Secrets: only overwrite if a new value was typed; blank keeps the old one.
            $typed_key = sanitize_text_field($in['telnyx_api_key'] ?? '');
            $values['telnyx_api_key'] = ($typed_key !== '')
                ? $typed_key
                : Eros_Text_Settings::get('telnyx_api_key', '');

            $typed_plivo = sanitize_text_field($in['plivo_auth_token'] ?? '');
            $values['plivo_auth_token'] = ($typed_plivo !== '')
                ? $typed_plivo
                : Eros_Text_Settings::get('plivo_auth_token', '');

            $typed_infobip = sanitize_text_field($in['infobip_api_key'] ?? '');
            $values['infobip_api_key'] = ($typed_infobip !== '')
                ? $typed_infobip
                : Eros_Text_Settings::get('infobip_api_key', '');

            Eros_Text_Settings::update($values);
            $notice = 'Settings saved.';
        }

        $s = Eros_Text_Settings::get_all();
        include EROS_TEXT_DIR . 'admin/views/settings.php';
    }

    public function page_send() {
        $this->guard();
        $result = null;
        $prefill_phone = '';
        $prefill_msg   = '';

        if (isset($_POST['eros_text_send_nonce'])
            && check_admin_referer('eros_text_send', 'eros_text_send_nonce')) {

            $phone   = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
            $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
            $prefill_phone = $phone;
            $prefill_msg   = $message;

            if ($phone !== '' && $message !== '') {
                $result = Eros_Text::send_direct($phone, $message);
                if ($result['ok']) {
                    // Clear the form on success.
                    $prefill_phone = '';
                    $prefill_msg   = '';
                }
            } else {
                $result = ['ok' => false, 'error' => 'Enter both a phone number and a message.', 'to' => $phone];
            }
        }

        include EROS_TEXT_DIR . 'admin/views/send.php';
    }

    public function page_log() {
        $this->guard();
        $rows = Eros_Text_Log::recent(200);
        include EROS_TEXT_DIR . 'admin/views/log.php';
    }
}
