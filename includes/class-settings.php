<?php
// File: includes/class-settings.php

if (!defined('ABSPATH')) exit;

class Beer_Festival_Settings {

    private $option_name = 'beer_festival_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings_page() {
        add_submenu_page(
            'beer-festival-settings', // Parent slug
            __('Settings', 'beer-festival-tap'),
            __('Settings', 'beer-festival-tap'),
            'manage_options',
            'beer-festival-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'beer_festival_settings_group',
            $this->option_name,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'tap_count' => 16,
                    'refresh_interval' => 30,
                    'new_beer_duration' => 60
                ]
            ]
        );

        add_settings_section(
            'beer_festival_main_section',
            __('Tap List Settings', 'beer-festival-tap'),
            null,
            'beer-festival-settings'
        );

        add_settings_field(
            'tap_count',
            __('Number of Taps', 'beer-festival-tap'),
            [$this, 'field_tap_count'],
            'beer-festival-settings',
            'beer_festival_main_section'
        );

        add_settings_field(
            'refresh_interval',
            __('Auto-refresh Interval (seconds)', 'beer-festival-tap'),
            [$this, 'field_refresh_interval'],
            'beer-festival-settings',
            'beer_festival_main_section'
        );

        add_settings_field(
            'new_beer_duration',
            __('"New Beer" Indicator Duration (seconds)', 'beer-festival-tap'),
            [$this, 'field_new_beer_duration'],
            'beer-festival-settings',
            'beer_festival_main_section'
        );

        add_settings_field(
            'frontend_wrapper',
            __('Frontend Wrapper', 'beer-festival-tap'),
            [$this, 'field_frontend_wrapper'],
            'beer-festival-settings',
            'beer_festival_main_section'
        );

        add_settings_section(
            'beer_festival_staff_section',
            __('Staff Access (Beer Pages)', 'beer-festival-tap'),
            function () {
                echo '<p>' . esc_html__('Staff publish and remove beers on taps by scanning a beer\'s QR code, without logging in. Use these options to restrict that. Logged-in administrators and the Tap Management page are never affected.', 'beer-festival-tap') . '</p>';
            },
            'beer-festival-settings'
        );

        add_settings_field(
            'staff_changes_enabled',
            __('Tap Changes', 'beer-festival-tap'),
            [$this, 'field_staff_changes_enabled'],
            'beer-festival-settings',
            'beer_festival_staff_section'
        );

        add_settings_field(
            'staff_pin',
            __('Staff PIN', 'beer-festival-tap'),
            [$this, 'field_staff_pin'],
            'beer-festival-settings',
            'beer_festival_staff_section'
        );
    }

    public function field_staff_changes_enabled() {
        $options = get_option($this->option_name, []);
        $enabled = !isset($options['staff_changes_enabled']) || !empty($options['staff_changes_enabled']);
        ?>
        <input type="hidden" name="beer_festival_settings[staff_changes_enabled]" value="0">
        <label>
            <input type="checkbox" name="beer_festival_settings[staff_changes_enabled]" value="1" <?php checked($enabled); ?>>
            <?php _e('Allow publishing and removing beers from beer pages', 'beer-festival-tap'); ?>
        </label>
        <p class="description"><?php _e('Untick this to switch tap changes off completely, for example if someone is abusing the QR links. Beer pages then become read-only and the server rejects changes. Tick it again to restore access.', 'beer-festival-tap'); ?></p>
        <?php
    }

    public function field_staff_pin() {
        $options = get_option($this->option_name, []);
        $enabled = !empty($options['staff_pin_enabled']);
        $pin = isset($options['staff_pin']) ? (string) $options['staff_pin'] : '';
        ?>
        <input type="hidden" name="beer_festival_settings[staff_pin_enabled]" value="0">
        <label>
            <input type="checkbox" name="beer_festival_settings[staff_pin_enabled]" value="1" <?php checked($enabled); ?>>
            <?php _e('Require a PIN to change taps from beer pages', 'beer-festival-tap'); ?>
        </label>
        <p>
            <input type="text" name="beer_festival_settings[staff_pin]" value="<?php echo esc_attr($pin); ?>" class="regular-text" autocomplete="off" maxlength="<?php echo intval(Beer_Festival_Staff_Access::PIN_MAX_LEN); ?>" placeholder="<?php esc_attr_e('e.g. 4821', 'beer-festival-tap'); ?>">
        </p>
        <p class="description"><?php printf(esc_html__('%1$d–%2$d characters. Staff enter it once per phone; the phone remembers it. Changing the PIN here locks out every phone until its user enters the new one. Wrong guesses are rate limited per IP address.', 'beer-festival-tap'), Beer_Festival_Staff_Access::PIN_MIN_LEN, Beer_Festival_Staff_Access::PIN_MAX_LEN); ?></p>
        <?php
    }

    public function field_frontend_wrapper() {
        $options = get_option($this->option_name, []);
        $value = isset($options['frontend_wrapper']) ? $options['frontend_wrapper'] : 'div';
        ?>
        <select name="beer_festival_settings[frontend_wrapper]">
            <option value="div" <?php selected($value, 'div'); ?>><?php _e('DIV', 'beer-festival-tap'); ?></option>
            <option value="iframe" <?php selected($value, 'iframe'); ?>><?php _e('IFRAME', 'beer-festival-tap'); ?></option>
        </select>
        <p class="description"><?php _e('Choose whether the tap list is wrapped in a DIV or an IFRAME on the frontend.', 'beer-festival-tap'); ?></p>
        <p class="description"><?php _e('Select DIV to embed the tap list directly within your page. Choose IFRAME if you intend to display the tap list in fullscreen mode', 'beer-festival-tap'); ?></p>
        <?php
    }

    // Settings fields renderers

    public function field_tap_count() {
        $options = get_option($this->option_name, []);
        $value = isset($options['tap_count']) ? intval($options['tap_count']) : 16;
        echo '<input type="number" name="beer_festival_settings[tap_count]" min="1" max="100" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Set the total number of taps (1–100).', 'beer-festival-tap') . '</p>';
    }

    public function field_refresh_interval() {
        $options = get_option($this->option_name, []);
        $value = isset($options['refresh_interval']) ? intval($options['refresh_interval']) : 30;
        echo '<input type="number" name="beer_festival_settings[refresh_interval]" min="5" max="300" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('How often the tap list auto-refreshes for users (in seconds).', 'beer-festival-tap') . '</p>';
    }

    public function field_new_beer_duration() {
        $options = get_option($this->option_name);
        $value = $options['new_beer_duration'] ?? 5; // Same key here
        echo '<input type="number" name="beer_festival_settings[new_beer_duration]" value="' . esc_attr($value) . '">';
    }
    

    // Settings page renderer

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Beer Festival Tap List Settings', 'beer-festival-tap'); ?></h1>

            <div class="card" style="max-width: 600px; margin: 1em 0;">
                <h2><?php _e('Displaying the Tap List', 'beer-festival-tap'); ?></h2>
                <p><?php _e('Add this shortcode to any page or post to display the live, auto-refreshing tap list on the frontend:', 'beer-festival-tap'); ?></p>
                <p>
                    <input type="text" readonly value="[beer_tap_list]" id="bftl-shortcode-field" class="regular-text" onclick="this.select();">
                    <button type="button" class="button" id="bftl-shortcode-copy"><?php _e('Copy', 'beer-festival-tap'); ?></button>
                </p>
                <p class="description">
                    <?php _e('The "Frontend Wrapper" setting below controls how it is displayed: choose DIV to embed the tap list inline within your page content, or IFRAME to show it fullscreen (for example on a dedicated screen at the festival venue).', 'beer-festival-tap'); ?>
                </p>
            </div>
            <script>
            (function() {
                var button = document.getElementById('bftl-shortcode-copy');
                var field = document.getElementById('bftl-shortcode-field');
                if (!button || !field) return;

                function showCopied() {
                    var original = button.textContent;
                    button.textContent = '<?php echo esc_js(__('Copied!', 'beer-festival-tap')); ?>';
                    setTimeout(function() { button.textContent = original; }, 2000);
                }

                button.addEventListener('click', function() {
                    field.select();
                    field.setSelectionRange(0, field.value.length);
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(field.value).then(showCopied, function() {
                            document.execCommand('copy');
                            showCopied();
                        });
                    } else {
                        document.execCommand('copy');
                        showCopied();
                    }
                });
            })();
            </script>

            <form method="post" action="options.php">
                <?php
                settings_fields('beer_festival_settings_group');
                do_settings_sections('beer-festival-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    





    // Sanitization and validation

    public function sanitize_settings($input) {
        $options = get_option($this->option_name, []);
        $new = [];

        $allowed_wrappers = ['div', 'iframe'];
$wrapper = isset($input['frontend_wrapper']) ? $input['frontend_wrapper'] : 'div';
$new['frontend_wrapper'] = in_array($wrapper, $allowed_wrappers, true) ? $wrapper : 'div';


        // Tap count
        $tap_count = isset($input['tap_count']) ? intval($input['tap_count']) : 16;
        if ($tap_count < 1 || $tap_count > 100) {
            add_settings_error(
                $this->option_name,
                'tap_count_invalid',
                __('Tap count must be between 1 and 100.', 'beer-festival-tap'),
                'error'
            );
            $new['tap_count'] = isset($options['tap_count']) ? $options['tap_count'] : 16;
        } else {
            // Optional: check for active assignments above new tap count
            // You can add logic here to warn if reducing below current assignments
            $new['tap_count'] = $tap_count;
        }

        // Refresh interval
        $refresh = isset($input['refresh_interval']) ? intval($input['refresh_interval']) : 30;
        if ($refresh < 5 || $refresh > 300) {
            add_settings_error(
                $this->option_name,
                'refresh_interval_invalid',
                __('Auto-refresh interval must be between 5 and 300 seconds.', 'beer-festival-tap'),
                'error'
            );
            $new['refresh_interval'] = isset($options['refresh_interval']) ? $options['refresh_interval'] : 30;
        } else {
            $new['refresh_interval'] = $refresh;
        }

        // New beer indicator duration
        $duration = isset($input['new_beer_duration']) ? intval($input['new_beer_duration']) : 60;
        if ($duration < 10 || $duration > 600) {
            add_settings_error(
                $this->option_name,
                'new_beer_duration_invalid',
                __('"New Beer" indicator duration must be between 10 and 600 seconds.', 'beer-festival-tap'),
                'error'
            );
            $new['new_beer_duration'] = isset($options['new_beer_duration']) ? $options['new_beer_duration'] : 60;
        } else {
            $new['new_beer_duration'] = $duration;
        }

        // Staff access: master switch, then the optional shared PIN.
        // Fields that were not submitted at all (a programmatic update, not the
        // settings form) keep their stored value instead of resetting.
        if (!isset($input['staff_changes_enabled'])) {
            $new['staff_changes_enabled'] = !isset($options['staff_changes_enabled']) || !empty($options['staff_changes_enabled']) ? 1 : 0;
        } else {
            $new['staff_changes_enabled'] = !empty($input['staff_changes_enabled']) ? 1 : 0;
        }

        if (!isset($input['staff_pin']) && !isset($input['staff_pin_enabled'])) {
            $new['staff_pin_enabled'] = !empty($options['staff_pin_enabled']) ? 1 : 0;
            $new['staff_pin'] = isset($options['staff_pin']) ? (string) $options['staff_pin'] : '';
            return $new;
        }

        $pin = isset($input['staff_pin']) ? trim(sanitize_text_field(wp_unslash($input['staff_pin']))) : '';
        $pin_enabled = !empty($input['staff_pin_enabled']);
        $pin_length = strlen($pin);
        $pin_ok = $pin_length >= Beer_Festival_Staff_Access::PIN_MIN_LEN && $pin_length <= Beer_Festival_Staff_Access::PIN_MAX_LEN;

        if ($pin_enabled && !$pin_ok) {
            add_settings_error(
                $this->option_name,
                'staff_pin_invalid',
                sprintf(
                    __('The staff PIN must be %1$d–%2$d characters. The PIN requirement was not enabled.', 'beer-festival-tap'),
                    Beer_Festival_Staff_Access::PIN_MIN_LEN,
                    Beer_Festival_Staff_Access::PIN_MAX_LEN
                ),
                'error'
            );
            $pin_enabled = false;
        }
        if ($pin !== '' && !$pin_ok) {
            // Never store a PIN that could not be used; keep whatever was valid before.
            $pin = isset($options['staff_pin']) ? (string) $options['staff_pin'] : '';
        }

        $new['staff_pin_enabled'] = $pin_enabled ? 1 : 0;
        $new['staff_pin'] = $pin;

        return $new;
    }
}
