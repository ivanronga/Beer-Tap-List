<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Public {

    public function __construct() {
        add_shortcode('beer_tap_list', [$this, 'render_tap_list']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_filter('single_template', [$this, 'load_single_beer_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_single_beer_assets']);
    }

    public function load_single_beer_template($template) {
        if (is_singular('beer')) {
            $plugin_template = BEER_FESTIVAL_PLUGIN_DIR . 'includes/public/templates/single-beer.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function enqueue_single_beer_assets() {
        if (!is_singular('beer')) {
            return;
        }

        wp_enqueue_style(
            'bftl-beer-single-styles',
            $this->get_asset_url('css/beer-single.css'),
            [],
            BEER_FESTIVAL_VERSION
        );

        wp_enqueue_script(
            'bftl-beer-single',
            $this->get_asset_url('js/beer-single.js'),
            ['jquery'],
            BEER_FESTIVAL_VERSION,
            true
        );

        wp_localize_script(
            'bftl-beer-single',
            'BeerSingle',
            [
                'beer_id'         => get_queried_object_id(),
                'assign_rest_url' => rest_url('beer-festival-tap-list/v1/taps/assign'),
            ]
        );
    }

    private function get_asset_url($relative_path) {
        return plugins_url('includes/public/' . $relative_path, BEER_FESTIVAL_PLUGIN_DIR . 'beer-festival-tap-list.php');
    }

    public function enqueue_public_assets() {
        $settings = get_option('beer_festival_settings', []);
        $refresh_interval = isset($settings['refresh_interval']) ? intval($settings['refresh_interval']) : 30;
        $new_duration = isset($settings['new_beer_duration']) ? intval($settings['new_beer_duration']) : 60;

        wp_enqueue_style(
            'bftl-tap-list-styles',
            $this->get_asset_url('css/tap-list-styles.css'),
            [],
            BEER_FESTIVAL_VERSION
        );

        wp_enqueue_script(
            'bftl-tap-list-script',
            $this->get_asset_url('js/tap-list-display.js'),
            array('jquery'),
            BEER_FESTIVAL_VERSION,
            true
        );

        wp_localize_script(
            'bftl-tap-list-script',
            'BFTLFront',
            array(
                'rest_url' => rest_url('beer-festival-tap-list/v1/taps'),
                'refresh_interval' => $refresh_interval,
                'new_duration' => $new_duration,
                'css_url' => $this->get_asset_url('css/tap-list-styles.css')
            )
        );
    }

    private function get_refresh_interval() {
        $settings = get_option('beer_festival_settings', []);
$new_duration = isset($settings['new_beer_duration']) ? intval($settings['new_beer_duration']) : 5; // Default: 5 seconds
    }

    public function render_tap_list($atts) {
        $settings = get_option('beer_festival_settings', []);
        $num_taps = isset($settings['tap_count']) ? intval($settings['tap_count']) : 16;
        $new_duration = intval($settings['new_beer_duration'] ?? 5);
        $wrapper = isset($settings['frontend_wrapper']) ? $settings['frontend_wrapper'] : 'div';
    
        // Get all tap assignments, indexed by tap_id
        $taps = Tap_Manager::get_all_taps();
        if (is_wp_error($taps)) {
            error_log('Tap Manager Error: ' . $taps->get_error_message());
            return '<div class="error">Error loading tap list. Please try again later.</div>';
        }
        
        $tap_map = [];
        foreach ($taps as $tap) {
            $tap_map[intval($tap->tap_id)] = $tap;
        }
    
        // Preload all beers assigned to taps
        $beer_ids = [];
        foreach ($tap_map as $tap) {
            if ($tap->beer_id) $beer_ids[] = intval($tap->beer_id);
        }
        
        $beers = [];
        if ($beer_ids) {
            $beer_posts = get_posts([
                'post_type' => 'beer',
                'post__in' => $beer_ids,
                'numberposts' => -1
            ]);
            
            if (is_wp_error($beer_posts)) {
                error_log('Beer Posts Error: ' . $beer_posts->get_error_message());
                return '<div class="error">Error loading beer data.</div>';
            }
            
            foreach ($beer_posts as $beer) {
                $beers[$beer->ID] = $beer;
            }
        }
    
        ob_start();
        $tap_list_html = '<div class="bftl-tap-list" style="--tap-rows: ' . intval($num_taps) . ';">';
        
        for ($tap_id = 1; $tap_id <= $num_taps; $tap_id++) {
            $tap = isset($tap_map[$tap_id]) ? $tap_map[$tap_id] : null;
            $beer = ($tap && $tap->beer_id && isset($beers[$tap->beer_id])) ? $beers[$tap->beer_id] : null;
            $is_new = false;
            
            if ($beer && $tap && $tap->tapped_time) {
                $tapped_timestamp = strtotime($tap->tapped_time);
                $is_new = (time() - $tapped_timestamp) < $new_duration;
            }
    
            $category = $beer ? get_post_meta($beer->ID, '_beer_category', true) : '';

            $tap_list_html .= '<div class="tap-item'.($is_new ? ' new-beer' : '').'" id="tap-'.$tap_id.'">';
            $tap_list_html .= '<div class="tap-item--inner is-tap-number"><div class="tap-number" data-category="' . esc_attr($category) . '">' . esc_html($tap_id) . '</div></div>';

            if ($beer) {
                $tap_list_html .= '<div class="tap-item--inner beer-name">' . esc_html($beer->post_title);

                if ($is_new) {
                    $tap_list_html .= ' <div class="new-indicator">NEW!</div>';
                }

                $tap_list_html .= '</div>';

                $tap_list_html .= '<div class="tap-item--inner is-beer-style"><div class="beer-style" data-category="' . esc_attr($category) . '">' . esc_html(get_post_meta($beer->ID, '_beer_stil', true)) . '</div></div>';
                $tap_list_html .= '<div class="tap-item--inner brewer-name">' . esc_html(get_post_meta($beer->ID, '_beer_brewer', true)) . '</div>';
                $tap_list_html .= '<div class="tap-item--inner brewer-location">' . esc_html(get_post_meta($beer->ID, '_beer_location', true)) . '</div>';
                $tap_list_html .= '<div class="tap-item--inner ibu"><i class="icon icon--hops"></i> <span class="ibu--inner">' . esc_html(get_post_meta($beer->ID, '_beer_ibu', true)) . '</span></div>';
                $tap_list_html .= '<div class="tap-item--inner abv"><i class="icon icon--flask"></i> <span>' . esc_html(get_post_meta($beer->ID, '_beer_abv', true)) . '%</span></div>';
            } else {
                $tap_list_html .= '<div class="tap-item--inner beer-details empty-tap"><div class="empty-tap--inner">No beer assigned</div></div>';
            }
            
            $tap_list_html .= '</div>';
        }
        
        $tap_list_html .= '</div>';
        
        // Handle wrapper setting
        if ($wrapper === 'iframe') {
            $iframe_id = 'bftl-tap-list-iframe-' . uniqid();
            $output = '<iframe id="' . esc_attr($iframe_id) . '" style="width:100%; border:none; height:100%; max-width: 100%; position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 9999; margin-top: 0;"></iframe>';
            
            // Create the initialization script
            $init_script = '
            <script type="text/javascript">
            (function() {
                var iframe = document.getElementById("' . esc_js($iframe_id) . '");
                var doc = iframe.contentWindow.document;
                doc.open();
                doc.write(' . json_encode($tap_list_html) . ');
                doc.write(\'<link rel="stylesheet" href="' . esc_url($this->get_asset_url('css/tap-list-styles.css')) . '">\');
                doc.write(\'<script type="text/javascript" src="' . esc_url(includes_url('js/jquery/jquery.min.js')) . '"><\/script>\');
                doc.write(\'<script type="text/javascript" src="' . esc_url($this->get_asset_url('js/tap-list-display.js')) . '"><\/script>\');
                doc.write(\'<script type="text/javascript">var BFTLFront = ' . json_encode([
                    'rest_url' => rest_url('beer-festival-tap-list/v1/taps'),
                    'refresh_interval' => 5,
                    'new_duration' => isset($settings['new_beer_duration']) ? intval($settings['new_beer_duration']) : 60,
                    'css_url' => $this->get_asset_url('css/tap-list-styles.css')
                ]) . ';<\/script>\');
                doc.close();
            })();
            </script>';
            
            $output .= $init_script;
        } else {
            $output = '<div class="bftl-tap-list-wrapper">' . $tap_list_html . '</div>';
        }
        
        return $output;
    }
    
    
    

    private function is_new_beer($tapped_time, $settings) {
        if (!$tapped_time) return false;
        $duration = isset($settings['new_beer_duration']) ? intval($settings['new_beer_duration']) : 60;
        $tapped_timestamp = strtotime($tapped_time);
        return (time() - $tapped_timestamp) < $duration;
    }

    public function get_tap_list_data() {
        $taps = Tap_Manager::get_all_taps();
        if (is_wp_error($taps)) {
            error_log('Tap List Error: ' . $taps->get_error_message());
            return new WP_Error('bftl_tap_list_error', __('Error fetching tap data', 'beer-festival-tap'), ['status' => 500]);
        }

        $data = [];
        $settings = get_option('beer_festival_settings', []);
        $num_taps = isset($settings['tap_count']) ? intval($settings['tap_count']) : 16;
        
        // Create a map of all taps
        $tap_map = [];
        foreach ($taps as $tap) {
            $tap_map[intval($tap->tap_id)] = $tap;
        }
        
        // Process all taps, including empty ones
        for ($tap_id = 1; $tap_id <= $num_taps; $tap_id++) {
            $tap = isset($tap_map[$tap_id]) ? $tap_map[$tap_id] : null;
            
            if ($tap && $tap->active && $tap->beer_id) {
                $beer = get_post($tap->beer_id);
                if (!$beer) {
                    error_log('Beer not found for tap ' . $tap_id);
                    $data[] = [
                        'tap_id' => $tap_id,
                        'is_empty' => true
                    ];
                    continue;
                }
                
                $data[] = [
                    'tap_id' => $tap_id,
                    'beer_name' => $beer->post_title,
                    'beer_style' => get_post_meta($beer->ID, '_beer_stil', true),
                    'beer_category' => get_post_meta($beer->ID, '_beer_category', true),
                    'brewer_name' => get_post_meta($beer->ID, '_beer_brewer', true),
                    'brewer_location' => get_post_meta($beer->ID, '_beer_location', true),
                    'ibu' => get_post_meta($beer->ID, '_beer_ibu', true),
                    'abv' => get_post_meta($beer->ID, '_beer_abv', true),
                    'is_new' => $this->is_new_beer($tap->tapped_time, $settings),
                    'is_empty' => false
                ];
            } else {
                // Add empty tap data
                $data[] = [
                    'tap_id' => $tap_id,
                    'is_empty' => true
                ];
            }
        }
        
        return $data;
    }
}

new Beer_Festival_Public();
