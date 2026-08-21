<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array $rows */
?>
<div class="wrap">
    <h1>Eros Text — Send Log</h1>
    <p>The 200 most recent SMS attempts.</p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>When</th>
                <th>Event</th>
                <th>Order</th>
                <th>To</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)) : ?>
            <tr><td colspan="6">No messages sent yet.</td></tr>
        <?php else : ?>
            <?php foreach ($rows as $row) : ?>
                <?php
                $is_sent = ($row->status === 'sent');
                $order_link = '';
                if (!empty($row->order_id)) {
                    $url = admin_url('post.php?post=' . absint($row->order_id) . '&action=edit');
                    // HPOS order edit URL fallback.
                    if (function_exists('wc_get_container') && class_exists('\Automattic\WooCommerce\Internal\Admin\Orders\PageController')) {
                        $url = admin_url('admin.php?page=wc-orders&action=edit&id=' . absint($row->order_id));
                    }
                    $order_link = '<a href="' . esc_url($url) . '">#' . esc_html($row->order_id) . '</a>';
                }
                $detail = $is_sent ? $row->message : $row->error;
                ?>
                <tr>
                    <td><?php echo esc_html(mysql2date('M j, Y g:i a', $row->created_at)); ?></td>
                    <td><?php echo esc_html($row->event); ?></td>
                    <td><?php echo $order_link ? wp_kses_post($order_link) : '—'; ?></td>
                    <td><?php echo esc_html($row->to_phone); ?></td>
                    <td>
                        <?php if ($is_sent) : ?>
                            <span style="color:#1a7f37;font-weight:600;">sent</span>
                        <?php else : ?>
                            <span style="color:#b32d2e;font-weight:600;"><?php echo esc_html($row->status ?: 'failed'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($detail); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
