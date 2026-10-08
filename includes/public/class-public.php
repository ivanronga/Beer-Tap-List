<?php
if (!defined('ABSPATH')) exit;

class Beer_Festival_Public {

    public function __construct() {
        add_shortcode('beer_tap_list', [$this, 'render_tap_list']);
        add_action('init', [$this, 'maybe_serve_styles'], 0);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_filter('single_template', [$this, 'load_single_beer_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_single_beer_assets']);
        add_filter('wp_robots', [$this, 'noindex_beer_pages']);
        add_action('wp_enqueue_scripts', [$this, 'isolate_beer_page_assets'], 9999);
    }

    /**
     * The staff beer page is a self-contained document with its own design, so
     * no theme or block-editor stylesheet/script should leak into it. Keep only
     * this plugin's assets and the admin bar (jQuery and the admin bar's other
     * dependencies are pulled in automatically as dependencies).
     */
    public function isolate_beer_page_assets() {
        if (!is_singular('beer')) {
            return;
        }

        global $wp_styles, $wp_scripts;
        $keep_styles  = ['bftl-beer-single-styles', 'admin-bar'];
        $keep_scripts = ['bftl-beer-single', 'admin-bar'];

        foreach ((array) $wp_styles->queue as $handle) {
            if (!in_array($handle, $keep_styles, true)) {
                wp_dequeue_style($handle);
            }
        }
        foreach ((array) $wp_scripts->queue as $handle) {
            if (!in_array($handle, $keep_scripts, true)) {
                wp_dequeue_script($handle);
            }
        }
        remove_action('wp_head', 'wp_print_auto_sizes_contain_css_fix', 1);
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
    }

    /**
     * Beer pages are staff controls reached from a QR code; keep them out of
     * search results.
     */
    public function noindex_beer_pages($robots) {
        if (is_singular('beer')) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            unset($robots['max-image-preview']);
        }
        return $robots;
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

        $beer_id = get_queried_object_id();
        $taps = Tap_Manager::get_all_taps();
        $current_tap_ids = [];
        if (!is_wp_error($taps)) {
            foreach ($taps as $tap) {
                if ($tap->active && intval($tap->beer_id) === $beer_id) {
                    $current_tap_ids[] = intval($tap->tap_id);
                }
            }
        }

        wp_localize_script(
            'bftl-beer-single',
            'BeerSingle',
            [
                'beer_id'          => $beer_id,
                'assign_rest_url'  => rest_url('beer-festival-tap-list/v1/taps/assign'),
                'verify_rest_url'  => rest_url('beer-festival-tap-list/v1/staff/verify'),
                'current_tap_ids'  => $current_tap_ids,
                'access_mode'      => Beer_Festival_Staff_Access::page_mode(),
                // A logged-in administrator is recognised by the REST nonce and
                // so is never blocked by the staff switch or PIN.
                'rest_nonce'       => Beer_Festival_Staff_Access::is_admin_user() ? wp_create_nonce('wp_rest') : '',
            ]
        );
    }

    const BREAKPOINT_DEFAULT = 1440;
    const BREAKPOINT_MIN     = 480;
    const BREAKPOINT_MAX     = 4000;

    /**
     * Width (px) from which the table-style desktop layout is used; below it
     * the tap list is shown as cards. Set on the Settings page because the
     * display used at the event is not known in advance.
     */
    public static function get_desktop_breakpoint() {
        $settings = get_option('beer_festival_settings', []);
        $bp = isset($settings['desktop_breakpoint']) ? intval($settings['desktop_breakpoint']) : self::BREAKPOINT_DEFAULT;
        if ($bp < self::BREAKPOINT_MIN || $bp > self::BREAKPOINT_MAX) {
            $bp = self::BREAKPOINT_DEFAULT;
        }
        return $bp;
    }

    /**
     * URL of the board stylesheet with the configured breakpoint built in. A
     * media query cannot read a setting, so the stylesheet is generated; the
     * breakpoint and plugin version are in the URL, which makes the response
     * safe to cache for a long time.
     */
    private function get_styles_url() {
        return add_query_arg([
            'bftl_css' => self::get_desktop_breakpoint(),
            'ver'      => BEER_FESTIVAL_VERSION,
        ], home_url('/'));
    }

    /**
     * Cards flow into more columns as the screen widens. Each entry is the
     * smallest width at which that column count fits (cards stay >= ~340px).
     */
    private static function column_steps() {
        return [680 => 2, 1020 => 3, 1360 => 4];
    }

    // The column rules for a given desktop breakpoint: every range stops one
    // pixel short of it, so the card rules never apply to the desktop layout.
    private static function build_column_css($breakpoint) {
        $last = $breakpoint - 1;
        $starts = array_keys(self::column_steps());
        if ($starts[0] > $last) {
            return '';
        }

        $css = "@media (min-width:{$starts[0]}px) and (max-width:{$last}px){\n  .bftl-board .bftl-tap-list{ display:grid; }\n}\n";
        foreach ($starts as $i => $start) {
            $end = isset($starts[$i + 1]) ? min($starts[$i + 1] - 1, $last) : $last;
            if ($start > $end) {
                continue;
            }
            $cols = self::column_steps()[$start];
            $css .= "@media (min-width:{$start}px) and (max-width:{$end}px){\n"
                  . "  .bftl-board .bftl-tap-list{ grid-template-columns:repeat({$cols}, minmax(0, 1fr)); }\n"
                  . "  .bftl-board .tap-item:not(:nth-child({$cols}n)){ border-right:1px solid #696969; }\n"
                  . "}\n";
        }
        return $css;
    }

    /**
     * The board stylesheet for a desktop breakpoint: the shipped file with its
     * 1440px/1439px media queries rewritten, the column block regenerated, and
     * relative asset URLs made absolute (it is not served from the css folder).
     */
    public static function build_styles_css($breakpoint) {
        $css = file_get_contents(BEER_FESTIVAL_PLUGIN_DIR . 'includes/public/css/tap-list-styles.css');
        if ($css === false) {
            return '';
        }

        // strtr, not str_replace: it never re-scans its own output, so a
        // breakpoint such as 1439 cannot be rewritten twice.
        $css = strtr($css, [
            self::BREAKPOINT_DEFAULT . 'px'       => $breakpoint . 'px',
            (self::BREAKPOINT_DEFAULT - 1) . 'px' => ($breakpoint - 1) . 'px',
        ]);

        $css = preg_replace_callback(
            '#/\* @bftl-columns:start \*/.*?/\* @bftl-columns:end \*/#s',
            function () use ($breakpoint) {
                return self::build_column_css($breakpoint);
            },
            $css
        );

        $assets_base = plugins_url('includes/public/', BEER_FESTIVAL_PLUGIN_DIR . 'beer-festival-tap-list.php');
        return str_replace('url("../', 'url("' . $assets_base, $css);
    }

    public function maybe_serve_styles() {
        if (!isset($_GET['bftl_css'])) {
            return;
        }

        $breakpoint = intval($_GET['bftl_css']);
        if ($breakpoint < self::BREAKPOINT_MIN || $breakpoint > self::BREAKPOINT_MAX) {
            $breakpoint = self::BREAKPOINT_DEFAULT;
        }

        status_header(200);
        header('Content-Type: text/css; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=31536000, immutable');
        echo self::build_styles_css($breakpoint);
        exit;
    }
    private function get_asset_url($relative_path) {
        return plugins_url('includes/public/' . $relative_path, BEER_FESTIVAL_PLUGIN_DIR . 'beer-festival-tap-list.php');
    }

    /**
     * Global popup timing plus the enabled ads, in the shape the front-end
     * scheduler needs. Shared by the normal enqueue path and the iframe
     * wrapper's doc.write() sequence so both stay in sync. When the master
     * switch is off the ad list is empty, which makes the script do nothing.
     */
    private function get_ads_payload() {
        $settings = Beer_Festival_Marketing_Popups::get_settings();
        $payload = [
            'interval_seconds' => intval($settings['interval_seconds']),
            'duration_seconds' => intval($settings['duration_seconds']),
            'ads'              => [],
        ];
        if (!$settings['enabled']) {
            return $payload;
        }

        $ads = Beer_Festival_Marketing_Popups::get_enabled_ordered();
        if (is_wp_error($ads)) {
            return $payload;
        }
        foreach ($ads as $ad) {
            $image_url = wp_get_attachment_image_url($ad->image_id, 'large');
            if (!$image_url) {
                continue; // attachment was deleted -- skip rather than show a broken image
            }
            $payload['ads'][] = [
                'id'        => intval($ad->id),
                'image_url' => $image_url,
                'weight'    => max(1, intval($ad->weight)),
            ];
        }
        return $payload;
    }

    /**
     * The tap list and popup assets are only needed on a page that shows the
     * [beer_tap_list] shortcode. Themes that print the board by calling
     * do_shortcode() themselves can opt in with the filter.
     */
    private function should_enqueue_public_assets() {
        $post = is_singular() ? get_post() : null;
        $needed = $post && has_shortcode($post->post_content, 'beer_tap_list');
        return (bool) apply_filters('bftl_should_enqueue_public_assets', $needed);
    }

    public function enqueue_public_assets() {
        if (!$this->should_enqueue_public_assets()) {
            return;
        }

        $settings = get_option('beer_festival_settings', []);
        $refresh_interval = isset($settings['refresh_interval']) ? intval($settings['refresh_interval']) : 30;
        $new_duration = isset($settings['new_beer_duration']) ? intval($settings['new_beer_duration']) : 60;

        wp_enqueue_style(
            'bftl-tap-list-styles',
            $this->get_styles_url(),
            [],
            null // the version is already part of the generated URL
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
                'css_url' => $this->get_styles_url()
            )
        );

        wp_enqueue_style(
            'bftl-tap-list-ads',
            $this->get_asset_url('css/tap-list-ads.css'),
            [],
            BEER_FESTIVAL_VERSION
        );

        wp_enqueue_script(
            'bftl-tap-list-ads',
            $this->get_asset_url('js/tap-list-ads.js'),
            array('jquery'),
            BEER_FESTIVAL_VERSION,
            true
        );

        wp_localize_script(
            'bftl-tap-list-ads',
            'BFTLAds',
            $this->get_ads_payload()
        );
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
        // Field labels shown on the mobile cards. They sit on the list root so the
        // live-refresh script can build identical rows without its own strings.
        $labels = [
            'name'     => __('Ime piva', 'beer-festival-tap'),
            'style'    => __('Stil', 'beer-festival-tap'),
            'brewer'   => __('Pivar', 'beer-festival-tap'),
            'location' => __('Lokacija', 'beer-festival-tap'),
        ];
        $tap_list_html = '<div class="bftl-tap-list" style="--bftl-tap-rows: ' . intval($num_taps) . ';"'
            . ' data-label-name="' . esc_attr($labels['name']) . '"'
            . ' data-label-style="' . esc_attr($labels['style']) . '"'
            . ' data-label-brewer="' . esc_attr($labels['brewer']) . '"'
            . ' data-label-location="' . esc_attr($labels['location']) . '">';

        // Row banding follows zone-category groups (all taps in the same zone share
        // a band), not individual rows -- flips only when the zone category changes.
        $prev_zone_category = null;
        $band_highlight = true;

        for ($tap_id = 1; $tap_id <= $num_taps; $tap_id++) {
            $tap = isset($tap_map[$tap_id]) ? $tap_map[$tap_id] : null;
            $beer = ($tap && $tap->beer_id && isset($beers[$tap->beer_id])) ? $beers[$tap->beer_id] : null;
            $is_new = false;

            if ($beer && $tap && $tap->tapped_time) {
                $tapped_timestamp = strtotime($tap->tapped_time);
                $is_new = (time() - $tapped_timestamp) < $new_duration;
            }

            $category = $beer ? get_post_meta($beer->ID, '_beer_category', true) : '';
            $zone_category = $tap ? $tap->category : '';

            if ($prev_zone_category !== null && $zone_category !== $prev_zone_category) {
                $band_highlight = !$band_highlight;
            }
            $prev_zone_category = $zone_category;

            $tap_list_html .= '<div class="tap-item'.($is_new ? ' new-beer' : '').'" data-band="' . ($band_highlight ? '1' : '0') . '" id="tap-'.$tap_id.'">';
            $tap_list_html .= '<div class="tap-item--inner is-tap-number" data-category="' . esc_attr($zone_category) . '"><div class="tap-number" data-category="' . esc_attr($zone_category) . '">' . esc_html($tap_id) . '</div></div>';

            if ($beer) {
                // .tap-body / .tap-meta / .tap-stats group the fields into the
                // mobile card; on wider screens they are display:contents so the
                // desktop grid still sees the fields as direct children of the row.
                $tap_list_html .= '<div class="tap-body">';

                $tap_list_html .= '<div class="tap-item--inner beer-name" data-label="' . esc_attr($labels['name']) . '">' . esc_html($beer->post_title);

                if ($is_new) {
                    // Beside the name on wide screens; on mobile it is hidden and the
                    // copy in the IBU/ABV row below is shown instead.
                    $tap_list_html .= ' <div class="new-indicator new-indicator--name">NEW!</div>';
                }

                $tap_list_html .= '</div>';

                $tap_list_html .= '<div class="tap-item--inner is-beer-style" data-label="' . esc_attr($labels['style']) . '"><div class="beer-style" data-category="' . esc_attr($category) . '">' . esc_html(get_post_meta($beer->ID, '_beer_stil', true)) . '</div></div>';

                $tap_list_html .= '<div class="tap-meta">';
                $tap_list_html .= '<div class="tap-item--inner brewer-name" data-label="' . esc_attr($labels['brewer']) . '">' . esc_html(get_post_meta($beer->ID, '_beer_brewer', true)) . '</div>';
                $tap_list_html .= '<div class="tap-item--inner brewer-location" data-label="' . esc_attr($labels['location']) . '">' . esc_html(get_post_meta($beer->ID, '_beer_location', true)) . '</div>';
                $tap_list_html .= '</div>';

                $tap_list_html .= '<div class="tap-stats">';
                $tap_list_html .= '<div class="tap-item--inner ibu"><i class="icon icon--hops"></i> <span class="stat-label">IBU</span> <span class="ibu--inner">' . esc_html(get_post_meta($beer->ID, '_beer_ibu', true)) . '</span></div>';
                $tap_list_html .= '<div class="tap-item--inner abv"><i class="icon icon--flask"></i> <span class="stat-label">ABV</span> <span class="abv--inner">' . esc_html(get_post_meta($beer->ID, '_beer_abv', true)) . '%</span></div>';
                if ($is_new) {
                    $tap_list_html .= '<div class="new-indicator new-indicator--stats">NEW!</div>';
                }
                $tap_list_html .= '</div>';

                $tap_list_html .= '</div>';
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
                doc.write(\'<style>html,body{margin:0;padding:0;background:#22252d}</style>\');
                doc.write(' . json_encode('<div class="bftl-board">' . $tap_list_html . '</div>') . ');
                doc.write(\'<link rel="stylesheet" href="' . esc_url($this->get_styles_url()) . '">\');
                doc.write(\'<link rel="stylesheet" href="' . esc_url($this->get_asset_url('css/tap-list-ads.css')) . '">\');
                doc.write(\'<script type="text/javascript" src="' . esc_url(includes_url('js/jquery/jquery.min.js')) . '"><\/script>\');
                doc.write(\'<script type="text/javascript" src="' . esc_url($this->get_asset_url('js/tap-list-display.js')) . '"><\/script>\');
                doc.write(\'<script type="text/javascript">var BFTLFront = ' . json_encode([
                    'rest_url' => rest_url('beer-festival-tap-list/v1/taps'),
                    'refresh_interval' => 5,
                    'new_duration' => isset($settings['new_beer_duration']) ? intval($settings['new_beer_duration']) : 60,
                    'css_url' => $this->get_styles_url()
                ]) . ';<\/script>\');
                doc.write(\'<script type="text/javascript" src="' . esc_url($this->get_asset_url('js/tap-list-ads.js')) . '"><\/script>\');
                doc.write(\'<script type="text/javascript">var BFTLAds = ' . json_encode($this->get_ads_payload()) . ';<\/script>\');
                doc.close();
            })();
            </script>';
            
            $output .= $init_script;
        } else {
            // alignfull lets block themes break the board out of their content column.
            $output = '<div class="bftl-tap-list-wrapper bftl-board alignfull">' . $tap_list_html . '</div>';
        }
        
        return $output;
    }
    
    
    

    private static function is_new_beer($tapped_time, $settings) {
        if (!$tapped_time) return false;
        $duration = isset($settings['new_beer_duration']) ? intval($settings['new_beer_duration']) : 60;
        $tapped_timestamp = strtotime($tapped_time);
        return (time() - $tapped_timestamp) < $duration;
    }

    public static function get_tap_list_data() {
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
            $zone_category = $tap ? $tap->category : '';

            if ($tap && $tap->active && $tap->beer_id) {
                $beer = get_post($tap->beer_id);
                if (!$beer) {
                    error_log('Beer not found for tap ' . $tap_id);
                    $data[] = [
                        'tap_id' => $tap_id,
                        'zone_category' => $zone_category,
                        'is_empty' => true
                    ];
                    continue;
                }

                $data[] = [
                    'tap_id' => $tap_id,
                    'zone_category' => $zone_category,
                    'beer_name' => $beer->post_title,
                    'beer_style' => get_post_meta($beer->ID, '_beer_stil', true),
                    'beer_category' => get_post_meta($beer->ID, '_beer_category', true),
                    'brewer_name' => get_post_meta($beer->ID, '_beer_brewer', true),
                    'brewer_location' => get_post_meta($beer->ID, '_beer_location', true),
                    'ibu' => get_post_meta($beer->ID, '_beer_ibu', true),
                    'abv' => get_post_meta($beer->ID, '_beer_abv', true),
                    'is_new' => self::is_new_beer($tap->tapped_time, $settings),
                    'is_empty' => false
                ];
            } else {
                // Add empty tap data
                $data[] = [
                    'tap_id' => $tap_id,
                    'zone_category' => $zone_category,
                    'is_empty' => true
                ];
            }
        }
        
        return $data;
    }
}
