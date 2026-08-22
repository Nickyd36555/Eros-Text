<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array|null $result  @var string $prefill_phone  @var string $prefill_msg */
?>
<div class="wrap">
    <h1>Eros Text — Send a Text</h1>
    <p>Send a one-off SMS to any number. It goes out through your Telnyx number and is recorded in the Send Log.</p>

    <?php if (is_array($result)) : ?>
        <?php if (!empty($result['ok'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Sent to <strong><?php echo esc_html($result['to']); ?></strong>.<?php echo !empty($result['id']) ? ' Message ID: <code>' . esc_html($result['id']) . '</code>' : ''; ?></p>
            </div>
        <?php else : ?>
            <div class="notice notice-error is-dismissible">
                <p>Could not send<?php echo !empty($result['to']) ? ' to ' . esc_html($result['to']) : ''; ?>: <?php echo esc_html($result['error']); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('eros_text_send', 'eros_text_send_nonce'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="phone">Phone number</label></th>
                <td>
                    <input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($prefill_phone); ?>" placeholder="(857) 770-0798 or +18577700798" required>
                    <p class="description">A US number can be typed with or without the +1; other countries need the full +country code.</p>
                </td>
            </tr>
            <?php $quick = Eros_Text_Settings::quick_messages(); ?>
            <?php if (!empty($quick)) : ?>
            <tr>
                <th scope="row"><label for="quick_pick">Quick message</label></th>
                <td>
                    <select id="quick_pick">
                        <option value="">— insert a saved message —</option>
                        <?php foreach ($quick as $i => $q) : ?>
                            <option value="<?php echo esc_attr($q['body']); ?>"><?php echo esc_html($q['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Picking one fills the message box below (you can still edit it before sending).</p>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th scope="row"><label for="message">Message</label></th>
                <td>
                    <textarea name="message" id="message" rows="4" class="large-text" maxlength="1000" required placeholder="Type your message…"><?php echo esc_textarea($prefill_msg); ?></textarea>
                    <p class="description">Keep it under ~160 characters to fit a single SMS segment (longer messages still send, split into segments).</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Send text'); ?>
    </form>
</div>

<script>
(function () {
    var pick = document.getElementById('quick_pick');
    var msg = document.getElementById('message');
    if (!pick || !msg) { return; }
    pick.addEventListener('change', function () {
        if (pick.value) { msg.value = pick.value; msg.focus(); }
    });
})();
</script>
