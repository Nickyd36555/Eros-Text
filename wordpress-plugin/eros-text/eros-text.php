<?php
/**
 * Plugin Name: Eros Text
 * Plugin URI:  https://github.com/Nickyd36555/Eros-Text
 * Description: Sends Eros Labs order notifications (placed, processing, shipped with tracking) by SMS via Telnyx. Editable messages, direct texting, per-event toggles, and a send log. Auto-updates from GitHub.
 * Version:     1.1.0
 * Author:      Eros Labs
 * License:     GPL-2.0-or-later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.9
 * Text Domain: eros-text
 *
 * IMPORTANT: every customer-facing message is branded as the store only
 * (Eros Labs). Default copy must never describe or hint at the product category.
 */

if (!defined('ABSPATH')) {
    exit; // No direct access.
}

define('EROS_TEXT_VERSION', '1.1.0');
define('EROS_TEXT_FILE', __FILE__);
define('EROS_TEXT_DIR', plugin_dir_path(__FILE__));
define('EROS_TEXT_URL', plugin_dir_url(__FILE__));
define('EROS_TEXT_OPTION', 'eros_text_settings');

// GitHub repo the updater watches for new releases.
define('EROS_TEXT_GH_OWNER', 'Nickyd36555');
define('EROS_TEXT_GH_REPO', 'Eros-Text');

require_once EROS_TEXT_DIR . 'includes/class-eros-text-settings.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-phone.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-telnyx.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-plivo.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-sender.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-tracking.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-messages.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-log.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-orders.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text-updater.php';
require_once EROS_TEXT_DIR . 'includes/class-eros-text.php';

if (is_admin()) {
    require_once EROS_TEXT_DIR . 'admin/class-eros-text-admin.php';
}

// Declare HPOS (High-Performance Order Storage) compatibility.
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', EROS_TEXT_FILE, true);
    }
});

register_activation_hook(__FILE__, ['Eros_Text_Log', 'install']);
register_activation_hook(__FILE__, ['Eros_Text_Settings', 'install_defaults']);

// Boot after WooCommerce has loaded.
add_action('plugins_loaded', function () {
    Eros_Text::instance();
}, 20);

// The updater must run in admin and during cron (that's when WP checks for updates).
add_action('init', function () {
    new Eros_Text_Updater(
        plugin_basename(EROS_TEXT_FILE),
        'eros-text',
        EROS_TEXT_VERSION,
        EROS_TEXT_GH_OWNER,
        EROS_TEXT_GH_REPO
    );
});
