<?php

use NavigatorChart\Helpers\StockDataHelper;

function myplugin_render_stooq_cache_page() {
    // Each data type has its own option keys
    $datasets = [
        'ytd' => [
            'label' => 'YTD data',
            'cache_key' => 'navig_stooq_ytd_data',
            'timestamp_key' => 'navig_stooq_ytd_fetch',
            'fetch_method' => 'GetCachedYTDData',
        ],
        'last_month' => [
            'label' => 'Last month data',
            'cache_key' => 'navig_stooq_last_month_data',
            'timestamp_key' => 'navig_stooq_last_month_fetch',
            'fetch_method' => 'GetCachedLastMonthData',
        ],
        'last_half_year' => [
            'label' => 'Last half year data',
            'cache_key' => 'navig_stooq_last_half_year_data',
            'timestamp_key' => 'navig_stooq_last_half_year_fetch',
            'fetch_method' => 'GetCachedLastSixMonthData',
        ],
        'all' => [
            'label' => 'All data weekly',
            'cache_key' => 'navig_stooq_all_data',
            'timestamp_key' => 'navig_stooq_all_fetch',
            'fetch_method' => 'GetCachedAllData',
        ],
    ];

    echo '<div class="wrap"><h1>Stooq cache</h1>';

    foreach ($datasets as $key => $info) {
        $cache_key = $info['cache_key'];
        $timestamp_key = $info['timestamp_key'];
        $label = $info['label'];

        // Detect which section's form was submitted
        $action_prefix = strtoupper($key);
        $refresh_key = "refresh_data_$key";
        $clear_key = "clear_data_$key";
        $save_key = "save_json_data_$key";
        $textarea_name = "json_data_$key";

        if (isset($_POST[$refresh_key])) {
            delete_option($cache_key);
            delete_option($timestamp_key);
            if (class_exists('\NavigatorChart\Helpers\StockDataHelper') &&
                method_exists('\NavigatorChart\Helpers\StockDataHelper', $info['fetch_method'])) {
                $data = call_user_func(['\NavigatorChart\Helpers\StockDataHelper', $info['fetch_method']]);
            } else {
                $data = '{"error": "Fetch method not found"}';
            }
            echo '<div class="updated"><p><strong>' . esc_html($label) . ' updated</strong></p></div>';
        } elseif (isset($_POST[$clear_key])) {
            delete_option($cache_key);
            delete_option($timestamp_key);
            $data = null;
            echo '<div class="updated"><p><strong>' . esc_html($label) . ' cleared!</strong></p></div>';
        } elseif (isset($_POST[$save_key])) {
            $new_data = stripslashes($_POST[$textarea_name]);
            $decoded = json_decode($new_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                update_option($cache_key, wp_unslash($new_data));
                update_option($timestamp_key, time());
                $data = $new_data;
                echo '<div class="updated"><p><strong>' . esc_html($label) . ' JSON data saved!</strong></p></div>';
            } else {
                $data = get_option($cache_key);
                echo '<div class="error"><p><strong>Hiba:</strong> Not valid JSON (' . esc_html($label) . ').</p></div>';
            }
        } else {
            $data = get_option($cache_key);
        }

        $last_fetch = get_option($timestamp_key);

        // Section header
        echo '<hr><h2>' . esc_html($label) . '</h2>';

        if ($last_fetch) {
            echo '<p><strong>Last fetch:</strong> ' . date('Y.m.d. H:i:s', $last_fetch) . '</p>';
        } else {
            echo '<p><em>No data fetched.</em></p>';
        }

        // Buttons
        echo '<form method="post" style="margin-bottom:1em;">';
        echo '<button class="button button-primary" name="' . esc_attr($refresh_key) . '">Fetch now</button> ';
        echo '<button class="button" name="' . esc_attr($clear_key) . '">Clear cache</button>';
        echo '</form>';

        // JSON editor
        if ($data) {
            $decoded = json_decode($data, true);
            $pretty = (json_last_error() === JSON_ERROR_NONE)
                ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : $data;

            echo '<form method="post">';
            echo '<textarea name="' . esc_attr($textarea_name) . '" style="width:100%;height:400px;font-family:monospace;">' . esc_textarea($pretty) . '</textarea>';
            echo '<p><button class="button button-primary" name="' . esc_attr($save_key) . '">Save data</button></p>';
            echo '</form>';
        } else {
            echo '<p>Nincs mentett adat.</p>';
            echo '<form method="post">';
            echo '<textarea name="' . esc_attr($textarea_name) . '" placeholder="Put JSON data here..." style="width:100%;height:300px;font-family:monospace;"></textarea>';
            echo '<p><button class="button button-primary" name="' . esc_attr($save_key) . '">Save</button></p>';
            echo '</form>';
        }
    }

    echo '</div>';
}

add_action('admin_menu', function() {
    add_menu_page(
        'Stooq cache data',     // Page title (browser tab)
        'Stooq cache',            // Menu title (left sidebar)
        'manage_options',               // Capability
        'stooq-data-cache',             // Menu slug
        'myplugin_render_stooq_cache_page', // Callback
        'dashicons-database',           // Icon
        80                              // Position
    );
});