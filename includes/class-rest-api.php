<?php
if (!defined('ABSPATH')) exit;

require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/admin/class-admin.php';
require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/class-concurrent-edit.php';

class Beer_Festival_REST {

    const NAMESPACE_ = 'beer-festival-tap-list/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function can_manage() {
        return current_user_can('manage_options');
    }

    public function register_routes() {
        register_rest_route(self::NAMESPACE_, '/taps', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_taps'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save_taps'],
                'permission_callback' => [__CLASS__, 'can_manage'],
                'args'                => [
                    'tap_beer' => ['required' => true],
                ],
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

    public function get_taps(WP_REST_Request $request) {
        $public = new Beer_Festival_Public();
        $data = $public->get_tap_list_data();
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    public function save_taps(WP_REST_Request $request) {
        $admin = new Beer_Festival_Admin();
        $result = $admin->save_taps_data($request->get_param('tap_beer'), get_current_user_id());
        return rest_ensure_response($result);
    }

    public function get_activity(WP_REST_Request $request) {
        $concurrent_edit = new Beer_Festival_Concurrent_Edit();
        $data = $concurrent_edit->get_activity_data($request->get_param('since'));
        return rest_ensure_response($data);
    }
}
