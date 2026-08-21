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
        switch (Eros_Text_Settings::provider()) {
            case 'plivo':
                return Eros_Text_Plivo::send($to, $text);
            case 'infobip':
                return Eros_Text_Infobip::send($to, $text);
            default:
                return Eros_Text_Telnyx::send($to, $text);
        }
    }
}
