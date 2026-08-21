=== Eros Text ===
Contributors: eroslabs
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Eros Labs order notifications by SMS (Telnyx): placed, processing, and shipped with tracking. Editable messages, direct texting, per-event toggles, and a send log.

== Description ==

Eros Text sends your WooCommerce customers a text message when their order is:

* **placed** — order received
* **processing** — being prepped for shipment
* **shipped** — includes the tracking number and a tracking link

Every message is branded as your store only and never mentions the product category.

Features:

* Editable message templates with placeholders ({first_name}, {order_number}, {tracking_number}, {tracking_line}, …)
* Direct texting — send a one-off SMS to any number from wp-admin
* Turn each event (placed / processing / shipped) on or off
* Send log with per-order notes
* Reads tracking numbers from WooCommerce Shipment Tracking / Advanced Shipment Tracking
* Auto-updates from GitHub releases

== Installation ==

1. Upload the plugin zip in Plugins → Add New → Upload Plugin, then Activate.
2. Go to Eros Text → Settings and enter your Telnyx API key and From number.
3. Choose which events to send and edit the message templates if you like.
4. (Recommended) Put your API key in wp-config.php instead of the database:
   `define('EROS_TEXT_TELNYX_API_KEY', 'KEY...');`

Requires WooCommerce.

== Changelog ==

= 1.0.0 =
* Initial release: placed/processing/shipped SMS via Telnyx, editable templates, direct texting, send log, per-event toggles, GitHub auto-update.
