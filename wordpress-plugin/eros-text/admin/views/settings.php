<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array $s Current settings. @var string $notice */
$key_set = (Eros_Text_Settings::api_key() !== '');
$key_const = Eros_Text_Settings::api_key_from_constant();
$plivo_token_set = (Eros_Text_Settings::plivo_auth_token() !== '');
$plivo_token_const = Eros_Text_Settings::plivo_token_from_constant();
$placeholders = implode(' ', Eros_Text_Messages::available_placeholders());
?>
<div class="wrap">
    <h1>Eros Text — Settings</h1>

    <?php if ($notice) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <p style="max-width:720px;">
        Sends order notifications by SMS via Telnyx. All messages are branded as your store
        only — please keep the copy generic (nothing about the product category).
    </p>

    <form method="post" action="">
        <?php wp_nonce_field('eros_text_save_settings', 'eros_text_settings_nonce'); ?>

        <h2 class="title">SMS provider</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="sms_provider">Provider</label></th>
                <td>
                    <select name="sms_provider" id="sms_provider">
                        <option value="telnyx" <?php selected($s['sms_provider'], 'telnyx'); ?>>Telnyx</option>
                        <option value="plivo" <?php selected($s['sms_provider'], 'plivo'); ?>>Plivo</option>
                    </select>
                    <p class="description">Only the selected provider's credentials below are used.</p>
                </td>
            </tr>
        </table>

        <div class="eros-provider eros-provider-telnyx">
        <h2 class="title">Telnyx</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="telnyx_api_key">API key</label></th>
                <td>
                    <?php if ($key_const) : ?>
                        <p><em>Set in <code>wp-config.php</code> via <code>EROS_TEXT_TELNYX_API_KEY</code> (recommended). This field is ignored.</em></p>
                    <?php else : ?>
                        <input type="password" name="telnyx_api_key" id="telnyx_api_key" class="regular-text" autocomplete="new-password"
                               placeholder="<?php echo $key_set ? '•••••••• (leave blank to keep current)' : 'KEY...'; ?>">
                        <p class="description">
                            <?php echo $key_set ? 'A key is saved. Leave blank to keep it, or type a new one to replace it.' : 'Paste your Telnyx API key (starts with KEY…).'; ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="telnyx_from">From number</label></th>
                <td>
                    <input type="text" name="telnyx_from" id="telnyx_from" class="regular-text"
                           value="<?php echo esc_attr($s['telnyx_from']); ?>" placeholder="+18577700798">
                    <p class="description">Your Telnyx number in E.164 format. Required unless you set a Messaging Profile ID below.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="telnyx_messaging_profile_id">Messaging Profile ID</label></th>
                <td>
                    <input type="text" name="telnyx_messaging_profile_id" id="telnyx_messaging_profile_id" class="regular-text"
                           value="<?php echo esc_attr($s['telnyx_messaging_profile_id']); ?>" placeholder="(optional)">
                    <p class="description">Optional. If your number is already assigned to a profile, the From number alone is enough.</p>
                </td>
            </tr>
        </table>
        </div><!-- /telnyx -->

        <div class="eros-provider eros-provider-plivo">
        <h2 class="title">Plivo</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="plivo_auth_id">Auth ID</label></th>
                <td>
                    <input type="text" name="plivo_auth_id" id="plivo_auth_id" class="regular-text"
                           value="<?php echo esc_attr($s['plivo_auth_id']); ?>" placeholder="MAxxxxxxxxxxxxxxxxxx">
                    <p class="description">From your Plivo console dashboard. Not secret.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="plivo_auth_token">Auth Token</label></th>
                <td>
                    <?php if ($plivo_token_const) : ?>
                        <p><em>Set in <code>wp-config.php</code> via <code>EROS_TEXT_PLIVO_AUTH_TOKEN</code> (recommended). This field is ignored.</em></p>
                    <?php else : ?>
                        <input type="password" name="plivo_auth_token" id="plivo_auth_token" class="regular-text" autocomplete="new-password"
                               placeholder="<?php echo $plivo_token_set ? '•••••••• (leave blank to keep current)' : 'your Plivo Auth Token'; ?>">
                        <p class="description"><?php echo $plivo_token_set ? 'A token is saved. Leave blank to keep it, or type a new one to replace it.' : 'Paste your Plivo Auth Token.'; ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="plivo_from">From number</label></th>
                <td>
                    <input type="text" name="plivo_from" id="plivo_from" class="regular-text"
                           value="<?php echo esc_attr($s['plivo_from']); ?>" placeholder="+15555550123">
                    <p class="description">Your Plivo number in E.164 format (the + is fine — it's handled automatically).</p>
                </td>
            </tr>
        </table>
        </div><!-- /plivo -->

        <h2 class="title">Store &amp; orders</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="store_name">Store name</label></th>
                <td><input type="text" name="store_name" id="store_name" class="regular-text" value="<?php echo esc_attr($s['store_name']); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="default_country">Default country</label></th>
                <td>
                    <input type="text" name="default_country" id="default_country" class="small-text" maxlength="2" value="<?php echo esc_attr($s['default_country']); ?>">
                    <p class="description">Two-letter code (e.g. US) used to format phone numbers when the order has no country.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="shipped_status">"Shipped" status</label></th>
                <td>
                    <input type="text" name="shipped_status" id="shipped_status" class="regular-text" value="<?php echo esc_attr($s['shipped_status']); ?>">
                    <p class="description">The order status that means shipped. WooCommerce default is <code>completed</code>. Use a custom status slug if your shipping plugin sets one (no <code>wc-</code> prefix).</p>
                </td>
            </tr>
        </table>

        <h2 class="title">Which texts to send</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Events</th>
                <td>
                    <label><input type="checkbox" name="event_placed" value="1" <?php checked($s['event_placed'], 1); ?>> Order placed</label><br>
                    <label><input type="checkbox" name="event_processing" value="1" <?php checked($s['event_processing'], 1); ?>> Processing</label><br>
                    <label><input type="checkbox" name="event_shipped" value="1" <?php checked($s['event_shipped'], 1); ?>> Shipped</label>
                </td>
            </tr>
        </table>

        <h2 class="title">Message templates</h2>
        <p class="description" style="margin-bottom:8px;">
            Available placeholders: <code><?php echo esc_html($placeholders); ?></code>.
            <br><code>{tracking_line}</code> becomes e.g. "USPS tracking: 123. Track it: https://…" on shipment, or a friendly fallback if no tracking is recorded.
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="tpl_placed">Order placed</label></th>
                <td><textarea name="tpl_placed" id="tpl_placed" rows="3" class="large-text"><?php echo esc_textarea($s['tpl_placed']); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="tpl_processing">Processing</label></th>
                <td><textarea name="tpl_processing" id="tpl_processing" rows="3" class="large-text"><?php echo esc_textarea($s['tpl_processing']); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="tpl_shipped">Shipped</label></th>
                <td><textarea name="tpl_shipped" id="tpl_shipped" rows="3" class="large-text"><?php echo esc_textarea($s['tpl_shipped']); ?></textarea></td>
            </tr>
        </table>

        <h2 class="title">Updates</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Auto-update</th>
                <td>
                    <label><input type="checkbox" name="auto_update" value="1" <?php checked($s['auto_update'], 1); ?>> Automatically install new versions from GitHub</label>
                    <p class="description">Installed version: <code><?php echo esc_html(EROS_TEXT_VERSION); ?></code>. Updates are published to
                        <a href="<?php echo esc_url('https://github.com/' . EROS_TEXT_GH_OWNER . '/' . EROS_TEXT_GH_REPO . '/releases'); ?>" target="_blank" rel="noopener">GitHub releases</a>.</p>
                </td>
            </tr>
        </table>

        <?php submit_button('Save settings'); ?>
    </form>
</div>

<script>
(function () {
    var sel = document.getElementById('sms_provider');
    if (!sel) { return; }
    function sync() {
        var p = sel.value;
        document.querySelectorAll('.eros-provider').forEach(function (el) {
            el.style.display = el.classList.contains('eros-provider-' + p) ? '' : 'none';
        });
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
