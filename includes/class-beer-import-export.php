<?php
if (!defined('ABSPATH')) exit;

/**
 * CSV export/import for beer posts. The export is a UTF-8 CSV with a BOM so
 * Excel reads accented names correctly. Import still accepts .xls-named files
 * because earlier versions exported CSV content under that extension.
 */
class Beer_Festival_Import_Export {

    const MAX_IMPORT_ROWS = 2000;

    public static function export_headers() {
        return ['Beer Name', 'Style', 'Category', 'Brewer', 'Location', 'ABV', 'IBU', 'QR Image'];
    }

    /**
     * File name of a beer's QR image in the "Download all as ZIP" bundle. The
     * id keeps it unique and stable even if two beers share a name or one is
     * renamed. Shared by the export column and the QR Codes page so the CSV
     * always points at the files the ZIP contains.
     */
    public static function qr_filename($beer) {
        $slug = sanitize_title($beer->post_title);
        return ($slug !== '' ? $slug : 'beer') . '-' . intval($beer->ID) . '.png';
    }

    // Folder the user will unzip the QR images into, normalised to end with a
    // separator so it can simply be put in front of the file name. Layout
    // software (e.g. Affinity Publisher data merge) needs the full file path.
    public static function normalize_qr_folder($folder) {
        $folder = trim((string) $folder);
        if ($folder === '') {
            return '';
        }
        $last = substr($folder, -1);
        if ($last === '/' || $last === '\\') {
            return $folder;
        }
        return $folder . (strpos($folder, '\\') !== false ? '\\' : '/');
    }

    public static function build_export_row($beer, $qr_folder = '') {
        return [
            $beer->post_title,
            get_post_meta($beer->ID, '_beer_stil', true),
            get_post_meta($beer->ID, '_beer_category', true),
            get_post_meta($beer->ID, '_beer_brewer', true),
            get_post_meta($beer->ID, '_beer_location', true),
            get_post_meta($beer->ID, '_beer_abv', true),
            get_post_meta($beer->ID, '_beer_ibu', true),
            $qr_folder . self::qr_filename($beer),
        ];
    }

    // Prefixes a leading =, +, -, or @ so spreadsheet apps never treat an
    // exported free-text value (brewer/location/style) as a formula.
    private static function guard_formula_injection($value) {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }
        return $value;
    }

    public static function stream_csv_export($qr_folder = '') {
        $qr_folder = self::normalize_qr_folder($qr_folder);
        $beers = get_posts([
            'post_type'    => 'beer',
            'post_status'  => 'publish',
            'numberposts'  => -1,
            'orderby'      => 'title',
            'order'        => 'ASC',
        ]);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="beers-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accented names correctly.

        fputcsv($out, self::export_headers());
        foreach ($beers as $beer) {
            fputcsv($out, array_map([__CLASS__, 'guard_formula_injection'], self::build_export_row($beer, $qr_folder)));
        }
        fclose($out);
        exit;
    }

    /**
     * Reads an uploaded CSV directly from its temp path (deliberately not
     * wp_handle_upload() -- a re-imported .xls-named CSV export would fail
     * WordPress's real-content-vs-extension sniffing, and the file is only
     * needed transiently here, not stored in the Media Library).
     */
    public static function parse_csv_file($tmp_path) {
        if (!is_readable($tmp_path)) {
            return new WP_Error('bftl_import_unreadable', __('Uploaded file could not be read.', 'beer-festival-tap'));
        }

        $handle = fopen($tmp_path, 'r');
        if (!$handle) {
            return new WP_Error('bftl_import_unreadable', __('Uploaded file could not be read.', 'beer-festival-tap'));
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if ($headers === false || $headers === null) {
            fclose($handle);
            return new WP_Error('bftl_import_empty', __('The file appears to be empty.', 'beer-festival-tap'));
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($rows) >= self::MAX_IMPORT_ROWS) {
                break;
            }
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue; // blank line
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    // Best-effort pre-selection for the column-mapping screen.
    public static function guess_column_mapping(array $headers) {
        $aliases = [
            'beer_name' => ['beer name', 'name', 'title'],
            'stil'      => ['style', 'beer style'],
            'category'  => ['category'],
            'brewer'    => ['brewer', 'brewer name'],
            'location'  => ['location', 'brewer location'],
            'abv'       => ['abv'],
            'ibu'       => ['ibu'],
        ];

        $mapping = [];
        foreach ($aliases as $field => $names) {
            $mapping[$field] = '';
            foreach ($headers as $index => $header) {
                if (in_array(strtolower(trim((string) $header)), $names, true)) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }
        return $mapping;
    }

    // Existing beer titles (lower-cased) -> post ID, built once per import run.
    public static function build_title_map() {
        $ids = get_posts([
            'post_type'   => 'beer',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);

        $map = [];
        foreach ($ids as $beer_id) {
            $map[strtolower(get_the_title($beer_id))] = $beer_id;
        }
        return $map;
    }

    public static function import_row($mapping, array $row, array &$title_map, $row_number) {
        $cell = function ($field) use ($mapping, $row) {
            if (!isset($mapping[$field]) || $mapping[$field] === '') {
                return '';
            }
            $index = intval($mapping[$field]);
            return isset($row[$index]) ? trim((string) $row[$index]) : '';
        };

        $beer_name = sanitize_text_field($cell('beer_name'));
        if ($beer_name === '') {
            return [
                'row'       => $row_number,
                'beer_name' => '',
                'action'    => 'error',
                'message'   => __('Missing beer name — row skipped.', 'beer-festival-tap'),
            ];
        }

        $key = strtolower($beer_name);
        $is_new = !isset($title_map[$key]);

        if ($is_new) {
            $post_id = wp_insert_post([
                'post_type'   => 'beer',
                'post_status' => 'publish',
                'post_title'  => $beer_name,
            ], true);
            if (is_wp_error($post_id)) {
                return [
                    'row'       => $row_number,
                    'beer_name' => $beer_name,
                    'action'    => 'error',
                    'message'   => $post_id->get_error_message(),
                ];
            }
            $title_map[$key] = $post_id;
        } else {
            $post_id = $title_map[$key];
        }

        $category = $cell('category');
        $category_note = '';
        if ($category !== '' && !in_array($category, Beer_Festival_Categories::get_selectable(false), true)) {
            $added = Beer_Festival_Categories::add($category);
            if (is_wp_error($added) && $added->get_error_code() !== 'bftl_category_exists') {
                /* translators: %s: category name */
                $category_note = ' ' . sprintf(__('Category "%s" could not be created and was left blank.', 'beer-festival-tap'), $category);
                $category = '';
            }
        }

        Beer_CPT::sanitize_and_save_beer_meta($post_id, [
            'stil'     => $cell('stil'),
            'brewer'   => $cell('brewer'),
            'location' => $cell('location'),
            'ibu'      => $cell('ibu'),
            'abv'      => $cell('abv'),
            'category' => $category,
        ]);

        return [
            'row'       => $row_number,
            'beer_name' => $beer_name,
            'action'    => $is_new ? 'created' : 'updated',
            'message'   => trim(($is_new ? __('Created.', 'beer-festival-tap') : __('Updated.', 'beer-festival-tap')) . $category_note),
        ];
    }
}
