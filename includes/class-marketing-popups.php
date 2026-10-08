<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Marketing_Popups {

    const SETTINGS_OPTION = 'beer_festival_marketing_settings';
    const SETTINGS_DEFAULTS = [
        'enabled'          => 1,
        'interval_seconds' => 900,
        'duration_seconds' => 10,
    ];
    const MAX_WEIGHT = 100;

    /**
     * Create/upgrade the marketing popups table on plugin activation and version bumps.
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta can't rename or drop columns, so earlier schemas are converted
        // in place before it runs. On a fresh install there is nothing to convert.
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;

        // v2.10.0-2.10.4 stored the interval in minutes.
        $has_minutes = $table_exists && $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'interval_minutes'");
        if ($has_minutes) {
            $wpdb->query("ALTER TABLE $table_name CHANGE interval_minutes interval_seconds INT UNSIGNED NOT NULL DEFAULT 900");
            $wpdb->query("UPDATE $table_name SET interval_seconds = interval_seconds * 60");
        }

        // v2.10.5-2.10.6 stored interval/duration per ad; they are now global
        // settings. Seed the global values from the first ad, then drop the columns.
        $has_per_ad_timing = $table_exists && $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'interval_seconds'");
        if ($has_per_ad_timing) {
            if (get_option(self::SETTINGS_OPTION, null) === null) {
                $first = $wpdb->get_row("SELECT interval_seconds, duration_seconds FROM $table_name ORDER BY display_order ASC, id ASC LIMIT 1");
                if ($first) {
                    update_option(self::SETTINGS_OPTION, [
                        'enabled'          => 1,
                        'interval_seconds' => max(1, intval($first->interval_seconds)),
                        'duration_seconds' => max(1, intval($first->duration_seconds)),
                    ]);
                }
            }
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN interval_seconds, DROP COLUMN duration_seconds");
        }

        $sql = "CREATE TABLE $table_name (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            image_id BIGINT UNSIGNED NOT NULL,
            weight INT UNSIGNED NOT NULL DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            display_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function get_settings() {
        $saved = get_option(self::SETTINGS_OPTION, []);
        return array_merge(self::SETTINGS_DEFAULTS, is_array($saved) ? $saved : []);
    }

    public static function save_settings($data) {
        $interval_seconds = intval($data['interval_seconds'] ?? 0);
        if ($interval_seconds < 1) {
            return new WP_Error('bftl_popup_invalid_interval', __('Interval must be at least 1 second.', 'beer-festival-tap'));
        }

        $duration_seconds = intval($data['duration_seconds'] ?? 0);
        if ($duration_seconds < 1) {
            return new WP_Error('bftl_popup_invalid_duration', __('Duration must be at least 1 second.', 'beer-festival-tap'));
        }

        update_option(self::SETTINGS_OPTION, [
            'enabled'          => !empty($data['enabled']) ? 1 : 0,
            'interval_seconds' => $interval_seconds,
            'duration_seconds' => $duration_seconds,
        ]);

        return true;
    }

    public static function get_all() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY display_order ASC, id ASC");

        if ($wpdb->last_error) {
            return new WP_Error('db_error', __('Database error: ', 'beer-festival-tap') . $wpdb->last_error);
        }

        return $results;
    }

    public static function get_enabled_ordered() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $results = $wpdb->get_results("SELECT * FROM $table_name WHERE enabled = 1 ORDER BY display_order ASC, id ASC");

        if ($wpdb->last_error) {
            return new WP_Error('db_error', __('Database error: ', 'beer-festival-tap') . $wpdb->last_error);
        }

        return $results;
    }

    public static function get($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($id)));

        return $row ?: null;
    }

    public static function create($data) {
        $validated = self::validate_fields($data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $next_order = intval($wpdb->get_var("SELECT COALESCE(MAX(display_order), 0) + 1 FROM $table_name"));

        $inserted = $wpdb->insert(
            $table_name,
            [
                'image_id'      => $validated['image_id'],
                'weight'        => $validated['weight'],
                'enabled'       => $validated['enabled'],
                'display_order' => $next_order,
            ],
            ['%d', '%d', '%d', '%d']
        );

        if ($inserted === false) {
            return new WP_Error('bftl_popup_insert_failed', __('Could not save the ad.', 'beer-festival-tap'));
        }

        return true;
    }

    public static function update($id, $data) {
        $validated = self::validate_fields($data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $updated = $wpdb->update(
            $table_name,
            [
                'image_id' => $validated['image_id'],
                'weight'   => $validated['weight'],
                'enabled'  => $validated['enabled'],
            ],
            ['id' => intval($id)],
            ['%d', '%d', '%d'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('bftl_popup_update_failed', __('Could not save the ad.', 'beer-festival-tap'));
        }

        return true;
    }

    public static function delete($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';

        $deleted = $wpdb->delete($table_name, ['id' => intval($id)], ['%d']);

        if ($deleted === false) {
            return new WP_Error('bftl_popup_delete_failed', __('Could not delete the ad.', 'beer-festival-tap'));
        }

        return true;
    }

    public static function toggle_enabled($id) {
        $ad = self::get($id);
        if (!$ad) {
            return new WP_Error('bftl_popup_not_found', __('Ad not found.', 'beer-festival-tap'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';
        $new_state = $ad->enabled ? 0 : 1;

        $wpdb->update($table_name, ['enabled' => $new_state], ['id' => intval($id)], ['%d'], ['%d']);

        return $new_state;
    }

    /**
     * Moves an ad one position up or down in the list. Re-numbers display_order
     * for every ad so the swap works even if existing values were equal/gappy.
     */
    public static function move($id, $direction) {
        $ads = self::get_all();
        if (is_wp_error($ads)) {
            return $ads;
        }

        $ids = array_map(function ($ad) { return intval($ad->id); }, $ads);
        $index = array_search(intval($id), $ids, true);
        if ($index === false) {
            return new WP_Error('bftl_popup_not_found', __('Ad not found.', 'beer-festival-tap'));
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target >= 0 && $target < count($ids)) {
            $swap = $ids[$index];
            $ids[$index] = $ids[$target];
            $ids[$target] = $swap;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bftl_marketing_popups';
        foreach ($ids as $position => $ad_id) {
            $wpdb->update($table_name, ['display_order' => $position + 1], ['id' => $ad_id], ['%d'], ['%d']);
        }

        return true;
    }

    private static function validate_fields($data) {
        $image_id = intval($data['image_id'] ?? 0);
        if ($image_id <= 0 || !wp_attachment_is_image($image_id)) {
            return new WP_Error('bftl_popup_invalid_image', __('Please select an image for this ad.', 'beer-festival-tap'));
        }

        $weight = intval($data['weight'] ?? 1);
        if ($weight < 1 || $weight > self::MAX_WEIGHT) {
            return new WP_Error('bftl_popup_invalid_weight', sprintf(
                /* translators: %d: maximum weight */
                __('Weight must be between 1 and %d.', 'beer-festival-tap'),
                self::MAX_WEIGHT
            ));
        }

        return [
            'image_id' => $image_id,
            'weight'   => $weight,
            'enabled'  => !empty($data['enabled']) ? 1 : 0,
        ];
    }
}
