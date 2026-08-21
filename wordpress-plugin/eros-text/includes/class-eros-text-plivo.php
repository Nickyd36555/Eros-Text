<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends SMS through the Plivo Messages API using WordPress' HTTP client.
 * https://www.plivo.com/docs/sms/api/message
 */
class Eros_Text_Plivo {

    /**
     * @return array{ok:bool,id:string,error:string}
     */
    public static function send($to, $text) {
        $auth_id    = (string) Eros_Text_Settings::get('plivo_auth_id', '');
        $auth_token = Eros_Text_Settings::plivo_auth_token();
        $from       = (string) Eros_Text_Settings::get('plivo_from', '');

        if (!$auth_id || !$auth_token) {
            return ['ok' => false, 'id' => '', 'error' => 'Plivo Auth ID / Auth Token is not set.'];
        }
        if (!$from) {
            return ['ok' => false, 'id' => '', 'error' => 'Set a Plivo "From" number in Settings.'];
        }

        // Plivo expects numbers without the leading "+".
        $src = ltrim($from, '+');
        $dst = ltrim($to, '+');

        $endpoint = 'https://api.plivo.com/v1/Account/' . rawurlencode($auth_id) . '/Message/';

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($auth_id . ':' . $auth_token),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode([
                'src'  => $src,
                'dst'  => $dst,
                'text' => $text,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'id' => '', 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        // Plivo returns 202 Accepted on success.
        if ($code === 202 || ($code >= 200 && $code < 300)) {
            $id = '';
            if (isset($data['message_uuid'][0])) {
                $id = (string) $data['message_uuid'][0];
            }
            return ['ok' => true, 'id' => $id, 'error' => ''];
        }

        $error = 'HTTP ' . $code;
        if (isset($data['error'])) {
            $error .= ': ' . (is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']));
        } elseif ($raw) {
            $error .= ': ' . wp_strip_all_tags(substr($raw, 0, 300));
        }
        return ['ok' => false, 'id' => '', 'error' => $error];
    }
}
