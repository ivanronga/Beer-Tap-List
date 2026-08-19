<?php
if (!defined('ABSPATH')) exit;

get_header();

$beer = get_queried_object();
$beer_id = $beer->ID;

$style    = get_post_meta($beer_id, '_beer_stil', true);
$brewer   = get_post_meta($beer_id, '_beer_brewer', true);
$location = get_post_meta($beer_id, '_beer_location', true);
$ibu      = get_post_meta($beer_id, '_beer_ibu', true);
$abv      = get_post_meta($beer_id, '_beer_abv', true);
$category = get_post_meta($beer_id, '_beer_category', true);

$settings  = get_option('beer_festival_settings', []);
$tap_count = isset($settings['tap_count']) ? intval($settings['tap_count']) : 16;

$taps = Tap_Manager::get_all_taps();
$current_taps = [];
$tap_categories = [];
if (!is_wp_error($taps)) {
    foreach ($taps as $tap) {
        if ($tap->active && intval($tap->beer_id) === $beer_id) {
            $current_taps[] = intval($tap->tap_id);
        }
        if (!empty($tap->category)) {
            $tap_categories[intval($tap->tap_id)] = $tap->category;
        }
    }
}
?>
<div class="bftl-beer-single-wrapper">
    <h1 class="bftl-beer-single-title"><?php echo esc_html($beer->post_title); ?></h1>

    <div class="bftl-beer-single-meta">
        <?php if ($category): ?><p><strong><?php _e('Category:', 'beer-festival-tap'); ?></strong> <?php echo esc_html($category); ?></p><?php endif; ?>
        <?php if ($style): ?><p><strong><?php _e('Style:', 'beer-festival-tap'); ?></strong> <?php echo esc_html($style); ?></p><?php endif; ?>
        <?php if ($brewer): ?><p><strong><?php _e('Brewer:', 'beer-festival-tap'); ?></strong> <?php echo esc_html($brewer); ?></p><?php endif; ?>
        <?php if ($location): ?><p><strong><?php _e('Location:', 'beer-festival-tap'); ?></strong> <?php echo esc_html($location); ?></p><?php endif; ?>
        <?php if ($ibu): ?><p><strong>IBU:</strong> <?php echo esc_html($ibu); ?></p><?php endif; ?>
        <?php if ($abv): ?><p><strong>ABV:</strong> <?php echo esc_html($abv); ?>%</p><?php endif; ?>
    </div>

    <div class="bftl-beer-single-status">
        <?php if ($current_taps): ?>
            <p><?php _e('Currently on tap:', 'beer-festival-tap'); ?></p>
            <ul class="bftl-current-taps">
            <?php foreach ($current_taps as $tap_id): ?>
                <li>
                    <?php printf(esc_html__('Tap #%d', 'beer-festival-tap'), $tap_id); ?>
                    <button type="button" class="button bftl-remove-tap" data-tap="<?php echo esc_attr($tap_id); ?>"><?php _e('Remove from this tap', 'beer-festival-tap'); ?></button>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php _e('Not currently on tap.', 'beer-festival-tap'); ?></p>
        <?php endif; ?>
    </div>

    <div class="bftl-beer-single-assign">
        <label for="bftl-assign-tap-select"><?php _e('Assign to tap:', 'beer-festival-tap'); ?></label>
        <select id="bftl-assign-tap-select">
            <?php for ($i = 1; $i <= $tap_count; $i++):
                $tap_category = isset($tap_categories[$i]) ? $tap_categories[$i] : '';
                $tap_label = ($tap_category && $tap_category !== Beer_Festival_Categories::DEFAULT_CATEGORY)
                    ? sprintf('%d - %s', $i, $tap_category)
                    : (string) $i;
            ?>
            <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($tap_label); ?></option>
            <?php endfor; ?>
        </select>
        <button type="button" id="bftl-assign-tap-button" class="button button-primary"><?php _e('Assign', 'beer-festival-tap'); ?></button>
        <span id="bftl-beer-assign-feedback"></span>
    </div>
</div>
<?php
get_footer();
