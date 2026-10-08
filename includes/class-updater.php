<?php
if (!defined('ABSPATH')) exit;

/**
 * Self-updates from this plugin's public GitHub repository.
 *
 * Every release is a `vX.Y.Z` tag on GitHub. WordPress is told about the newest
 * tag through the `update_plugins_github.com` filter (enabled by the plugin's
 * `Update URI` header), so the normal "Update available" notice, one-click
 * update and auto-update toggle all work. Nothing is published per release
 * other than the tag itself.
 *
 * GitHub's tag archive unpacks to `Beer-Tap-List-X.Y.Z/`, so the folder is
 * renamed to the plugin's real slug during the upgrade; otherwise WordPress
 * would install a second copy instead of replacing this one.
 */
class Beer_Festival_Updater {

    const REPO          = 'ivanronga/Beer-Tap-List';
    const SLUG          = 'beer-festival-tap-list';
    const CACHE_KEY     = 'bftl_update_info';
    const SUCCESS_TTL   = 3 * HOUR_IN_SECONDS;
    const FAILURE_TTL   = 30 * MINUTE_IN_SECONDS;
    const CHANGELOG_TTL = 12 * HOUR_IN_SECONDS;

    private $basename;

    public function __construct() {
        $this->basename = plugin_basename(BEER_FESTIVAL_PLUGIN_DIR . 'beer-festival-tap-list.php');

        add_filter('update_plugins_github.com', [$this, 'offer_update'], 10, 3);
        add_filter('plugins_api', [$this, 'plugin_details'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_source_folder'], 10, 4);
        add_filter('plugin_action_links_' . $this->basename, [$this, 'action_links']);
        add_action('admin_post_bftl_check_updates', [$this, 'handle_check_updates']);
    }

    /**
     * A development checkout (has .git) must never be replaced by an update:
     * installing one swaps the whole folder and would delete the repository.
     */
    private function is_dev_checkout() {
        if (defined('BFTL_FORCE_UPDATES') && BFTL_FORCE_UPDATES) {
            return false;
        }
        return file_exists(BEER_FESTIVAL_PLUGIN_DIR . '.git');
    }

    /**
     * Newest release as ['version' => '2.19.0', 'tag' => 'v2.19.0', 'package' => url],
     * or null when GitHub cannot be reached. Cached, including failures, so a
     * GitHub outage or rate limit never slows down the admin.
     */
    public function get_latest_release() {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return isset($cached['version']) ? $cached : null;
        }

        $latest = $this->fetch_latest_release();
        set_transient(self::CACHE_KEY, $latest ? $latest : ['error' => 1], $latest ? self::SUCCESS_TTL : self::FAILURE_TTL);
        return $latest;
    }

    private function fetch_latest_release() {
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/tags?per_page=100', [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Beer-Festival-Tap-List-Updater; ' . home_url(),
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $tags = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($tags)) {
            return null;
        }

        $best = null;
        foreach ($tags as $tag) {
            if (!isset($tag['name']) || !preg_match('/^v(\d+\.\d+\.\d+)$/', $tag['name'], $m)) {
                continue; // ignore anything that is not a plain vX.Y.Z release tag
            }
            // The API does not guarantee order, so compare versions explicitly.
            if ($best === null || version_compare($m[1], $best['version'], '>')) {
                $best = [
                    'version' => $m[1],
                    'tag'     => $tag['name'],
                    'package' => 'https://github.com/' . self::REPO . '/archive/refs/tags/' . $tag['name'] . '.zip',
                ];
            }
        }
        return $best;
    }

    /**
     * Filter `update_plugins_github.com`: the update record for this plugin.
     * WordPress itself decides whether the version is newer than the installed one.
     */
    public function offer_update($update, $plugin_data, $plugin_file) {
        if ($plugin_file !== $this->basename || $this->is_dev_checkout()) {
            return $update;
        }

        $latest = $this->get_latest_release();
        if (!$latest) {
            return $update;
        }

        return [
            'id'      => 'github.com/' . self::REPO,
            'slug'    => self::SLUG,
            'version' => $latest['version'],
            'url'     => 'https://github.com/' . self::REPO . '/tree/' . $latest['tag'],
            'package' => $latest['package'],
        ];
    }

    /**
     * Filter `plugins_api`: content of the "View version x.y.z details" popup.
     */
    public function plugin_details($result, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $latest = $this->get_latest_release();
        if (!$latest) {
            return $result;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $header = get_plugin_data(BEER_FESTIVAL_PLUGIN_DIR . 'beer-festival-tap-list.php', false, false);

        $info = new stdClass();
        $info->name          = $header['Name'];
        $info->slug          = self::SLUG;
        $info->version       = $latest['version'];
        $info->author        = esc_html($header['Author']);
        $info->homepage      = 'https://github.com/' . self::REPO;
        $info->requires      = $header['RequiresWP'];
        $info->requires_php  = $header['RequiresPHP'];
        $info->download_link = $latest['package'];
        $info->sections      = [
            'description' => '<p>' . esc_html($header['Description']) . '</p>',
            'changelog'   => $this->get_changelog_html($latest['tag']),
        ];
        return $info;
    }

    private function get_changelog_html($tag) {
        $key = 'bftl_update_changelog_' . md5($tag);
        $html = get_transient($key);
        if (is_string($html)) {
            return $html;
        }

        $fallback = '<p><a href="' . esc_url('https://github.com/' . self::REPO . '/releases/tag/' . $tag) . '">'
                  . esc_html__('See this version on GitHub', 'beer-festival-tap') . '</a></p>';

        $response = wp_remote_get('https://raw.githubusercontent.com/' . self::REPO . '/' . rawurlencode($tag) . '/readme.txt', [
            'timeout' => 10,
            'headers' => ['User-Agent' => 'Beer-Festival-Tap-List-Updater; ' . home_url()],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $fallback; // not cached: try again next time
        }

        $html = self::changelog_to_html(wp_remote_retrieve_body($response));
        if ($html === '') {
            $html = $fallback;
        }
        set_transient($key, $html, self::CHANGELOG_TTL);
        return $html;
    }

    /**
     * Turns the "== Changelog ==" block of a readme.txt into <h4>/<ul> markup.
     */
    public static function changelog_to_html($readme) {
        $readme = str_replace("\r\n", "\n", $readme);
        if (!preg_match('/^==\s*Changelog\s*==\s*\n(.*?)(?=^==\s*[^=]|\z)/ms', $readme, $m)) {
            return '';
        }

        $html = '';
        $open = false;
        foreach (explode("\n", $m[1]) as $line) {
            $line = trim($line);
            if (preg_match('/^=\s*(.+?)\s*=$/', $line, $h)) {
                $html .= ($open ? '</ul>' : '') . '<h4>' . esc_html($h[1]) . '</h4><ul>';
                $open = true;
            } elseif ($open && strpos($line, '*') === 0) {
                $html .= '<li>' . esc_html(trim(substr($line, 1))) . '</li>';
            }
        }
        return $html . ($open ? '</ul>' : '');
    }

    /**
     * Filter `upgrader_source_selection`: rename GitHub's archive folder
     * (Beer-Tap-List-X.Y.Z) to this plugin's folder name before WordPress
     * swaps it in. Returning a WP_Error aborts the update with the installed
     * plugin untouched.
     */
    public function fix_source_folder($source, $remote_source, $upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $source;
        }

        global $wp_filesystem;
        $target = trailingslashit($remote_source) . self::SLUG . '/';

        if (untrailingslashit($source) !== untrailingslashit($target)) {
            if (!$wp_filesystem || !$wp_filesystem->move($source, $target, true)) {
                return new WP_Error('bftl_rename_failed', __('Could not rename the downloaded plugin folder.', 'beer-festival-tap'));
            }
        }

        if (!$wp_filesystem->exists($target . 'beer-festival-tap-list.php')) {
            return new WP_Error('bftl_invalid_package', __('The downloaded package does not contain the plugin.', 'beer-festival-tap'));
        }

        return $target;
    }

    /**
     * "Check for updates" next to Deactivate on the Plugins page: drops the
     * cached answer so a freshly pushed tag is picked up immediately.
     */
    public function action_links($links) {
        if ($this->is_dev_checkout()) {
            return $links;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=bftl_check_updates'), 'bftl_check_updates');
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates', 'beer-festival-tap') . '</a>';
        return $links;
    }

    public function handle_check_updates() {
        check_admin_referer('bftl_check_updates');
        if (!current_user_can('update_plugins')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }

        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        wp_safe_redirect(self_admin_url('update-core.php?force-check=1'));
        exit;
    }
}
