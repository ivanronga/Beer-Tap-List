<?php
if (!defined('ABSPATH')) exit;

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

$logo_path = get_template_directory() . '/img/hombre_logo_w.svg';
$logo_url  = get_template_directory_uri() . '/img/hombre_logo_w.svg';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#17251f">
<title><?php echo esc_html($beer->post_title); ?> &mdash; <?php bloginfo('name'); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class('bftl-beer-single-body'); ?>>
<div class="bftl-beer-single-page">
    <div class="bftl-beer-single-card">
        <div class="bftl-beer-single-logo">
            <?php if (file_exists($logo_path)): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php bloginfo('name'); ?>">
            <?php else: ?>
                <span class="bftl-beer-single-logo-text"><?php bloginfo('name'); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($current_taps): ?>
        <div class="bftl-beer-single-banner is-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 4h11v3h2a3 3 0 0 1 3 3v2a3 3 0 0 1-3 3h-2.17A6 6 0 0 1 10 19H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Zm11 5v6h2a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1h-2Z" fill="currentColor"/></svg>
            <p><?php esc_html_e('Pivo je objavljeno!', 'beer-festival-tap'); ?></p>
        </div>
        <?php else: ?>
        <div class="bftl-beer-single-banner is-warning">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3 2 20h20L12 3Zm0 5.5 6.2 10.7H5.8L12 8.5ZM11.1 13v3h1.8v-3h-1.8Zm0 4v1.7h1.8V17h-1.8Z" fill="currentColor"/></svg>
            <p><?php esc_html_e('Nakon što ste spojili pivo, odaberite broj pipe za objavu u aplikaciji.', 'beer-festival-tap'); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($category): ?><p class="bftl-beer-single-eyebrow"><?php echo esc_html($category); ?></p><?php endif; ?>
        <h1 class="bftl-beer-single-title"><?php echo esc_html($beer->post_title); ?></h1>

        <?php if ($brewer || $location): ?>
        <p class="bftl-beer-single-subline">
            <?php if ($brewer): ?><span><?php echo esc_html($brewer); ?></span><?php endif; ?>
            <?php if ($brewer && $location): ?><span class="bftl-beer-single-dot">&bull;</span><?php endif; ?>
            <?php if ($location): ?><span><?php echo esc_html($location); ?></span><?php endif; ?>
        </p>
        <?php endif; ?>

        <?php if ($ibu || $abv): ?>
        <p class="bftl-beer-single-stats">
            <?php if ($ibu): ?><span>IBU: <?php echo esc_html($ibu); ?></span><?php endif; ?>
            <?php if ($ibu && $abv): ?><span class="bftl-beer-single-dot">&bull;</span><?php endif; ?>
            <?php if ($abv): ?><span>Alc: <?php echo esc_html($abv); ?>% vol.</span><?php endif; ?>
        </p>
        <?php endif; ?>

        <div class="bftl-beer-single-taps">
            <?php foreach ($current_taps as $tap_id):
                $tc = isset($tap_categories[$tap_id]) ? $tap_categories[$tap_id] : '';
                $badge_label = ($tc && $tc !== Beer_Festival_Categories::DEFAULT_CATEGORY)
                    ? ($tap_id . ' - ' . $tc)
                    : (string) $tap_id;
            ?>
            <div class="bftl-tap-block">
                <p class="bftl-tap-block-label"><?php esc_html_e('Odabrana pipa', 'beer-festival-tap'); ?></p>
                <div class="bftl-tap-confirmed" data-category="<?php echo esc_attr($tc); ?>">
                    <?php echo esc_html($badge_label); ?>
                </div>
                <button type="button" class="bftl-tap-remove-btn" data-tap="<?php echo esc_attr($tap_id); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="5" width="14" height="14" rx="2" stroke="currentColor" stroke-width="2"/></svg>
                    <?php esc_html_e('Ukloni', 'beer-festival-tap'); ?>
                </button>
            </div>
            <?php endforeach; ?>

            <div class="bftl-tap-block" data-tap-assign-block>
                <p class="bftl-tap-block-label"><?php esc_html_e('Odaberi pipu', 'beer-festival-tap'); ?></p>
                <div class="bftl-tap-dropdown" data-tap-dropdown>
                    <button type="button" class="bftl-tap-dropdown-trigger" data-dropdown-trigger aria-haspopup="listbox" aria-expanded="false">
                        <span class="bftl-tap-dropdown-dot" data-dropdown-dot></span>
                        <span class="bftl-tap-dropdown-label" data-dropdown-label><?php esc_html_e('Odaberi pipu', 'beer-festival-tap'); ?></span>
                        <svg class="bftl-tap-dropdown-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <ul class="bftl-tap-dropdown-list" data-dropdown-list role="listbox" hidden>
                        <?php for ($i = 1; $i <= $tap_count; $i++):
                            $tc = isset($tap_categories[$i]) ? $tap_categories[$i] : '';
                            $label = ($tc && $tc !== Beer_Festival_Categories::DEFAULT_CATEGORY)
                                ? ($i . ' - ' . $tc)
                                : (string) $i;
                        ?>
                        <li class="bftl-tap-dropdown-option" data-value="<?php echo esc_attr($i); ?>" data-category="<?php echo esc_attr($tc); ?>" role="option">
                            <span class="bftl-tap-dropdown-dot"></span>
                            <?php echo esc_html($label); ?>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <a href="#" class="bftl-tap-cancel-link" data-tap-cancel><?php esc_html_e('Otkaži', 'beer-festival-tap'); ?></a>
                <button type="button" class="bftl-tap-publish-btn" data-tap-publish disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 4.5v15l13-7.5-13-7.5Z" fill="currentColor"/></svg>
                    <?php esc_html_e('Objavi', 'beer-festival-tap'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php wp_footer(); ?>
</body>
</html>
