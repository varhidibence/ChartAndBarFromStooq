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
            'label' => 'All data monthly',
            'cache_key' => 'navig_stooq_all_data',
            'timestamp_key' => 'navig_stooq_all_fetch',
            'fetch_method' => 'GetCachedAllData',
        ],
        'last_price' => [
            'label' => 'Last price (15 min cache)',
            'cache_key' => 'navig_stooq_last_price_data',
            'timestamp_key' => 'navig_stooq_last_price_fetch',
            'fetch_method' => 'GetCachedLastPrice',
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
                $ttl = ($key === 'last_price') ? 900 : DAY_IN_SECONDS;
                update_option($timestamp_key, time() + $ttl);
                $data = $new_data;
                echo '<div class="updated"><p><strong>' . esc_html($label) . ' JSON data saved!</strong></p></div>';
            } else {
                $data = get_option($cache_key);
                echo '<div class="error"><p><strong>Hiba:</strong> Not valid JSON (' . esc_html($label) . ').</p></div>';
            }
        }
        
    }

    // Tab navigation
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($datasets as $key => $info) {
        echo '<a href="#tab-' . esc_attr($key) . '" class="nav-tab" id="tablink-' . esc_attr($key) . '">' . esc_html($info['label']) . '</a>';
    }
    echo '</h2>';

    // Tab content panels
    foreach ($datasets as $key => $info) {
        $cache_key = $info['cache_key'];
        $timestamp_key = $info['timestamp_key'];
        $label = $info['label'];

        $data = get_option($cache_key);
        $last_fetch = get_option($timestamp_key);

        echo '<div id="tab-' . esc_attr($key) . '" class="tab-content" style="display:none;">';

        $last_fetched_at = get_option($timestamp_key . '_last');
        if ($last_fetch) {
            $tz = wp_timezone_string();
            $expires_label = (time() > $last_fetch) ? ' (lejárt)' : '';
            if ($last_fetched_at) {
                echo '<p><strong>Utolsó lekérés:</strong> ' . wp_date('Y.m.d. H:i:s', $last_fetched_at) . ' (' . esc_html($tz) . ')</p>';
            }
            echo '<p><strong>Cache lejárat:</strong> ' . wp_date('Y.m.d. H:i:s', $last_fetch) . ' (' . esc_html($tz) . ')' . $expires_label . '</p>';
        } else {
            echo '<p><em>Még nincs adat lekérve.</em></p>';
        }

        // Buttons
        echo '<form method="post" style="margin-bottom:1em;">';
        echo '<button class="button button-primary" name="refresh_data_' . esc_attr($key) . '">Frissítés most</button> ';
        echo '<button class="button" name="clear_data_' . esc_attr($key) . '">Gyorsítótár törlése</button>';
        echo '</form>';

        // JSON editor
        echo '<h3>' . esc_html($label) . ' JSON</h3>';
        if ($data) {
            $decoded = json_decode($data, true);
            $pretty = (json_last_error() === JSON_ERROR_NONE)
                ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : $data;

            echo '<form method="post">';
            echo '<textarea name="json_data_' . esc_attr($key) . '" style="width:100%;height:400px;font-family:monospace;">' . esc_textarea($pretty) . '</textarea>';
            echo '<p><button class="button button-primary" name="save_json_data_' . esc_attr($key) . '">Mentés</button></p>';
            echo '</form>';
        } else {
            echo '<p>Nincs mentett adat.</p>';
            echo '<form method="post">';
            echo '<textarea name="json_data_' . esc_attr($key) . '" placeholder="Ide illesztheted be a JSON-t..." style="width:100%;height:300px;font-family:monospace;"></textarea>';
            echo '<p><button class="button button-primary" name="save_json_data_' . esc_attr($key) . '">Mentés</button></p>';
            echo '</form>';
        }

        echo '</div>';
    }

    // JavaScript for tab switching
    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const tabs = document.querySelectorAll(".nav-tab");
        const panels = document.querySelectorAll(".tab-content");

        function showTab(id) {
            panels.forEach(p => p.style.display = "none");
            tabs.forEach(t => t.classList.remove("nav-tab-active"));
            document.getElementById("tab-" + id).style.display = "block";
            document.getElementById("tablink-" + id).classList.add("nav-tab-active");
            localStorage.setItem("stooqActiveTab", id);
        }

        // Restore last open tab
        const saved = localStorage.getItem("stooqActiveTab") || "ytd";
        showTab(saved);

        tabs.forEach(tab => {
            tab.addEventListener("click", e => {
                e.preventDefault();
                showTab(tab.id.replace("tablink-", ""));
            });
        });
    });
    </script>
    ';

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