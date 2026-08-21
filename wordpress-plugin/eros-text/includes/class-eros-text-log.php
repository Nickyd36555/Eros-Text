<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records every SMS attempt in a custom table, for auditing in wp-admin.
 */
class Eros_Text_Log {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'eros_text_log';
    }

    public static function install() {
        global $wpdb;
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            event VARCHAR(32) NOT NULL DEFAULT '',
            to_phone VARCHAR(32) NOT NULL DEFAULT '',
            status VARCHAR(16) NOT NULL DEFAULT '',
            provider_id VARCHAR(128) NOT NULL DEFAULT '',
            error TEXT NULL,
            message TEXT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function add($args) {
        global $wpdb;
        $a = wp_parse_args($args, [
            'order_id'    => null,
            'event'       => '',
            'to_phone'    => '',
            'status'      => '',
            'provider_id' => '',
            'error'       => '',
            'message'     => '',
        ]);

        $wpdb->insert(self::table(), [
            'created_at'  => current_time('mysql'),
            'order_id'    => $a['order_id'] ? absint($a['order_id']) : null,
            'event'       => substr((string) $a['event'], 0, 32),
            'to_phone'    => substr((string) $a['to_phone'], 0, 32),
            'status'      => substr((string) $a['status'], 0, 16),
            'provider_id' => substr((string) $a['provider_id'], 0, 128),
            'error'       => (string) $a['error'],
            'message'     => (string) $a['message'],
        ]);
    }

    public static function recent($limit = 100) {
        global $wpdb;
        $table = self::table();
        $limit = absint($limit);
        // Table name is internal (not user input); limit is cast to int.
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}");
    }
}
