<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main bootstrap: wires up order hooks and the admin UI, and exposes a helper
 * used by the "Send a Text" (direct texting) screen.
 */
class Eros_Text {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        Eros_Text_Orders::init();

        if (is_admin()) {
            Eros_Text_Admin::instance();
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>Eros Text</strong> needs WooCommerce to be installed and active.</p></div>';
    }

    /**
     * Send an ad-hoc SMS (used by the direct-texting screen).
     *
     * @return array{ok:bool,id:string,error:string,to:string}
     */
    public static function send_direct($raw_phone, $text) {
        $to = Eros_Text_Phone::normalize($raw_phone, Eros_Text_Settings::get('default_country', 'US'));
        if (!$to) {
            return ['ok' => false, 'id' => '', 'error' => 'That does not look like a valid phone number.', 'to' => $raw_phone];
        }

        $result = Eros_Text_Telnyx::send($to, $text);
        $result['to'] = $to;

        Eros_Text_Log::add([
            'order_id'    => null,
            'event'       => 'direct',
            'to_phone'    => $to,
            'status'      => $result['ok'] ? 'sent' : 'failed',
            'provider_id' => $result['id'],
            'error'       => $result['ok'] ? '' : $result['error'],
            'message'     => $text,
        ]);

        return $result;
    }
}
