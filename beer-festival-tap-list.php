<?php
/**
 * Plugin Name: Beer Festival Tap List
 * Description: Real-time tap list management for beer festivals
 * Version: 2.13.0
 * Author: Beer Festival Tap List Contributors
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beer-festival-tap
 * Domain Path: /languages
 */

// Security check
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('BEER_FESTIVAL_VERSION', '2.13.0');
define('BEER_FESTIVAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BEER_FESTIVAL_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('init', 'beer_festival_load_textdomain');
function beer_festival_load_textdomain() {
    load_plugin_textdomain('beer-festival-tap', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'beer_festival_activate');
register_deactivation_hook(__FILE__, 'beer_festival_deactivate');

function beer_festival_activate() {
    require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/class-tap-manager.php';
    require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/class-beer-cpt.php';
    require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/class-marketing-popups.php';

    Tap_Manager::create_tap_status_table();
    Beer_Festival_Marketing_Popups::create_table();

    // Register the beer CPT and flush rewrite rules
    $beer_cpt = new Beer_CPT();
    $beer_cpt->register_beer_cpt();
    flush_rewrite_rules();
}

function beer_festival_deactivate() {
}

// Load core components
add_action('plugins_loaded', 'beer_festival_init_plugin');

function beer_festival_init_plugin() {
    // Load required classes
    $core_files = [
        'includes/class-categories.php',  // Beer/tap categories
        'includes/class-marketing-popups.php', // Marketing popup ads
        'includes/class-beer-import-export.php', // CSV export/import for beers
        'includes/class-beer-cpt.php',    // Beer custom post type
        'includes/class-tap-manager.php', // Tap management
        'includes/class-settings.php',    // Plugin settings
        'includes/admin/class-admin.php',          // Admin interface
        'includes/public/class-public.php',        // Frontend display
        'includes/class-rest-api.php'              // REST API endpoints
    ];

    foreach ($core_files as $file) {
        require_once BEER_FESTIVAL_PLUGIN_DIR . $file;
    }

    // Tap changes arrive through the REST API (QR flow, Tap Management Save),
    // which is not is_admin(), so the activity log listener must always be on.
    require_once BEER_FESTIVAL_PLUGIN_DIR . 'includes/class-concurrent-edit.php';
    new Beer_Festival_Concurrent_Edit();

    // Initialize admin and public components
    if (is_admin()) {
        global $beer_festival_settings;
        $beer_festival_settings = new Beer_Festival_Settings();
        new Beer_Festival_Admin();
    }
    new Beer_Festival_Public();
    new Beer_Festival_REST();

    add_action('init', 'beer_festival_maybe_upgrade', 20);
}

/**
 * Runs once per version bump, after CPT registration (init priority 10) so
 * flush_rewrite_rules() picks up the current rewrite rules.
 */
function beer_festival_maybe_upgrade() {
    $db_version = get_option('beer_festival_db_version', '');
    if ($db_version === BEER_FESTIVAL_VERSION) {
        return;
    }

    Tap_Manager::create_tap_status_table();
    Beer_Festival_Marketing_Popups::create_table();
    flush_rewrite_rules();

    // Backfill any taps with no zone to the protected "Default" category.
    global $wpdb;
    $table_name = $wpdb->prefix . 'tap_status';
    $wpdb->query("UPDATE $table_name SET category = 'Default' WHERE category IS NULL OR category = ''");

    update_option('beer_festival_db_version', BEER_FESTIVAL_VERSION);
}

// Uninstall cleanup lives in uninstall.php, which WordPress runs on its own.
