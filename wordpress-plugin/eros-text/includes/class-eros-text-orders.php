<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks WooCommerce order events and sends the matching SMS.
 *
 *   new order                 -> "placed"
 *   status -> processing      -> "processing"
 *   status -> {shipped_status} -> "shipped" (default: completed)
 *
 * Idempotency: a per-order meta flag per event prevents duplicate texts.
 */
class Eros_Text_Orders {

    public static function init() {
        add_action('woocommerce_new_order', [__CLASS__, 'on_new_order'], 20, 1);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_status_changed'], 20, 4);
    }

    public static function on_new_order($order_id) {
        if (!Eros_Text_Settings::get('event_placed')) {
            return;
        }
        $order = wc_get_order($order_id);
        if ($order) {
            self::maybe_send($order, 'placed');
        }
    }

    public static function on_status_changed($order_id, $from, $to, $order) {
        $shipped = Eros_Text_Settings::get('shipped_status', 'completed');

        if ($to === 'processing' && Eros_Text_Settings::get('event_processing')) {
            self::maybe_send($order, 'processing');
        } elseif ($to === $shipped && Eros_Text_Settings::get('event_shipped')) {
            self::maybe_send($order, 'shipped');
        }
    }

    protected static function template_for($event) {
        switch ($event) {
            case 'placed':
                return Eros_Text_Settings::get('tpl_placed');
            case 'processing':
                return Eros_Text_Settings::get('tpl_processing');
            case 'shipped':
                return Eros_Text_Settings::get('tpl_shipped');
        }
        return '';
    }

    public static function maybe_send($order, $event) {
        $meta_key = '_eros_text_sent_' . $event;

        // Already sent this event for this order? Don't repeat.
        if ($order->get_meta($meta_key, true)) {
            return;
        }

        $raw_phone = $order->get_billing_phone();
        $country   = $order->get_billing_country();
        $to = Eros_Text_Phone::normalize(
            $raw_phone,
            $country ? $country : Eros_Text_Settings::get('default_country', 'US')
        );

        if (!$to) {
            Eros_Text_Log::add([
                'order_id' => $order->get_id(),
                'event'    => $event,
                'to_phone' => $raw_phone,
                'status'   => 'failed',
                'error'    => 'No valid phone number on order.',
            ]);
            $order->add_order_note(sprintf('Eros Text: could not send "%s" SMS — no valid phone number.', $event));
            return;
        }

        $text = Eros_Text_Messages::render(self::template_for($event), $order);
        $result = Eros_Text_Telnyx::send($to, $text);

        if ($result['ok']) {
            // Mark sent only on success, so a failure can retry on a later change.
            $order->update_meta_data($meta_key, current_time('mysql'));
            $order->save();

            Eros_Text_Log::add([
                'order_id'    => $order->get_id(),
                'event'       => $event,
                'to_phone'    => $to,
                'status'      => 'sent',
                'provider_id' => $result['id'],
                'message'     => $text,
            ]);
            $order->add_order_note(sprintf('Eros Text: "%s" SMS sent to %s.', $event, $to));
        } else {
            Eros_Text_Log::add([
                'order_id' => $order->get_id(),
                'event'    => $event,
                'to_phone' => $to,
                'status'   => 'failed',
                'error'    => $result['error'],
                'message'  => $text,
            ]);
            $order->add_order_note(sprintf('Eros Text: "%s" SMS FAILED — %s', $event, $result['error']));
        }
    }
}
