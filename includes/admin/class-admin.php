<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_bftl_add_category', [$this, 'handle_add_category']);
        add_action('admin_post_bftl_rename_category', [$this, 'handle_rename_category']);
        add_action('admin_post_bftl_delete_category', [$this, 'handle_delete_category']);
        add_action('admin_post_bftl_export_csv', [$this, 'handle_export_csv']);
        add_action('admin_post_bftl_import_upload', [$this, 'handle_import_upload']);
        add_action('admin_post_bftl_import_process', [$this, 'handle_import_process']);
        add_action('admin_post_bftl_delete_all_beers', [$this, 'handle_delete_all_beers']);
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
            __('Import/Export', 'beer-festival-tap'),
            __('Import/Export', 'beer-festival-tap'),
            'manage_options',
            'beer-festival-import-export',
            [$this, 'render_import_export_page']
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
        if ($hook === 'beer-festival_page_beer-festival-import-export') {
            wp_enqueue_style('bftl-admin', plugins_url('../admin/css/admin-styles.css', __FILE__), [], BEER_FESTIVAL_VERSION);
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
            <table class="admin-table is-tap-manage">
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
            <table class="admin-table is-tap-manage">
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
    public static function get_stable_beer_url($beer_id) {
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
            <p>
                <input type="search" id="bftl-qr-search" placeholder="<?php esc_attr_e('Search by beer name…', 'beer-festival-tap'); ?>" style="width: 300px;">
                <select id="bftl-qr-category-filter">
                    <option value=""><?php _e('All categories', 'beer-festival-tap'); ?></option>
                    <?php foreach (Beer_Festival_Categories::get_all() as $filter_category): ?>
                    <option value="<?php echo esc_attr($filter_category); ?>"><?php echo esc_html($filter_category); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <div class="bftl-qr-grid">
                <?php foreach ($beers as $beer):
                    $brewer = get_post_meta($beer->ID, '_beer_brewer', true);
                    $category = get_post_meta($beer->ID, '_beer_category', true);
                    $style = get_post_meta($beer->ID, '_beer_stil', true);
                    $location = get_post_meta($beer->ID, '_beer_location', true);
                    $ibu = get_post_meta($beer->ID, '_beer_ibu', true);
                    $abv = get_post_meta($beer->ID, '_beer_abv', true);

                    $brewer_line = $brewer;
                    if ($location) {
                        $brewer_line = $brewer ? ($brewer . ' • ' . $location) : $location;
                    }

                    $stats_parts = [];
                    if ($style) $stats_parts[] = $style;
                    if ($ibu !== '') $stats_parts[] = 'IBU: ' . $ibu;
                    if ($abv !== '') $stats_parts[] = 'ABV: ' . $abv . '%';
                    $stats_line = implode(' • ', $stats_parts);
                ?>
                <div class="bftl-qr-item" data-permalink="<?php echo esc_attr($this->get_stable_beer_url($beer->ID)); ?>" data-brewer="<?php echo esc_attr($brewer); ?>" data-category="<?php echo esc_attr($category); ?>">
                    <?php if ($category): ?>
                    <p class="bftl-qr-item-category"><?php echo esc_html($category); ?></p>
                    <?php endif; ?>
                    <div class="bftl-qr-item-code bftl-qr-item-code-clickable" role="button" tabindex="0" aria-label="<?php esc_attr_e('Preview QR code', 'beer-festival-tap'); ?>"></div>
                    <p class="bftl-qr-item-name"><?php echo esc_html($beer->post_title); ?></p>
                    <?php if ($brewer_line): ?>
                    <p class="bftl-qr-item-brewer"><?php echo esc_html($brewer_line); ?></p>
                    <?php endif; ?>
                    <?php if ($stats_line): ?>
                    <p class="bftl-qr-item-stats"><?php echo esc_html($stats_line); ?></p>
                    <?php endif; ?>
                    <div class="bftl-qr-item-actions">
                        <button type="button" class="button bftl-qr-download"><?php _e('Download', 'beer-festival-tap'); ?></button>
                        <button type="button" class="button bftl-qr-print"><?php _e('Print', 'beer-festival-tap'); ?></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="bftl-qr-modal" class="bftl-qr-modal">
                <div class="bftl-qr-modal-backdrop"></div>
                <div class="bftl-qr-modal-content">
                    <button type="button" class="bftl-qr-modal-close" aria-label="<?php esc_attr_e('Close', 'beer-festival-tap'); ?>">&times;</button>
                    <p class="bftl-qr-modal-category"></p>
                    <div class="bftl-qr-modal-code"></div>
                    <p class="bftl-qr-modal-name"></p>
                    <p class="bftl-qr-modal-brewer"></p>
                </div>
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

            <table class="widefat" style="max-width: 600px;">
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
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex; align-items:center; gap: 6px;">
                                    <?php wp_nonce_field('bftl_rename_category'); ?>
                                    <input type="hidden" name="action" value="bftl_rename_category">
                                    <input type="hidden" name="old_name" value="<?php echo esc_attr($category); ?>">
                                    <input type="text" name="new_name" value="<?php echo esc_attr($category); ?>" style="width: 140px;">
                                    <button type="submit" class="button"><?php _e('Rename', 'beer-festival-tap'); ?></button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this category?', 'beer-festival-tap')); ?>');">
                                    <?php wp_nonce_field('bftl_delete_category'); ?>
                                    <input type="hidden" name="action" value="bftl_delete_category">
                                    <input type="hidden" name="name" value="<?php echo esc_attr($category); ?>">
                                    <button type="submit" class="button button-link-delete"><?php _e('Delete', 'beer-festival-tap'); ?></button>
                                </form>
                            </div>
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

    public function render_import_export_page() {
        $step = isset($_GET['step']) ? sanitize_key($_GET['step']) : '';
        $notice = isset($_GET['bftl_notice']) ? sanitize_text_field($_GET['bftl_notice']) : '';
        $error = isset($_GET['bftl_error']) ? sanitize_text_field($_GET['bftl_error']) : '';
        ?>
        <div class="wrap">
            <h1><?php _e('Import / Export Beers', 'beer-festival-tap'); ?></h1>

            <?php if ($notice): ?>
                <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php
            if ($step === 'map') {
                $this->render_import_mapping_step();
            } elseif ($step === 'results') {
                $this->render_import_results_step();
            } else {
                $this->render_import_export_initial_step();
            }
            ?>
        </div>
        <?php
    }

    private function render_import_export_initial_step() {
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=bftl_export_csv'), 'bftl_export_csv');
        ?>
        <h2><?php _e('Export', 'beer-festival-tap'); ?></h2>
        <p><?php _e('Download all published beers as a spreadsheet: Beer Name, Style, Category, Brewer, Location, ABV, IBU, and a QR reference link.', 'beer-festival-tap'); ?></p>
        <p>
            <a href="<?php echo esc_url($export_url); ?>" class="button button-primary"><?php _e('Export Beers', 'beer-festival-tap'); ?></a>
        </p>
        <p class="description"><?php _e('The file downloads with an .xls extension for convenience, but its content is plain CSV — Excel may show a one-time "format doesn\'t match extension" warning on open; choosing "Yes" opens it correctly.', 'beer-festival-tap'); ?></p>

        <hr>

        <h2><?php _e('Import', 'beer-festival-tap'); ?></h2>
        <p><?php _e('Upload a spreadsheet to bulk-create or update beers. You\'ll map its columns to the right fields before anything is imported.', 'beer-festival-tap'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('bftl_import_upload'); ?>
            <input type="hidden" name="action" value="bftl_import_upload">
            <p><input type="file" name="import_file" accept=".csv,.xls,.xlsx" required></p>
            <p><button type="submit" class="button button-primary"><?php _e('Upload &amp; Continue', 'beer-festival-tap'); ?></button></p>
        </form>

        <hr>

        <?php $this->render_delete_all_beers_section(); ?>
        <?php
    }

    private function render_delete_all_beers_section() {
        $beer_count = wp_count_posts('beer')->publish;
        $confirm_message = sprintf(
            /* translators: %d: number of beers */
            __('Delete all %d beer(s)? They will be moved to Trash and every tap will be cleared. This cannot be undone from this screen.', 'beer-festival-tap'),
            $beer_count
        );
        ?>
        <h2 style="color: #a00;"><?php _e('Delete All Beers', 'beer-festival-tap'); ?></h2>
        <p><?php _e('Moves every beer to the Trash and clears every tap currently pouring one. Beers can be restored from the Trash afterward if this was a mistake.', 'beer-festival-tap'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($confirm_message); ?>');">
            <?php wp_nonce_field('bftl_delete_all_beers'); ?>
            <input type="hidden" name="action" value="bftl_delete_all_beers">
            <p><button type="submit" class="button bftl-button-danger"><?php _e('Delete All Beers', 'beer-festival-tap'); ?></button></p>
        </form>
        <?php
    }

    private function render_import_mapping_step() {
        $import_id = isset($_GET['import_id']) ? sanitize_key($_GET['import_id']) : '';
        $data = $import_id ? get_transient('bftl_import_data_' . $import_id) : false;

        if (!$data) {
            echo '<div class="notice notice-error"><p>' . esc_html__('This upload has expired. Please upload the file again.', 'beer-festival-tap') . '</p></div>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=beer-festival-import-export')) . '" class="button">' . esc_html__('Back', 'beer-festival-tap') . '</a></p>';
            return;
        }

        $headers = $data['headers'];
        $mapping = Beer_Festival_Import_Export::guess_column_mapping($headers);
        $row_count = count($data['rows']);

        $fields = [
            'beer_name' => __('Beer Name', 'beer-festival-tap'),
            'stil'      => __('Style', 'beer-festival-tap'),
            'category'  => __('Category', 'beer-festival-tap'),
            'brewer'    => __('Brewer', 'beer-festival-tap'),
            'location'  => __('Location', 'beer-festival-tap'),
            'abv'       => __('ABV', 'beer-festival-tap'),
            'ibu'       => __('IBU', 'beer-festival-tap'),
        ];
        ?>
        <h2><?php _e('Map columns', 'beer-festival-tap'); ?></h2>
        <p><?php printf(esc_html__('Found %d data row(s) in the uploaded file. Choose which uploaded column maps to each field below.', 'beer-festival-tap'), $row_count); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('bftl_import_process'); ?>
            <input type="hidden" name="action" value="bftl_import_process">
            <input type="hidden" name="import_id" value="<?php echo esc_attr($import_id); ?>">

            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th><?php _e('Field', 'beer-festival-tap'); ?></th>
                        <th><?php _e('Uploaded column', 'beer-festival-tap'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field_key => $field_label):
                        $required = $field_key === 'beer_name';
                        $selected_index = $mapping[$field_key];
                    ?>
                    <tr>
                        <td><?php echo esc_html($field_label); ?><?php echo $required ? ' <span style="color:#c00;">*</span>' : ''; ?></td>
                        <td>
                            <select name="mapping[<?php echo esc_attr($field_key); ?>]" <?php echo $required ? 'required' : ''; ?>>
                                <?php if ($required && $selected_index === ''): ?>
                                <option value="" disabled selected><?php _e('-- Select --', 'beer-festival-tap'); ?></option>
                                <?php elseif (!$required): ?>
                                <option value="" <?php selected($selected_index, ''); ?>><?php _e('-- Do not import --', 'beer-festival-tap'); ?></option>
                                <?php endif; ?>
                                <?php foreach ($headers as $index => $header): ?>
                                <option value="<?php echo esc_attr($index); ?>" <?php selected($selected_index, $index); ?>><?php echo esc_html($header); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description"><?php _e('A Category value that doesn\'t already exist will be created automatically. Existing beers are matched by exact Beer Name (case-insensitive) — a new name creates a new beer.', 'beer-festival-tap'); ?></p>

            <p>
                <button type="submit" class="button button-primary"><?php _e('Import', 'beer-festival-tap'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=beer-festival-import-export')); ?>" class="button"><?php _e('Cancel', 'beer-festival-tap'); ?></a>
            </p>
        </form>
        <?php
    }

    private function render_import_results_step() {
        $import_id = isset($_GET['import_id']) ? sanitize_key($_GET['import_id']) : '';
        $results = $import_id ? get_transient('bftl_import_results_' . $import_id) : false;

        if (!$results) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Import results are no longer available (they expire after an hour), but the import itself already ran.', 'beer-festival-tap') . '</p></div>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=beer-festival-import-export')) . '" class="button">' . esc_html__('Back', 'beer-festival-tap') . '</a></p>';
            return;
        }

        $counts = ['created' => 0, 'updated' => 0, 'error' => 0];
        foreach ($results as $result) {
            if (isset($counts[$result['action']])) {
                $counts[$result['action']]++;
            }
        }
        ?>
        <h2><?php _e('Import results', 'beer-festival-tap'); ?></h2>
        <p>
            <?php printf(
                esc_html__('%1$d created, %2$d updated, %3$d error(s).', 'beer-festival-tap'),
                $counts['created'], $counts['updated'], $counts['error']
            ); ?>
        </p>
        <table class="widefat" style="max-width: 800px;">
            <thead>
                <tr>
                    <th><?php _e('Row', 'beer-festival-tap'); ?></th>
                    <th><?php _e('Beer', 'beer-festival-tap'); ?></th>
                    <th><?php _e('Result', 'beer-festival-tap'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result):
                    $feedback_class = $result['action'] === 'error' ? 'bftl-feedback-error' : 'bftl-feedback-success';
                ?>
                <tr>
                    <td><?php echo esc_html($result['row']); ?></td>
                    <td><?php echo esc_html($result['beer_name'] !== '' ? $result['beer_name'] : '—'); ?></td>
                    <td><span class="bftl-row-feedback <?php echo esc_attr($feedback_class); ?>"><?php echo esc_html($result['message']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 16px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=beer-festival-import-export')); ?>" class="button"><?php _e('Import another file', 'beer-festival-tap'); ?></a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=beer')); ?>" class="button button-primary"><?php _e('View All Beers', 'beer-festival-tap'); ?></a>
        </p>
        <?php
    }

    private function redirect_to_import_export($notice = '', $error = '') {
        $args = ['page' => 'beer-festival-import-export'];
        if ($notice) $args['bftl_notice'] = $notice;
        if ($error) $args['bftl_error'] = $error;
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handle_export_csv() {
        check_admin_referer('bftl_export_csv');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }
        Beer_Festival_Import_Export::stream_csv_export();
    }

    public function handle_import_upload() {
        check_admin_referer('bftl_import_upload');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }

        if (empty($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'] ?? '')) {
            $this->redirect_to_import_export('', __('Please choose a file to upload.', 'beer-festival-tap'));
        }

        $file = $_FILES['import_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->redirect_to_import_export('', __('Upload failed, please try again.', 'beer-festival-tap'));
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $this->redirect_to_import_export('', __('File is too large (max 5MB).', 'beer-festival-tap'));
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            $this->redirect_to_import_export('', __('Please upload a .csv or .xls file.', 'beer-festival-tap'));
        }

        $parsed = Beer_Festival_Import_Export::parse_csv_file($file['tmp_name']);
        if (is_wp_error($parsed)) {
            $this->redirect_to_import_export('', $parsed->get_error_message());
        }
        if (empty($parsed['rows'])) {
            $this->redirect_to_import_export('', __('No data rows found in that file.', 'beer-festival-tap'));
        }

        $import_id = bin2hex(random_bytes(8));
        set_transient('bftl_import_data_' . $import_id, $parsed, 15 * MINUTE_IN_SECONDS);

        wp_safe_redirect(add_query_arg([
            'page'      => 'beer-festival-import-export',
            'step'      => 'map',
            'import_id' => $import_id,
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_import_process() {
        check_admin_referer('bftl_import_process');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }

        $import_id = isset($_POST['import_id']) ? sanitize_key($_POST['import_id']) : '';
        $data = $import_id ? get_transient('bftl_import_data_' . $import_id) : false;
        if (!$data) {
            wp_safe_redirect(add_query_arg([
                'page'       => 'beer-festival-import-export',
                'bftl_error' => __('This upload expired, please try again.', 'beer-festival-tap'),
            ], admin_url('admin.php')));
            exit;
        }

        $mapping = isset($_POST['mapping']) && is_array($_POST['mapping']) ? wp_unslash($_POST['mapping']) : [];
        $mapping = array_map(function ($value) {
            return $value === '' ? '' : intval($value);
        }, $mapping);

        $title_map = Beer_Festival_Import_Export::build_title_map();
        $results = [];
        $row_number = 1; // header row is 1
        foreach ($data['rows'] as $row) {
            $row_number++;
            $results[] = Beer_Festival_Import_Export::import_row($mapping, $row, $title_map, $row_number);
        }

        delete_transient('bftl_import_data_' . $import_id);
        set_transient('bftl_import_results_' . $import_id, $results, HOUR_IN_SECONDS);

        wp_safe_redirect(add_query_arg([
            'page'      => 'beer-festival-import-export',
            'step'      => 'results',
            'import_id' => $import_id,
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_delete_all_beers() {
        check_admin_referer('bftl_delete_all_beers');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'beer-festival-tap'));
        }

        // Clear every tap first so nothing is left pointing at a beer that's about to be trashed.
        $taps = Tap_Manager::get_all_taps();
        if (!is_wp_error($taps)) {
            foreach ($taps as $tap) {
                if ($tap->beer_id) {
                    Tap_Manager::clear_tap(intval($tap->tap_id), get_current_user_id());
                }
            }
        }

        $beer_ids = get_posts([
            'post_type'   => 'beer',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);

        $deleted = 0;
        foreach ($beer_ids as $beer_id) {
            if (wp_delete_post($beer_id)) { // no force = moves to Trash
                $deleted++;
            }
        }

        $this->redirect_to_import_export(sprintf(
            /* translators: %d: number of beers deleted */
            __('%d beer(s) moved to Trash. All taps were cleared.', 'beer-festival-tap'),
            $deleted
        ));
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


