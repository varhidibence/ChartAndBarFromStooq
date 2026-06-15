<?php

use NavigatorChart\Helpers\StockDataHelper;

function myplugin_render_stooq_cache_page() {
    // Each data type has its own option keys
    $datasets = [
        'ytd' => [
            'label' => 'YTD data',
            'cache_key' => 'navig_stooq_ytd_data',
            'timestamp_key' => 'navig_stooq_ytd_fetch',
            'refresh_method' => 'refreshYTDData',
        ],
        'last_month' => [
            'label' => 'Last month data',
            'cache_key' => 'navig_stooq_last_month_data',
            'timestamp_key' => 'navig_stooq_last_month_fetch',
            'refresh_method' => 'refreshLastMonthData',
        ],
        'last_half_year' => [
            'label' => 'Last half year data',
            'cache_key' => 'navig_stooq_last_half_year_data',
            'timestamp_key' => 'navig_stooq_last_half_year_fetch',
            'refresh_method' => 'refreshLastSixMonthData',
        ],
        'all' => [
            'label' => 'All data monthly',
            'cache_key' => 'navig_stooq_all_data',
            'timestamp_key' => 'navig_stooq_all_fetch',
            'refresh_method' => 'refreshAllMonthlyData',
        ],
        'last_price' => [
            'label' => 'Last price (15 min cron)',
            'cache_key' => 'navig_stooq_last_price_data',
            'timestamp_key' => 'navig_stooq_last_price_fetch',
            'refresh_method' => 'refreshLastPrice',
        ],
    ];

    echo '<div class="wrap"><h1>Stooq cache</h1>';

    // Cron statusz
    $tz = wp_timezone_string();
    $next_all = wp_next_scheduled('navig_cron_refresh_all_data');
    $next_price = wp_next_scheduled('navig_cron_refresh_last_price');
    echo '<div class="card" style="max-width:600px;padding:12px;margin-bottom:16px;">';
    echo '<h3 style="margin-top:0;">WP Cron schedule</h3>';
    echo '<p><strong>All data refresh (daily):</strong> ';
    echo $next_all ? wp_date('Y.m.d. H:i:s', $next_all) . ' (' . esc_html($tz) . ')' : '<em>Not scheduled</em>';
    echo '</p>';
    echo '<p><strong>Last price refresh (every 15 min):</strong> ';
    echo $next_price ? wp_date('Y.m.d. H:i:s', $next_price) . ' (' . esc_html($tz) . ')' : '<em>Not scheduled</em>';
    echo '</p>';
    echo '</div>';

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

        if (isset($_POST[$refresh_key]) || isset($_POST[$clear_key]) || isset($_POST[$save_key])) {
            check_admin_referer('stooq_cache_action');
        }

        if (isset($_POST[$refresh_key])) {
            if (class_exists('\NavigatorChart\Helpers\StockDataHelper') &&
                method_exists('\NavigatorChart\Helpers\StockDataHelper', $info['refresh_method'])) {
                call_user_func(['\NavigatorChart\Helpers\StockDataHelper', $info['refresh_method']]);
            }
            $fresh = get_option($cache_key);
            if ($fresh) {
                echo '<div class="updated"><p><strong>' . esc_html($label) . ' updated</strong></p></div>';
            } else {
                echo '<div class="error"><p><strong>' . esc_html($label) . ':</strong> API call failed, no new data.</p></div>';
            }
        } elseif (isset($_POST[$clear_key])) {
            delete_option($cache_key);
            delete_option($timestamp_key);
            $data = null;
            echo '<div class="updated"><p><strong>' . esc_html($label) . ' cleared!</strong></p></div>';
        } elseif (isset($_POST[$save_key])) {
            $new_data = wp_unslash($_POST[$textarea_name]);
            $decoded = json_decode($new_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                update_option($cache_key, $new_data);
                $ttl = ($key === 'last_price') ? 900 : DAY_IN_SECONDS;
                update_option($timestamp_key, time() + $ttl);
                $data = $new_data;
                echo '<div class="updated"><p><strong>' . esc_html($label) . ' JSON data saved!</strong></p></div>';
            } else {
                $data = get_option($cache_key);
                echo '<div class="error"><p><strong>Error:</strong> Not valid JSON (' . esc_html($label) . ').</p></div>';
            }
        }
        
    }

    // Clear log handling
    if (isset($_POST['clear_stooq_log'])) {
        check_admin_referer('stooq_cache_action');
        StockDataHelper::clearLog();
        echo '<div class="updated"><p><strong>Log cleared!</strong></p></div>';
    }

    // Tab navigation
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($datasets as $key => $info) {
        echo '<a href="#tab-' . esc_attr($key) . '" class="nav-tab" id="tablink-' . esc_attr($key) . '">' . esc_html($info['label']) . '</a>';
    }
    echo '<a href="#tab-log" class="nav-tab" id="tablink-log">Log</a>';
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
            $expires_label = (time() > $last_fetch) ? ' (expired)' : '';
            if ($last_fetched_at) {
                echo '<p><strong>Last fetched:</strong> ' . wp_date('Y.m.d. H:i:s', $last_fetched_at) . ' (' . esc_html($tz) . ')</p>';
            }
            echo '<p><strong>Cache expires:</strong> ' . wp_date('Y.m.d. H:i:s', $last_fetch) . ' (' . esc_html($tz) . ')' . $expires_label . '</p>';
        } else {
            echo '<p><em>No data fetched yet.</em></p>';
        }

        // Buttons
        echo '<form method="post" style="margin-bottom:1em;">';
        wp_nonce_field('stooq_cache_action');
        echo '<button class="button button-primary" name="refresh_data_' . esc_attr($key) . '">Refresh now</button> ';
        echo '<button class="button" name="clear_data_' . esc_attr($key) . '">Clear cache</button>';
        echo '</form>';

        // JSON editor
        echo '<h3>' . esc_html($label) . ' JSON</h3>';
        if ($data) {
            $decoded = json_decode($data, true);
            $pretty = (json_last_error() === JSON_ERROR_NONE)
                ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : $data;

            echo '<form method="post">';
            wp_nonce_field('stooq_cache_action');
            echo '<textarea name="json_data_' . esc_attr($key) . '" style="width:100%;height:400px;font-family:monospace;">' . esc_textarea($pretty) . '</textarea>';
            echo '<p><button class="button button-primary" name="save_json_data_' . esc_attr($key) . '">Save</button></p>';
            echo '</form>';
        } else {
            echo '<p>No cached data.</p>';
            echo '<form method="post">';
            wp_nonce_field('stooq_cache_action');
            echo '<textarea name="json_data_' . esc_attr($key) . '" placeholder="Paste JSON here..." style="width:100%;height:300px;font-family:monospace;"></textarea>';
            echo '<p><button class="button button-primary" name="save_json_data_' . esc_attr($key) . '">Save</button></p>';
            echo '</form>';
        }

        echo '</div>';
    }

    // Log tab
    echo '<div id="tab-log" class="tab-content" style="display:none;">';
    $logs = StockDataHelper::getLog();
    echo '<form method="post" style="margin-bottom:1em;">';
    wp_nonce_field('stooq_cache_action');
    echo '<button class="button" name="clear_stooq_log">Clear log</button>';
    echo '</form>';
    if (empty($logs)) {
        echo '<p><em>No log entries.</em></p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Message</th></tr></thead><tbody>';
        $tz = wp_timezone_string();
        foreach ($logs as $entry) {
            $time = wp_date('Y.m.d. H:i:s', $entry['time']) . ' (' . esc_html($tz) . ')';
            $msg = esc_html($entry['message']);
            $is_error = (strpos($entry['message'], 'OK') === false);
            $color = $is_error ? 'color:#d63638;' : 'color:#00a32a;';
            echo '<tr><td>' . $time . '</td><td style="' . $color . '">' . $msg . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';

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