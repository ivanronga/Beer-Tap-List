<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_bftl_add_category', [$this, 'handle_add_category']);
        add_action('admin_post_bftl_rename_category', [$this, 'handle_rename_category']);
        add_action('admin_post_bftl_delete_category', [$this, 'handle_delete_category']);
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
            __('Tap Zones', 'beer-festival-tap'),
            __('Tap Zones', 'beer-festival-tap'),
            'manage_options',
            'beer-festival-tap-zones',
            [$this, 'render_tap_zones_page']
        );
        add_submenu_page(
            'beer-festival-settings',
            __('QR Codes', 'beer-festival-tap'),
            __('QR Codes', 'beer-festival-tap'),
            'manage_options',
            'beer-festival-qr-codes',
            [$this, 'render_qr_codes_page']
        );
        add_submenu_page(
            'beer-festival-settings',
            __('Categories', 'beer-festival-tap'),
            __('Categories', 'beer-festival-tap'),
            'manage_options',
            'beer-festival-categories',
            [$this, 'render_categories_page']
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
        if ($hook === 'beer-festival_page_beer-festival-qr-codes') {
            wp_enqueue_style('bftl-admin', plugins_url('../admin/css/admin-styles.css', __FILE__), [], BEER_FESTIVAL_VERSION);
            wp_enqueue_script('bftl-qrcode', plugins_url('../public/js/qrcode.min.js', __FILE__), [], '1.4.4', true);
            wp_enqueue_script('bftl-qr-codes', plugins_url('../admin/js/qr-codes.js', __FILE__), ['bftl-qrcode'], BEER_FESTIVAL_VERSION, true);
            return;
        }
        if ($hook === 'beer-festival_page_beer-festival-tap-zones') {
            wp_enqueue_style('bftl-admin', plugins_url('../admin/css/admin-styles.css', __FILE__), [], BEER_FESTIVAL_VERSION);
            wp_enqueue_script('bftl-tap-zones', plugins_url('../admin/js/tap-zones.js', __FILE__), ['jquery', 'wp-api-fetch'], BEER_FESTIVAL_VERSION, true);
            wp_localize_script('bftl-tap-zones', 'BFTL', [
                'category_rest_url' => rest_url('beer-festival-tap-list/v1/taps/category'),
                'nonce'             => wp_create_nonce('wp_rest')
            ]);
            return;
        }
        if ($hook !== 'beer-festival_page_beer-festival-tap-management') return;
        // Enqueue Select2, SweetAlert2, and your custom JS/CSS here
        wp_enqueue_style('select2', plugins_url('../admin/css/select2.min.css', __FILE__), [], '4.1.0-rc.0');
        wp_enqueue_script('select2', plugins_url('../admin/js/select2.min.js', __FILE__), ['jquery'], '4.1.0-rc.0', true);
        wp_enqueue_script('bftl-tap-management', plugins_url('../admin/js/tap-management.js', __FILE__), ['jquery', 'select2', 'wp-api-fetch'], BEER_FESTIVAL_VERSION, true);
        wp_localize_script('bftl-tap-management', 'BFTL', [
            'assign_rest_url'   => rest_url('beer-festival-tap-list/v1/taps/assign'),
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
        $tap_map = [];
        if (!is_wp_error($tap_assignments)) {
            foreach ($tap_assignments as $t) {
                $tap_map[intval($t->tap_id)] = $t;
            }
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Tap Management', 'beer-festival-tap'); ?></h1>
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
                        $current = isset($tap_map[$i]) ? $tap_map[$i] : null;
                    ?>
                    <tr data-tap="<?php echo $i; ?>">
                        <td><?php echo esc_html($i); ?></td>
                        <td>
                            <select class="bftl-select2 bftl-tap-beer" data-tap="<?php echo $i; ?>" style="width: 300px;">
                                <option value=""><?php _e('-- Empty --', 'beer-festival-tap'); ?></option>
                                <?php foreach ($beers as $beer):
                                    $label = esc_html($beer->post_title);
                                    $style = get_post_meta($beer->ID, '_beer_stil', true);
                                    $category = get_post_meta($beer->ID, '_beer_category', true);
                                    $brewer = get_post_meta($beer->ID, '_beer_brewer', true);
                                    $location = get_post_meta($beer->ID, '_beer_location', true);
                                    $ibu = get_post_meta($beer->ID, '_beer_ibu', true);
                                    $abv = get_post_meta($beer->ID, '_beer_abv', true);
                                    $details = implode(', ', array_filter([$label, $category, $style, $brewer, $location, "IBU: $ibu", "ABV: $abv%"], 'strlen'));
                                ?>
                                <option value="<?php echo $beer->ID; ?>" <?php selected($current && $current->beer_id == $beer->ID); ?>>
                                    <?php echo esc_html($details); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button bftl-save-tap" data-tap="<?php echo $i; ?>" disabled>
                                <?php _e('Save', 'beer-festival-tap'); ?>
                            </button>
                            <button type="button" class="button bftl-clear-tap" data-tap="<?php echo $i; ?>">
                                <?php _e('Clear', 'beer-festival-tap'); ?>
                            </button>
                            <span class="bftl-row-feedback" data-tap="<?php echo $i; ?>"></span>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_tap_zones_page() {
        $settings = get_option('beer_festival_settings', []);
        $tap_count = isset($settings['tap_count']) ? intval($settings['tap_count']) : 16;

        $tap_assignments = Tap_Manager::get_all_taps();
        $tap_map = [];
        if (!is_wp_error($tap_assignments)) {
            foreach ($tap_assignments as $t) {
                $tap_map[intval($t->tap_id)] = $t;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Tap Zones', 'beer-festival-tap'); ?></h1>
            <p><?php _e('Assign a fixed category/zone to each tap. This is normally set up once before the event and does not change as beers are swapped.', 'beer-festival-tap'); ?></p>
            <table class="widefat admin-table is-tap-manage">
                <thead>
                    <tr>
                        <th><?php _e('Tap #', 'beer-festival-tap'); ?></th>
                        <th><?php _e('Zone', 'beer-festival-tap'); ?></th>
                        <th><?php _e('Actions', 'beer-festival-tap'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= $tap_count; $i++):
                        $current = isset($tap_map[$i]) ? $tap_map[$i] : null;
                        $current_category = $current && $current->category ? $current->category : Beer_Festival_Categories::DEFAULT_CATEGORY;
                    ?>
                    <tr data-tap="<?php echo $i; ?>">
                        <td><?php echo esc_html($i); ?></td>
                        <td>
                            <select class="bftl-zone-select" data-tap="<?php echo $i; ?>" style="width: 200px;">
                                <?php foreach (Beer_Festival_Categories::get_all() as $category): ?>
                                <option value="<?php echo esc_attr($category); ?>" <?php selected($current_category, $category); ?>><?php echo esc_html($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button button-primary bftl-save-zone" data-tap="<?php echo $i; ?>">
                                <?php _e('Save', 'beer-festival-tap'); ?>
                            </button>
                            <span class="bftl-row-feedback" data-tap="<?php echo $i; ?>"></span>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * A beer's URL that stays valid no matter what permalink structure is
     * active later — WordPress always understands ?post_type=beer&p=ID and
     * redirects it to the current pretty URL (or serves it directly if
     * pretty permalinks are ever turned off), unlike a slug-based link.
     */
    private function get_stable_beer_url($beer_id) {
        return home_url('/?post_type=beer&p=' . intval($beer_id));
    }

    public function render_qr_codes_page() {
        $beers = get_posts([
            'post_type' => 'beer',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        ?>
        <div class="wrap bftl-qr-codes-page">
            <h1><?php _e('QR Codes', 'beer-festival-tap'); ?></h1>
            <p><?php _e('Print this page (Ctrl+P) to prepare QR codes for every beer ahead of the festival.', 'beer-festival-tap'); ?></p>
            <div class="bftl-qr-grid">
                <?php foreach ($beers as $beer): ?>
                <div class="bftl-qr-item" data-permalink="<?php echo esc_attr($this->get_stable_beer_url($beer->ID)); ?>">
                    <div class="bftl-qr-item-code"></div>
                    <p class="bftl-qr-item-name"><?php echo esc_html($beer->post_title); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function render_categories_page() {
        $categories = Beer_Festival_Categories::get_all();
        $notice = isset($_GET['bftl_notice']) ? sanitize_text_field($_GET['bftl_notice']) : '';
        $error = isset($_GET['bftl_error']) ? sanitize_text_field($_GET['bftl_error']) : '';
        ?>
        <div class="wrap">
            <h1><?php _e('Categories', 'beer-festival-tap'); ?></h1>

            <?php if ($notice): ?>
                <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <table class="widefat" style="max-width: 500px;">
                <thead>
                    <tr>
                        <th><?php _e('Category', 'beer-festival-tap'); ?></th>
                        <th><?php _e('Actions', 'beer-festival-tap'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo esc_html($category); ?></td>
                        <td>
                            <?php if ($category !== Beer_Festival_Categories::DEFAULT_CATEGORY): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right: 6px;">
                                <?php wp_nonce_field('bftl_rename_category'); ?>
                                <input type="hidden" name="action" value="bftl_rename_category">
                                <input type="hidden" name="old_name" value="<?php echo esc_attr($category); ?>">
                                <input type="text" name="new_name" value="<?php echo esc_attr($category); ?>" style="width: 140px;">
                                <button type="submit" class="button"><?php _e('Rename', 'beer-festival-tap'); ?></button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js(__('Delete this category?', 'beer-festival-tap')); ?>');">
                                <?php wp_nonce_field('bftl_delete_category'); ?>
                                <input type="hidden" name="action" value="bftl_delete_category">
                                <input type="hidden" name="name" value="<?php echo esc_attr($category); ?>">
                                <button type="submit" class="button button-link-delete"><?php _e('Delete', 'beer-festival-tap'); ?></button>
                            </form>
                            <?php else: ?>
                                <em><?php _e('Protected', 'beer-festival-tap'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php _e('Add New Category', 'beer-festival-tap'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bftl_add_category'); ?>
                <input type="hidden" name="action" value="bftl_add_category">
                <input type="text" name="name" placeholder="<?php esc_attr_e('e.g. Wheat Beer', 'beer-festival-tap'); ?>" required>
                <button type="submit" class="button button-primary"><?php _e('Add', 'beer-festival-tap'); ?></button>
            </form>
        </div>
        <?php
    }

    private function redirect_to_categories($notice = '', $error = '') {
        $args = ['page' => 'beer-festival-categories'];
        if ($notice) $args['bftl_notice'] = $notice;
        if ($error) $args['bftl_error'] = $error;
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handle_add_category() {
        check_admin_referer('bftl_add_category');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }
        $result = Beer_Festival_Categories::add($_POST['name'] ?? '');
        if (is_wp_error($result)) {
            $this->redirect_to_categories('', $result->get_error_message());
        }
        $this->redirect_to_categories(__('Category added.', 'beer-festival-tap'));
    }

    public function handle_rename_category() {
        check_admin_referer('bftl_rename_category');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }
        $result = Beer_Festival_Categories::rename($_POST['old_name'] ?? '', $_POST['new_name'] ?? '');
        if (is_wp_error($result)) {
            $this->redirect_to_categories('', $result->get_error_message());
        }
        $this->redirect_to_categories(__('Category renamed.', 'beer-festival-tap'));
    }

    public function handle_delete_category() {
        check_admin_referer('bftl_delete_category');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }
        $result = Beer_Festival_Categories::delete($_POST['name'] ?? '');
        if (is_wp_error($result)) {
            $this->redirect_to_categories('', $result->get_error_message());
        }
        $this->redirect_to_categories(__('Category deleted.', 'beer-festival-tap'));
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

    public function assign_single_tap($tap_id, $beer_id, $user_id) {
        if ($beer_id) {
            Tap_Manager::assign_beer_to_tap($tap_id, $beer_id, $user_id);
        } else {
            Tap_Manager::clear_tap($tap_id, $user_id);
        }
        return ['message' => __('Tap updated.', 'beer-festival-tap')];
    }

    public function set_tap_category($tap_id, $category) {
        Tap_Manager::set_tap_category($tap_id, $category ?: Beer_Festival_Categories::DEFAULT_CATEGORY);
        return ['message' => __('Tap category updated.', 'beer-festival-tap')];
    }

    


}


