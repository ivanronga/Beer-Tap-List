<?php
if (!defined('ABSPATH')) exit;

require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/admin/class-admin.php';
require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/class-concurrent-edit.php';

class Beer_Festival_REST {

    const NAMESPACE_ = 'beer-festival-tap-list/v1';
    const RATE_LIMIT_MAX = 10;
    const RATE_LIMIT_WINDOW = 300; // 5 minutes

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function can_manage() {
        return current_user_can('manage_options');
    }

    public function register_routes() {
        register_rest_route(self::NAMESPACE_, '/taps', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_taps'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE_, '/taps/assign', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'assign_tap'],
            'permission_callback' => '__return_true',
            'args'                => [
                'tap_id'  => ['required' => true, 'type' => 'integer'],
                'beer_id' => ['required' => false, 'type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route(self::NAMESPACE_, '/taps/category', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'set_tap_category'],
            'permission_callback' => [__CLASS__, 'can_manage'],
            'args'                => [
                'tap_id'   => ['required' => true, 'type' => 'integer'],
                'category' => ['required' => false, 'type' => 'string', 'default' => ''],
            ],
        ]);

        register_rest_route(self::NAMESPACE_, '/activity', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_activity'],
            'permission_callback' => [__CLASS__, 'can_manage'],
            'args'                => [
                'since' => [
                    'type'    => 'integer',
                    'default' => 0,
                ],
            ],
        ]);
    }

    private function is_rate_limited() {
        $key = 'bftl_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $count = get_transient($key);
        if ($count === false) {
            set_transient($key, 1, self::RATE_LIMIT_WINDOW);
            return false;
        }
        if ($count >= self::RATE_LIMIT_MAX) {
            return true;
        }
        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
        return false;
    }

    public function get_taps(WP_REST_Request $request) {
        $public = new Beer_Festival_Public();
        $data = $public->get_tap_list_data();
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    public function assign_tap(WP_REST_Request $request) {
        if ($this->is_rate_limited()) {
            return new WP_Error('bftl_rate_limited', __('Too many requests, please try again in a few minutes.', 'beer-festival-tap'), ['status' => 429]);
        }

        $settings = get_option('beer_festival_settings', []);
        $tap_count = isset($settings['tap_count']) ? intval($settings['tap_count']) : 16;
        $tap_id = intval($request->get_param('tap_id'));
        $beer_id = intval($request->get_param('beer_id'));

        if ($tap_id < 1 || $tap_id > $tap_count) {
            return new WP_Error('bftl_invalid_tap', __('Invalid tap number.', 'beer-festival-tap'), ['status' => 400]);
        }
        if ($beer_id !== 0) {
            $beer = get_post($beer_id);
            if (!$beer || $beer->post_type !== 'beer' || $beer->post_status !== 'publish') {
                return new WP_Error('bftl_invalid_beer', __('Invalid beer.', 'beer-festival-tap'), ['status' => 400]);
            }
        }

        $admin = new Beer_Festival_Admin();
        $result = $admin->assign_single_tap($tap_id, $beer_id, get_current_user_id());
        return rest_ensure_response($result);
    }

    public function set_tap_category(WP_REST_Request $request) {
        $settings = get_option('beer_festival_settings', []);
        $tap_count = isset($settings['tap_count']) ? intval($settings['tap_count']) : 16;
        $tap_id = intval($request->get_param('tap_id'));
        $category = (string) $request->get_param('category');

        if ($tap_id < 1 || $tap_id > $tap_count) {
            return new WP_Error('bftl_invalid_tap', __('Invalid tap number.', 'beer-festival-tap'), ['status' => 400]);
        }
        if ($category === '') {
            $category = Beer_Festival_Categories::DEFAULT_CATEGORY;
        }
        if (!in_array($category, Beer_Festival_Categories::get_all(), true)) {
            return new WP_Error('bftl_invalid_category', __('Invalid category.', 'beer-festival-tap'), ['status' => 400]);
        }

        $admin = new Beer_Festival_Admin();
        $result = $admin->set_tap_category($tap_id, $category);
        return rest_ensure_response($result);
    }

    public function get_activity(WP_REST_Request $request) {
        $concurrent_edit = new Beer_Festival_Concurrent_Edit();
        $data = $concurrent_edit->get_activity_data($request->get_param('since'));
        return rest_ensure_response($data);
    }
}
