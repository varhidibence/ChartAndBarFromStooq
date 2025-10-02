<?php
/*
Plugin Name: NAVIGATOR stock rate chart plugin
Description: A simple plugin that shows a chart with Chart.js.
Version: 1.0
Author: Benve Várhidi
*/

require_once WP_PLUGIN_DIR . '/chart-plugin/StockDataHelper.php';

use NavigatorChart\Helpers\StockDataHelper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Enqueue Chart.js
function my_huf_chart_enqueue_scripts() {
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        array(),
        null,
        true
    );
    
}
add_action( 'wp_enqueue_scripts', 'my_huf_chart_enqueue_scripts' );

// Shortcode function
function my_huf_chart_shortcode() {
    // Beállítások
    $ticker = 'navigator.hu';
    $start = '20250901';
    $end = date('Ymd');  // pl. mai nap
    $interval = 'd';     // napi

    $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$start}&d2={$end}&i={$interval}";
    //$url = "https://stooq.com/q/?s=navigator.hu&c=1d&t=l&a=lg&b=0";

    wp_add_inline_script('chartjs', 'console.log("Fetching data from this url:", ' . json_encode($url) . ');');

    // Lekérjük a CSV adatot
    $csv_data = StockDataHelper::fetchUrl($url);
    if ($csv_data === false) {
        die("Nem sikerült lekérni az adatokat. url: {$url}");
    }

    wp_add_inline_script('chartjs', 'console.log("Fetched CSV data:", ' . json_encode($csv_data) . ');');
    $json_data = StockDataHelper::csv_to_json($csv_data);

    //wp_add_inline_script('chartjs', 'console.log("Fetched CSV json_data:", ' . json_encode($json_data) . ');');

    ob_start(); ?>
    
    <canvas id="hufChart" width="400" height="200"></canvas>
    <script>

        console.log("My HUF Chart plugin shortcode was loaded ✅");

        document.addEventListener("DOMContentLoaded", function () {
            const csvData = <?php echo $json_data; ?>;

            console.log("CSV Data:", csvData);

            const labels = csvData.map(row => row.Date);
            const openPrices = csvData.map(row => parseFloat(row.Close));
            const closePrices = csvData.map(row => parseFloat(row.Close));


            const ctx = document.getElementById('hufChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels:  labels, //['2025-09-20', '2025-09-21', '2025-09-22', '2025-09-23'],
                    datasets: [
                        {
                            label: 'Záró ár (Close)',
                            data: closePrices,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                                                {
                            label: 'Nyitó ár (Open)',
                            data: openPrices,
                            backgroundColor: 'rgba(235, 142, 54, 0.5)',
                            borderColor: 'rgba(211, 177, 23, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Navigator Investments árfolyam adatok (HUF)'
                        },
                    },
                    interaction: {
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString("hu-HU") + " Ft";
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode( 'my_chart', 'my_huf_chart_shortcode' );