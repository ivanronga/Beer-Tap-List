<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Concurrent_Edit {

    const ACTIVITY_LIMIT = 50;
    const OPTION_NAME = 'beer_festival_edit_activity';

    public function __construct() {
        add_action('beer_festival_tap_updated', [$this, 'record_edit_activity'], 10, 3);
    }

    // Record edit activity when taps are modified
    public function record_edit_activity($tap_id, $user_id, $action) {
        $user = get_userdata($user_id);
        $activity = [
            'time' => time(),
            'tap' => $tap_id,
            'user' => $user_id,
            'action' => $action,
            'user_name' => $user ? $user->display_name : __('Unknown user', 'beer-festival-tap')
        ];
        
        $log = get_option(self::OPTION_NAME, []);
        array_unshift($log, $activity);
        $log = array_slice($log, 0, self::ACTIVITY_LIMIT);
        update_option(self::OPTION_NAME, $log);
    }

    // Fetch activity newer than $last_check (unix timestamp)
    public function get_activity_data($last_check) {
        $last_check = intval($last_check);
        $log = get_option(self::OPTION_NAME, []);
        $new_activities = [];

        foreach ($log as $activity) {
            if ($activity['time'] > $last_check) {
                $new_activities[] = $activity;
            }
        }

        return [
            'activities' => $new_activities,
            'current_time' => time()
        ];
    }
}
