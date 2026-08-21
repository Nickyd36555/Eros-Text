<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Self-hosted auto-updater. Watches the plugin's GitHub repo for a newer
 * release and lets WordPress install it like any other plugin update — including
 * unattended auto-updates when enabled in Settings.
 *
 * How it works:
 *  - A GitHub Actions workflow publishes a release (tag v{version}) with an
 *    "eros-text.zip" asset whenever the plugin version is bumped and pushed.
 *  - This class reads the latest release, compares versions, and hands WordPress
 *    the asset URL to download. The zip already contains a top-level eros-text/
 *    folder, so it installs cleanly.
 *
 * Fails closed: if GitHub is unreachable, no update is offered and nothing breaks.
 */
class Eros_Text_Updater {

    private $basename;   // e.g. eros-text/eros-text.php
    private $slug;       // e.g. eros-text
    private $version;    // installed version
    private $owner;
    private $repo;
    private $cache_key;

    public function __construct($basename, $slug, $version, $owner, $repo) {
        $this->basename  = $basename;
        $this->slug      = $slug;
        $this->version   = $version;
        $this->owner     = $owner;
        $this->repo      = $repo;
        $this->cache_key = 'eros_text_gh_release';

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_source_dir'], 10, 4);

        // Respect the Settings toggle for unattended auto-updates.
        add_filter('auto_update_plugin', [$this, 'maybe_auto_update'], 10, 2);

        // Clear the cached release info after any plugin update runs.
        add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 0);
    }

    /**
     * Fetch (and cache) the latest release from GitHub.
     * @return array{version:string,zip:string,url:string,body:string,published:string}|null
     */
    private function get_latest_release() {
        $cached = get_transient($this->cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $endpoint = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo);
        $response = wp_remote_get($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Eros-Text-Updater',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            // Cache the miss briefly so a down/rate-limited API doesn't hammer.
            set_transient($this->cache_key, ['version' => '', 'zip' => '', 'url' => '', 'body' => '', 'published' => ''], HOUR_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return null;
        }

        // Parse a clean version out of the tag (e.g. "v1.2.3" -> "1.2.3").
        $version = '';
        if (preg_match('/(\d+(?:\.\d+){1,3})/', $data['tag_name'], $m)) {
            $version = $m[1];
        }

        // Prefer the eros-text.zip asset; fall back to the source zipball.
        $zip = '';
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['name']) && $asset['name'] === 'eros-text.zip' && !empty($asset['browser_download_url'])) {
                    $zip = $asset['browser_download_url'];
                    break;
                }
            }
        }
        if (!$zip && !empty($data['zipball_url'])) {
            $zip = $data['zipball_url'];
        }

        $release = [
            'version'   => $version,
            'zip'       => $zip,
            'url'       => isset($data['html_url']) ? $data['html_url'] : '',
            'body'      => isset($data['body']) ? $data['body'] : '',
            'published' => isset($data['published_at']) ? $data['published_at'] : '',
        ];

        set_transient($this->cache_key, $release, 6 * HOUR_IN_SECONDS);
        return $release;
    }

    public function check_for_update($transient) {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release || empty($release['version']) || empty($release['zip'])) {
            return $transient;
        }

        if (version_compare($release['version'], $this->version, '>')) {
            $item = [
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $release['version'],
                'url'         => $release['url'],
                'package'     => $release['zip'],
            ];
            $transient->response[$this->basename] = (object) $item;
        } else {
            // Record that it's up to date (helps the Updates screen).
            $transient->no_update[$this->basename] = (object) [
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $this->version,
                'url'         => $release['url'],
                'package'     => '',
            ];
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $release = $this->get_latest_release();
        $version = ($release && $release['version']) ? $release['version'] : $this->version;

        $info = [
            'name'          => 'Eros Text',
            'slug'          => $this->slug,
            'version'       => $version,
            'author'        => 'Eros Labs',
            'homepage'      => sprintf('https://github.com/%s/%s', $this->owner, $this->repo),
            'download_link' => ($release && $release['zip']) ? $release['zip'] : '',
            'sections'      => [
                'description' => 'Eros Labs order SMS notifications via Telnyx: placed, processing, and shipped (with tracking). Editable messages, direct texting, per-event toggles, and a send log.',
                'changelog'   => ($release && $release['body']) ? wp_kses_post(nl2br($release['body'])) : 'See GitHub releases.',
            ],
        ];

        return (object) $info;
    }

    /**
     * When installing from a GitHub source zipball, the extracted folder may be
     * named something like "Owner-Repo-abc123". Rename it to the plugin slug so
     * WordPress updates the existing plugin in place. (No-op for our named asset.)
     */
    public function fix_source_dir($source, $remote_source, $upgrader, $hook_extra = null) {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $source;
        }

        global $wp_filesystem;
        $desired = trailingslashit($remote_source) . $this->slug;

        if (trailingslashit($source) === trailingslashit($desired)) {
            return $source;
        }
        if ($wp_filesystem && $wp_filesystem->move($source, $desired, true)) {
            return trailingslashit($desired);
        }
        return $source;
    }

    public function maybe_auto_update($update, $item) {
        $plugin = '';
        if (is_object($item) && isset($item->plugin)) {
            $plugin = $item->plugin;
        }
        if ($plugin === $this->basename) {
            return (bool) Eros_Text_Settings::get('auto_update', 1);
        }
        return $update;
    }

    public function clear_cache() {
        delete_transient($this->cache_key);
    }
}
