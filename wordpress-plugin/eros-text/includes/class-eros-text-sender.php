<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Routes an outgoing SMS to the configured provider. Every provider exposes the
 * same shape: send($to, $text) -> ['ok'=>bool,'id'=>string,'error'=>string].
 */
class Eros_Text_Sender {

    public static function send($to, $text) {
        if (Eros_Text_Settings::provider() === 'plivo') {
            return Eros_Text_Plivo::send($to, $text);
        }
        return Eros_Text_Telnyx::send($to, $text);
    }
}
