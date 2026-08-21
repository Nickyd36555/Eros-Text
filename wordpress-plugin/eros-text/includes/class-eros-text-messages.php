<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders message templates by substituting {placeholders}.
 */
class Eros_Text_Messages {

    /** Placeholders shown in the admin help text. */
    public static function available_placeholders() {
        return [
            '{store_name}',
            '{first_name}',
            '{last_name}',
            '{order_number}',
            '{order_total}',
            '{tracking_number}',
            '{tracking_provider}',
            '{tracking_url}',
            '{tracking_line}',
        ];
    }

    public static function placeholders($order) {
        $store = Eros_Text_Settings::get('store_name', 'Eros Labs');

        $first = $order ? $order->get_billing_first_name() : '';
        $last  = $order ? $order->get_billing_last_name() : '';
        $num   = $order ? $order->get_order_number() : '';

        $total = '';
        if ($order) {
            $total = html_entity_decode(
                wp_strip_all_tags(wc_price($order->get_total(), ['currency' => $order->get_currency()])),
                ENT_QUOTES
            );
        }

        $tracking = $order
            ? Eros_Text_Tracking::extract($order)
            : ['number' => '', 'provider' => '', 'url' => ''];

        return [
            '{store_name}'        => $store,
            '{first_name}'        => $first,
            '{last_name}'         => $last,
            '{order_number}'      => $num,
            '{order_total}'       => $total,
            '{tracking_number}'   => $tracking['number'],
            '{tracking_provider}' => $tracking['provider'],
            '{tracking_url}'      => $tracking['url'],
            '{tracking_line}'     => self::tracking_line($tracking),
        ];
    }

    /** Composes the "USPS tracking: 123. Track it: url" line, or a fallback. */
    public static function tracking_line($tracking) {
        if (!empty($tracking['number'])) {
            $carrier = !empty($tracking['provider']) ? $tracking['provider'] . ' ' : '';
            $line = trim($carrier . 'tracking: ' . $tracking['number'] . '.');
            if (!empty($tracking['url'])) {
                $line .= ' Track it: ' . $tracking['url'];
            }
            return $line;
        }
        return 'It is on its way!';
    }

    public static function render($template, $order) {
        $map  = self::placeholders($order);
        $text = strtr((string) $template, $map);
        // Tidy any double spaces left by empty placeholders.
        $text = trim(preg_replace('/[ \t]{2,}/', ' ', $text));
        return $text;
    }
}
