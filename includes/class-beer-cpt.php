<?php
if (!defined('ABSPATH')) exit;

class Beer_CPT {

    public function __construct() {
        add_action('init', [$this, 'register_beer_cpt']);
        // add_action('init', [$this, 'register_beer_style_taxonomy']);
        add_action('add_meta_boxes', [$this, 'add_beer_meta_boxes']);
        add_action('save_post', [$this, 'save_beer_meta_fields']);
        add_filter('parent_file', [$this, 'set_admin_menu_parent']);
        add_filter('submenu_file', [$this, 'set_admin_menu_submenu']);
    }

    // Register the 'beer' custom post type
    public function register_beer_cpt() {
        $labels = [
            'name'               => __('Beers', 'beer-festival-tap'),
            'singular_name'      => __('Beer', 'beer-festival-tap'),
            'menu_name'          => __('Beers', 'beer-festival-tap'),
            'all_items'          => __('All Beers', 'beer-festival-tap'),
            'add_new'            => __('Add New', 'beer-festival-tap'),
            'add_new_item'       => __('Add New Beer', 'beer-festival-tap'),
            'edit_item'          => __('Edit Beer', 'beer-festival-tap'),
            'new_item'           => __('New Beer', 'beer-festival-tap'),
            'view_item'          => __('View Beer', 'beer-festival-tap'),
            'search_items'       => __('Search Beers', 'beer-festival-tap'),
            'not_found'          => __('No beers found', 'beer-festival-tap'),
            'not_found_in_trash' => __('No beers found in Trash', 'beer-festival-tap'),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll handle this manually in admin
            'supports'           => ['title'],
            'has_archive'        => false,
            'rewrite'            => ['slug' => 'beer', 'with_front' => false],
            'capability_type'    => 'post',
            'menu_icon'          => 'dashicons-beer',
        ];
        register_post_type('beer', $args);
    }

    // Register the 'beer_style' taxonomy
    // public function register_beer_style_taxonomy() {
    //     $labels = [
    //         'name'              => __('Beer Styles', 'beer-festival-tap'),
    //         'singular_name'     => __('Beer Style', 'beer-festival-tap'),
    //         'search_items'      => __('Search Beer Styles', 'beer-festival-tap'),
    //         'all_items'         => __('All Beer Styles', 'beer-festival-tap'),
    //         'edit_item'         => __('Edit Beer Style', 'beer-festival-tap'),
    //         'update_item'       => __('Update Beer Style', 'beer-festival-tap'),
    //         'add_new_item'      => __('Add New Beer Style', 'beer-festival-tap'),
    //         'new_item_name'     => __('New Beer Style Name', 'beer-festival-tap'),
    //         'menu_name'         => __('Beer Styles', 'beer-festival-tap'),
    //     ];
    //     $args = [
    //         'hierarchical'      => true,
    //         'labels'            => $labels,
    //         'show_ui'           => true,
    //         'show_admin_column' => true,
    //         'rewrite'           => false,
    //     ];
    //     register_taxonomy('beer_style', ['beer'], $args);
    // }

    // Add meta boxes for custom fields
    public function add_beer_meta_boxes() {
        add_meta_box(
            'beer_details',
            __('Beer Details', 'beer-festival-tap'),
            [$this, 'render_beer_details_meta_box'],
            'beer',
            'normal',
            'high'
        );
    }

    // Render meta box fields
    public function render_beer_details_meta_box($post) {
        wp_nonce_field('save_beer_meta_fields', 'beer_meta_nonce');
        $fields = [
            'stil'      => get_post_meta($post->ID, '_beer_stil', true),
            'brewer'    => get_post_meta($post->ID, '_beer_brewer', true),
            'location'  => get_post_meta($post->ID, '_beer_location', true),
            'ibu'       => get_post_meta($post->ID, '_beer_ibu', true),
            'abv'       => get_post_meta($post->ID, '_beer_abv', true),
            'category'  => get_post_meta($post->ID, '_beer_category', true),
        ];
        ?>
        <p>
            <label><?php _e('Category:', 'beer-festival-tap'); ?></label><br>
            <select name="beer_category" required style="width:100%;">
                <option value="" <?php selected($fields['category'], ''); ?>><?php _e('-- Select --', 'beer-festival-tap'); ?></option>
                <?php foreach (Beer_Festival_Categories::get_selectable(false) as $category): ?>
                <option value="<?php echo esc_attr($category); ?>" <?php selected($fields['category'], $category); ?>><?php echo esc_html($category); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><?php _e('Beer style:', 'beer-festival-tap'); ?></label><br>
            <input type="text" name="beer_stil" value="<?php echo esc_attr($fields['stil']); ?>" style="width:100%;">
        </p>
        <p>
            <label><?php _e('Brewer:', 'beer-festival-tap'); ?></label><br>
            <input type="text" name="beer_brewer" value="<?php echo esc_attr($fields['brewer']); ?>" style="width:100%;">
        </p>
        <p>
            <label><?php _e('Brewer Location:', 'beer-festival-tap'); ?></label><br>
            <input type="text" name="beer_location" value="<?php echo esc_attr($fields['location']); ?>" style="width:100%;">
        </p>
        <p>
            <label><?php _e('IBU:', 'beer-festival-tap'); ?></label><br>
            <input type="number" name="beer_ibu" value="<?php echo esc_attr($fields['ibu']); ?>" min="0" step="1" style="width:100%;">
        </p>
        <p>
            <label><?php _e('ABV (%):', 'beer-festival-tap'); ?></label><br>
            <input type="number" 
                name="beer_abv" 
                value="<?php echo esc_attr($fields['abv']); ?>" 
                min="0" max="100" step="0.1" 
                style="width:100%;" 
                inputmode="decimal" 
                autocomplete="off">
        </p>
        <?php
    }

    // Save custom field values
    public function save_beer_meta_fields($post_id) {
        if (!isset($_POST['beer_meta_nonce']) || !wp_verify_nonce($_POST['beer_meta_nonce'], 'save_beer_meta_fields')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ('beer' !== get_post_type($post_id)) {
            return;
        }
        update_post_meta($post_id, '_beer_stil', sanitize_text_field($_POST['beer_stil'] ?? ''));
        update_post_meta($post_id, '_beer_brewer', sanitize_text_field($_POST['beer_brewer'] ?? ''));
        update_post_meta($post_id, '_beer_location', sanitize_text_field($_POST['beer_location'] ?? ''));
        update_post_meta($post_id, '_beer_ibu', intval($_POST['beer_ibu'] ?? 0));
        update_post_meta($post_id, '_beer_abv', floatval($_POST['beer_abv'] ?? 0));

        $category = sanitize_text_field($_POST['beer_category'] ?? '');
        if (!in_array($category, Beer_Festival_Categories::get_selectable(false), true)) {
            $category = '';
        }
        update_post_meta($post_id, '_beer_category', $category);
    }

    // Ensure CPT appears under custom admin menu
    public function set_admin_menu_parent($parent_file) {
        global $typenow;
        if ($typenow == 'beer') {
            return 'beer-festival-settings';
        }
        return $parent_file;
    }
    public function set_admin_menu_submenu($submenu_file) {
        global $typenow;
        if ($typenow == 'beer') {
            if (isset($_GET['action']) && $_GET['action'] == 'add') {
                return 'post-new.php?post_type=beer';
            }
            return 'edit.php?post_type=beer';
        }
        return $submenu_file;
    }
}

new Beer_CPT();
