<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Categories {

    const OPTION_NAME = 'beer_festival_categories';
    const DEFAULT_CATEGORY = 'Default';
    const SEED = ['Default', 'Pale Ale', 'IPA', 'Lager', 'NEIPA/Hazy', 'Dark', 'Sour', 'Special'];

    public static function get_all() {
        $categories = get_option(self::OPTION_NAME, null);
        if ($categories === null) {
            $categories = self::SEED;
            update_option(self::OPTION_NAME, $categories);
        }
        return $categories;
    }

    public static function get_selectable($include_default = true) {
        $categories = self::get_all();
        if (!$include_default) {
            $categories = array_values(array_diff($categories, [self::DEFAULT_CATEGORY]));
        }
        return $categories;
    }

    public static function add($name) {
        $name = trim(sanitize_text_field($name));
        if ($name === '') {
            return new WP_Error('bftl_empty_category', __('Category name cannot be empty.', 'beer-festival-tap'));
        }
        $categories = self::get_all();
        if (in_array($name, $categories, true)) {
            return new WP_Error('bftl_category_exists', __('That category already exists.', 'beer-festival-tap'));
        }
        $categories[] = $name;
        update_option(self::OPTION_NAME, $categories);
        return true;
    }

    public static function rename($old_name, $new_name) {
        if ($old_name === self::DEFAULT_CATEGORY) {
            return new WP_Error('bftl_protected_category', __('The Default category cannot be renamed.', 'beer-festival-tap'));
        }
        $new_name = trim(sanitize_text_field($new_name));
        if ($new_name === '') {
            return new WP_Error('bftl_empty_category', __('Category name cannot be empty.', 'beer-festival-tap'));
        }
        $categories = self::get_all();
        $key = array_search($old_name, $categories, true);
        if ($key === false) {
            return new WP_Error('bftl_category_not_found', __('Category not found.', 'beer-festival-tap'));
        }
        if ($new_name !== $old_name && in_array($new_name, $categories, true)) {
            return new WP_Error('bftl_category_exists', __('That category already exists.', 'beer-festival-tap'));
        }

        $categories[$key] = $new_name;
        update_option(self::OPTION_NAME, $categories);
        self::cascade_rename($old_name, $new_name);
        return true;
    }

    public static function delete($name) {
        if ($name === self::DEFAULT_CATEGORY) {
            return new WP_Error('bftl_protected_category', __('The Default category cannot be deleted.', 'beer-festival-tap'));
        }
        if (self::is_in_use($name)) {
            return new WP_Error('bftl_category_in_use', __('This category is still assigned to a beer or a tap and cannot be deleted.', 'beer-festival-tap'));
        }
        $categories = self::get_all();
        $categories = array_values(array_diff($categories, [$name]));
        update_option(self::OPTION_NAME, $categories);
        return true;
    }

    public static function is_in_use($name) {
        $beers = get_posts([
            'post_type' => 'beer',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_key' => '_beer_category',
            'meta_value' => $name,
        ]);
        if (!empty($beers)) {
            return true;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'tap_status';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE category = %s",
            $name
        ));
        return intval($count) > 0;
    }

    private static function cascade_rename($old_name, $new_name) {
        global $wpdb;

        $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $new_name],
            ['meta_key' => '_beer_category', 'meta_value' => $old_name],
            ['%s'],
            ['%s', '%s']
        );

        $table_name = $wpdb->prefix . 'tap_status';
        $wpdb->update(
            $table_name,
            ['category' => $new_name],
            ['category' => $old_name],
            ['%s'],
            ['%s']
        );
    }
}
