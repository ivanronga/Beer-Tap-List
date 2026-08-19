<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Beer Festival', 'beer-festival-tap'),
            __('Beer Festival', 'beer-festival-tap'),
            'manage_options',
            'beer-festival-settings',
            [$this, 'render_main_page'],
            'dashicons-beer'
        );
        add_submenu_page(
            'beer-festival-settings',
            __('Tap Management', 'beer-festival-tap'),
            __('Tap Management', 'beer-festival-tap'),
            'manage_options',
            'beer-festival-tap-management',
            [$this, 'render_tap_management_page']
        );
        add_submenu_page(
            'beer-festival-settings',
            __('All Beers', 'beer-festival-tap'),
            __('All Beers', 'beer-festival-tap'),
            'manage_options',
            'edit.php?post_type=beer'
        );
        
        add_submenu_page(
            'beer-festival-settings',
            __('Add New Beer', 'beer-festival-tap'),
            __('Add New Beer', 'beer-festival-tap'),
            'manage_options',
            'post-new.php?post_type=beer'
        );
  
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'beer-festival_page_beer-festival-tap-management') return;
        // Enqueue Select2, SweetAlert2, and your custom JS/CSS here
        wp_enqueue_style('select2', plugins_url('../admin/css/select2.min.css', __FILE__), [], '4.1.0-rc.0');
        wp_enqueue_script('select2', plugins_url('../admin/js/select2.min.js', __FILE__), ['jquery'], '4.1.0-rc.0', true);
        wp_enqueue_script('bftl-tap-management', plugins_url('../admin/js/tap-management.js', __FILE__), ['jquery', 'select2', 'wp-api-fetch'], BEER_FESTIVAL_VERSION, true);
        wp_localize_script('bftl-tap-management', 'BFTL', [
            'taps_rest_url'     => rest_url('beer-festival-tap-list/v1/taps'),
            'activity_rest_url' => rest_url('beer-festival-tap-list/v1/activity'),
            'nonce'             => wp_create_nonce('wp_rest')
        ]);
        wp_enqueue_style('bftl-admin', plugins_url('../admin/css/admin-styles.css', __FILE__), [], BEER_FESTIVAL_VERSION);
        wp_enqueue_script('sweetalert2', plugins_url('../admin/js/sweetalert2.min.js', __FILE__), [], '11.26.25', true);
        wp_enqueue_script('bftl-concurrent-edit', plugins_url('../admin/js/concurrent-edit.js', __FILE__), ['jquery', 'sweetalert2', 'wp-api-fetch'], BEER_FESTIVAL_VERSION, true);

    }

    public function render_tap_management_page() {
        $settings = get_option('beer_festival_settings', []);
        $tap_count = isset($settings['tap_count']) ? intval($settings['tap_count']) : 16;
        
        $beers = get_posts([
            'post_type' => 'beer',
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        $tap_assignments = Tap_Manager::get_all_taps();

        ?>
        <div class="wrap">
            <h1><?php _e('Tap Management', 'beer-festival-tap'); ?></h1>
            <form id="bftl-tap-form">
                <table class="widefat admin-table is-tap-manage">
                    <thead>
                        <tr>
                            <th><?php _e('Tap #', 'beer-festival-tap'); ?></th>
                            <th><?php _e('Beer', 'beer-festival-tap'); ?></th>
                            <th><?php _e('Actions', 'beer-festival-tap'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= $tap_count; $i++): 
                            $current = isset($tap_assignments[$i-1]) ? $tap_assignments[$i-1] : null;
                        ?>
                        <tr>
                            <td><?php echo esc_html($i); ?></td>
                            <td>
                                <select name="tap_beer[<?php echo $i; ?>]" class="bftl-select2" style="width: 300px;">
                                    <option value=""><?php _e('-- Empty --', 'beer-festival-tap'); ?></option>
                                    <?php foreach ($beers as $beer):
                                        $label = esc_html($beer->post_title);
                                        $style = get_post_meta($beer->ID, '_beer_stil', true);
                                        $brewer = get_post_meta($beer->ID, '_beer_brewer', true);
                                        $location = get_post_meta($beer->ID, '_beer_location', true);
                                        $ibu = get_post_meta($beer->ID, '_beer_ibu', true);
                                        $abv = get_post_meta($beer->ID, '_beer_abv', true);
                                        $details = "$label, $style, $brewer, $location, IBU: $ibu, ABV: $abv%";
                                    ?>
                                    <option value="<?php echo $beer->ID; ?>" <?php selected($current && $current->beer_id == $beer->ID); ?>>
                                        <?php echo esc_html($details); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button bftl-clear-tap" data-tap="<?php echo $i; ?>">
                                    <?php _e('Clear', 'beer-festival-tap'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php _e('Save All Changes', 'beer-festival-tap'); ?></button>
                </p>
            </form>
            <div id="bftl-admin-feedback"></div>
        </div>
        <?php
    }

    public function render_main_page() {
        // Get the settings instance and render the settings page
        global $beer_festival_settings;
        if ($beer_festival_settings) {
            $beer_festival_settings->render_settings_page();
        } else {
            // Fallback if settings instance is not available
            echo '<div class="wrap">';
            echo '<h1>' . __('Beer Festival', 'beer-festival-tap') . '</h1>';
            echo '<p>' . __('Welcome to Beer Festival Tap List management.', 'beer-festival-tap') . '</p>';
            echo '<p><a href="' . admin_url('admin.php?page=beer-festival-settings') . '" class="button button-primary">' . __('Go to Settings', 'beer-festival-tap') . '</a></p>';
            echo '</div>';
        }
    }

    public function save_taps_data($tap_data, $user_id) {
        $tap_data = is_array($tap_data) ? $tap_data : [];
        foreach ($tap_data as $tap_num => $beer_id) {
            if ($beer_id) {
                Tap_Manager::assign_beer_to_tap($tap_num, intval($beer_id), $user_id);
            } else {
                Tap_Manager::clear_tap($tap_num, $user_id);
            }
        }
        return ['message' => __('Tap assignments updated.', 'beer-festival-tap')];
    }

    


}


