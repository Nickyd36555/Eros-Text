<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes phone numbers to E.164 (+15555550123), which Telnyx requires.
 * Pragmatic and US/Canada-focused; other countries pass through if they already
 * include a country code. Returns null when it can't form a plausible number.
 */
class Eros_Text_Phone {

    public static function normalize($raw, $default_country = 'US') {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $result = self::do_normalize($raw, $default_country);

        // Allow site owners/developers to override the logic if needed.
        return apply_filters('eros_text_normalize_phone', $result, $raw, $default_country);
    }

    protected static function do_normalize($raw, $default_country) {
        // Already in +E.164 form.
        if (strpos($raw, '+') === 0) {
            $digits = preg_replace('/\D+/', '', $raw);
            if (strlen($digits) >= 8 && strlen($digits) <= 15) {
                return '+' . $digits;
            }
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        $country = strtoupper((string) $default_country);

        // North America.
        if (in_array($country, ['US', 'CA'], true)) {
            if (strlen($digits) === 10) {
                return '+1' . $digits;
            }
            if (strlen($digits) === 11 && $digits[0] === '1') {
                return '+' . $digits;
            }
        }

        // Looks like it already carries a country code.
        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        // Can't safely guess a country code for a bare local number.
        return null;
    }
}
