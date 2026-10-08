<?php
if (!defined('ABSPATH')) exit;

/**
 * Who may change taps from a beer page (the QR-code staff flow).
 *
 * Two independent options, both stored in the beer_festival_settings option:
 *  - staff_changes_enabled (default on): master switch. Off blocks every tap
 *    change that does not come from a logged-in administrator.
 *  - staff_pin_enabled (default off) + staff_pin: when on, a request must carry
 *    the shared staff PIN. Wrong guesses are rate limited per IP.
 *
 * Logged-in administrators (manage_options, recognised via the REST nonce) are
 * never blocked, so the Tap Management admin page keeps working either way.
 * Enforcement lives here, on the server; the beer page only mirrors it in the UI.
 */
class Beer_Festival_Staff_Access {

    const OPTION_NAME   = 'beer_festival_settings';
    const PIN_HEADER    = 'X-BFTL-Pin';
    const MAX_FAILURES  = 10;
    const LOCKOUT_SECS  = 600;
    const PIN_MIN_LEN   = 4;
    const PIN_MAX_LEN   = 32;

    public static function changes_enabled() {
        $options = get_option(self::OPTION_NAME, []);
        return !isset($options['staff_changes_enabled']) || !empty($options['staff_changes_enabled']);
    }

    public static function pin_enabled() {
        $options = get_option(self::OPTION_NAME, []);
        return !empty($options['staff_pin_enabled']) && self::get_pin() !== '';
    }

    public static function get_pin() {
        $options = get_option(self::OPTION_NAME, []);
        return isset($options['staff_pin']) ? (string) $options['staff_pin'] : '';
    }

    public static function is_admin_user() {
        return current_user_can('manage_options');
    }

    /**
     * What the current visitor may do on a beer page:
     *  'open'       - may change taps (admin, or switch on and no PIN required)
     *  'pin'        - may change taps once the PIN has been entered
     *  'disabled'   - tap changes are switched off; page is read-only
     */
    public static function page_mode() {
        if (self::is_admin_user()) {
            return 'open';
        }
        if (!self::changes_enabled()) {
            return 'disabled';
        }
        return self::pin_enabled() ? 'pin' : 'open';
    }

    private static function failure_key() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        return 'bftl_pin_fail_' . md5($ip);
    }

    private static function is_locked_out() {
        return intval(get_transient(self::failure_key())) >= self::MAX_FAILURES;
    }

    private static function record_failure() {
        $key = self::failure_key();
        set_transient($key, intval(get_transient($key)) + 1, self::LOCKOUT_SECS);
    }

    private static function clear_failures() {
        delete_transient(self::failure_key());
    }

    /**
     * Constant-time PIN check with per-IP lockout. Returns true or a WP_Error.
     */
    public static function check_pin($candidate) {
        if (self::is_locked_out()) {
            return new WP_Error('bftl_pin_locked', __('Too many wrong PIN attempts. Try again in a few minutes.', 'beer-festival-tap'), ['status' => 429]);
        }
        if (is_string($candidate) && $candidate !== '' && hash_equals(self::get_pin(), $candidate)) {
            self::clear_failures();
            return true;
        }
        self::record_failure();
        return new WP_Error('bftl_pin_required', __('Wrong or missing staff PIN.', 'beer-festival-tap'), ['status' => 403]);
    }

    /**
     * REST permission callback for the tap assign endpoint.
     */
    public static function authorize_assign(WP_REST_Request $request) {
        return self::decide_once($request, 'assign');
    }

    public static function authorize_verify(WP_REST_Request $request) {
        return self::decide_once($request, 'verify');
    }

    // WordPress can evaluate a permission callback more than once per request.
    // Decide once, otherwise every wrong PIN would count as several failures.
    private static function decide_once(WP_REST_Request $request, $kind) {
        static $decisions = [];
        $key = spl_object_id($request) . ':' . $kind;
        if (!array_key_exists($key, $decisions)) {
            $decisions[$key] = $kind === 'assign' ? self::decide_assign($request) : self::decide_verify($request);
        }
        return $decisions[$key];
    }

    private static function decide_assign(WP_REST_Request $request) {
        if (self::is_admin_user()) {
            return true;
        }
        if (!self::changes_enabled()) {
            return new WP_Error('bftl_changes_disabled', __('Tap changes from beer pages are currently disabled.', 'beer-festival-tap'), ['status' => 403]);
        }
        if (self::pin_enabled()) {
            return self::check_pin((string) $request->get_header('x_bftl_pin'));
        }
        return true;
    }

    /**
     * REST permission callback for the PIN check the beer page makes before
     * unlocking its controls. Always available (it only reveals yes/no), and
     * shares the same lockout as real tap changes.
     */
    private static function decide_verify(WP_REST_Request $request) {
        if (self::is_admin_user()) {
            return true;
        }
        if (!self::changes_enabled()) {
            return new WP_Error('bftl_changes_disabled', __('Tap changes from beer pages are currently disabled.', 'beer-festival-tap'), ['status' => 403]);
        }
        if (!self::pin_enabled()) {
            return true;
        }
        return self::check_pin((string) $request->get_header('x_bftl_pin'));
    }
}
