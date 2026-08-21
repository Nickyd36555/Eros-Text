<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends SMS through the Infobip API using WordPress' HTTP client.
 * https://www.infobip.com/docs/api/channels/sms
 */
class Eros_Text_Infobip {

    /**
     * @return array{ok:bool,id:string,error:string}
     */
    public static function send($to, $text) {
        $base    = (string) Eros_Text_Settings::get('infobip_base_url', '');
        $api_key = Eros_Text_Settings::infobip_api_key();
        $from    = (string) Eros_Text_Settings::get('infobip_from', '');

        if (!$base || !$api_key) {
            return ['ok' => false, 'id' => '', 'error' => 'Infobip Base URL / API key is not set.'];
        }
        if (!$from) {
            return ['ok' => false, 'id' => '', 'error' => 'Set an Infobip "From" (sender) in Settings.'];
        }

        $endpoint = self::normalize_base($base) . '/sms/2/text/advanced';

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'App ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode([
                'messages' => [
                    [
                        'from'         => $from,
                        'destinations' => [['to' => ltrim($to, '+')]],
                        'text'         => $text,
                    ],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'id' => '', 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code >= 200 && $code < 300 && isset($data['messages'][0])) {
            $msg   = $data['messages'][0];
            $id    = isset($msg['messageId']) ? (string) $msg['messageId'] : '';
            $group = isset($msg['status']['groupName']) ? strtoupper($msg['status']['groupName']) : '';

            if (in_array($group, ['REJECTED', 'UNDELIVERABLE'], true)) {
                $why = isset($msg['status']['description']) ? $msg['status']['description'] : $group;
                return ['ok' => false, 'id' => $id, 'error' => 'Infobip rejected: ' . $why];
            }
            return ['ok' => true, 'id' => $id, 'error' => ''];
        }

        $error = 'HTTP ' . $code;
        if (isset($data['requestError']['serviceException']['text'])) {
            $error .= ': ' . $data['requestError']['serviceException']['text'];
        } elseif ($raw) {
            $error .= ': ' . wp_strip_all_tags(substr($raw, 0, 300));
        }
        return ['ok' => false, 'id' => '', 'error' => $error];
    }

    /** Accepts "xxxxx.api.infobip.com" or a full URL; returns "https://host" with no trailing slash. */
    private static function normalize_base($base) {
        $base = trim($base);
        if (!preg_match('#^https?://#i', $base)) {
            $base = 'https://' . $base;
        }
        return untrailingslashit($base);
    }
}
