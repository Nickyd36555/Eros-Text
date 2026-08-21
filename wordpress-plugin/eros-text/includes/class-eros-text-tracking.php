<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extracts tracking info from an order. Supports the common shipping plugins
 * (WooCommerce Shipment Tracking / Advanced Shipment Tracking) and a scalar
 * meta fallback. Returns ['number','provider','url'] with empty strings if none.
 */
class Eros_Text_Tracking {

    public static function extract($order) {
        $items = $order->get_meta('_wc_shipment_tracking_items', true);
        if (is_array($items) && !empty($items)) {
            $item = end($items);

            $provider = '';
            if (!empty($item['formatted_tracking_provider'])) {
                $provider = $item['formatted_tracking_provider'];
            } elseif (!empty($item['custom_tracking_provider'])) {
                $provider = $item['custom_tracking_provider'];
            } elseif (!empty($item['tracking_provider'])) {
                $provider = $item['tracking_provider'];
            }

            $number = !empty($item['tracking_number']) ? $item['tracking_number'] : '';

            $url = '';
            if (!empty($item['formatted_tracking_link'])) {
                $url = $item['formatted_tracking_link'];
            } elseif (!empty($item['custom_tracking_link'])) {
                $url = $item['custom_tracking_link'];
            } else {
                $url = self::carrier_url($provider, $number);
            }

            return ['number' => $number, 'provider' => $provider, 'url' => $url];
        }

        $number   = $order->get_meta('_tracking_number', true);
        $provider = $order->get_meta('_tracking_provider', true);
        if ($number) {
            return [
                'number'   => $number,
                'provider' => $provider,
                'url'      => self::carrier_url($provider, $number),
            ];
        }

        return ['number' => '', 'provider' => '', 'url' => ''];
    }

    public static function carrier_url($provider, $number) {
        if (!$number) {
            return '';
        }
        $p = strtolower((string) $provider);
        $n = rawurlencode($number);

        if (strpos($p, 'usps') !== false) {
            return "https://tools.usps.com/go/TrackConfirmAction?tLabels={$n}";
        }
        if (strpos($p, 'ups') !== false) {
            return "https://www.ups.com/track?tracknum={$n}";
        }
        if (strpos($p, 'fedex') !== false) {
            return "https://www.fedex.com/fedextrack/?trknbr={$n}";
        }
        if (strpos($p, 'dhl') !== false) {
            return "https://www.dhl.com/us-en/home/tracking.html?tracking-id={$n}";
        }
        return '';
    }
}
