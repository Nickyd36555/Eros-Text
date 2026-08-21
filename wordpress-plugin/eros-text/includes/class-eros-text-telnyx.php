<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends SMS through the Telnyx Messages API using WordPress' HTTP client.
 */
class Eros_Text_Telnyx {

    /**
     * @return array{ok:bool,id:string,error:string}
     */
    public static function send($to, $text) {
        $api_key = Eros_Text_Settings::api_key();
        $from    = Eros_Text_Settings::get('telnyx_from', '');
        $profile = Eros_Text_Settings::get('telnyx_messaging_profile_id', '');

        if (!$api_key) {
            return ['ok' => false, 'id' => '', 'error' => 'Telnyx API key is not set.'];
        }
        if (!$from && !$profile) {
            return ['ok' => false, 'id' => '', 'error' => 'Set a Telnyx "From" number or Messaging Profile ID in Settings.'];
        }

        $body = ['to' => $to, 'text' => $text];
        if ($profile) {
            $body['messaging_profile_id'] = $profile;
        }
        if ($from) {
            $body['from'] = $from;
        }

        $response = wp_remote_post('https://api.telnyx.com/v2/messages', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'id' => '', 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code >= 200 && $code < 300) {
            $id = isset($data['data']['id']) ? (string) $data['data']['id'] : '';
            return ['ok' => true, 'id' => $id, 'error' => ''];
        }

        $error = 'HTTP ' . $code;
        if (isset($data['errors'][0]['detail'])) {
            $error .= ': ' . $data['errors'][0]['detail'];
        } elseif ($raw) {
            $error .= ': ' . wp_strip_all_tags(substr($raw, 0, 300));
        }
        return ['ok' => false, 'id' => '', 'error' => $error];
    }
}
