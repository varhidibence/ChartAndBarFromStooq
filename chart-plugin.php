<?php
/*
Plugin Name: NAVIGATOR stock rate chart plugin
Description: A simple plugin that shows a chart with Chart.js.
Version: 1.0
Author: Bence Várhidi
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

function fetchLastMonthData(){
    $ticker = 'navigator.hu';
    $today = date('Ymd');
    $oneMonthAgo = date('Ymd', strtotime('-1 month'));
    $interval = 'd';     // napi

    $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneMonthAgo}&d2={$today}&i={$interval}";

    wp_add_inline_script('chartjs', 'console.log("Fetching data (Last month):", ' . json_encode($url) . ');');

    // Lekérjük a CSV adatot
    $csv_data = StockDataHelper::fetchUrl($url);
    if ($csv_data === false) {
        die("Nem sikerült lekérni az adatokat. url: {$url}");
    }

    //wp_add_inline_script('chartjs', 'console.log("Fetched CSV data:", ' . json_encode($csv_data) . ');');
    $json_data = StockDataHelper::csv_to_json($csv_data);

    //wp_add_inline_script('chartjs', 'console.log("Fetched CSV json_data:", ' . json_encode($json_data) . ');');
    return $json_data;
}

function fetchLastSixMonthData(){
    $ticker = 'navigator.hu';
    $today = date('Ymd');
    $halfYearAgo = date('Ymd', strtotime('-6 month'));
    $interval = 'd';     // napi

    $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$halfYearAgo}&d2={$today}&i={$interval}";

    wp_add_inline_script('chartjs', 'console.log("Fetching data (Half year)):", ' . json_encode($url) . ');');
    $csv_data = StockDataHelper::fetchUrl($url);
    if ($csv_data === false) {
        die("Nem sikerült lekérni az adatokat. url: {$url}");
    }

    $json_data = StockDataHelper::csv_to_json($csv_data);

    return $json_data;
}

function fetchAllMonthlyData(){
    $ticker = 'navigator.hu';
    $end = date('Ymd');
    $interval = 'w';     // weekly

    $url = "https://stooq.com/q/d/l/?s={$ticker}&d2={$end}&i={$interval}";

    wp_add_inline_script('chartjs', 'console.log("Fetching data (All):", ' . json_encode($url) . ');');
    
    $csv_data = StockDataHelper::fetchUrl($url);
    if ($csv_data === false) {
        die("Nem sikerült lekérni az adatokat. url: {$url}");
    }
    $json_data = StockDataHelper::csv_to_json($csv_data);

    return $json_data;
}

function fetchYTDData(){
    $ticker = 'navigator.hu';
    $firstDayOfYear = date('Y0101');  
    $end = date('Ymd');  // pl. mai nap
    $interval = 'd';     // havi

    $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$firstDayOfYear}&d2={$end}&i={$interval}";
    wp_add_inline_script('chartjs', 'console.log("Fetching data (YTD):", ' . json_encode($url) . ');');
    
    $csv_data = StockDataHelper::fetchUrl($url);
    //die("Nincsen megjelníthető adat");
    if ($csv_data === false) {
        die("Nem sikerült lekérni az adatokat. url: {$url}");
    }
    $json_data = StockDataHelper::csv_to_json($csv_data);

    return $json_data;
}

// Shortcode function
function my_huf_chart_shortcode() {

    $lastMonthData = fetchLastMonthData();
    $dataFromBeginning = fetchAllMonthlyData();
    $YTDData = fetchYTDData();
    $lastHalfYearData = fetchLastSixMonthData();

    $latestStockData = StockDataHelper::getLastPriceWithDate();
    $changePct = StockDataHelper::getChangeOfLastTwoDays();
    
    $latestPrice = $latestStockData["close"] ?? "-";
    $latestDate = $latestStockData["date"] ?? "-";
    $formattedDate = $latestDate != null ? date('Y.m.d', strtotime($latestDate)) : "-";
    $latestTime = $latestStockData["time"] ?? "-";
    
    
    $arrow = $changePct == 0 ? "-" : ($changePct > 0 ? "▲" : "▼");
    $class = $changePct == 0 ? "unchanged" : ($changePct > 0 ? "up" : "down");
    // Format with exactly one decimal place
    $formattedChange = number_format($changePct, 2);
    $signChange = $changePct == 0 ? "" : ($changePct > 0 ? "+" : "");

    ob_start(); ?>

    <div class="container my-4">
        <div class="row align-items-start">
            <!-- Table -->
            <div class="navigator-table col-12 col-md-4 mb-3 mb-md-0">
                <div class="table-main-data">
                    <span class='price'><?php echo esc_html($latestPrice); ?> HUF </span>
                    <span class='navi-tooltiptext'>
                        <?php echo esc_html($formattedDate); ?> 
                        <?php echo esc_html($latestTime); ?>
                    </span>

                    <span class='change {$class}'><?php echo esc_html($arrow); ?></span>
                    <span class='changepercent'> 
                        <?php echo esc_html($signChange); ?>
                        <?php echo esc_html($formattedChange); ?> %
                    </span>
                </div>
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                    </thead>
                        <tbody>
                            <tr><td>Adat 1</td><td>123</td></tr>
                            <tr><td>Adat 2</td><td>456</td></tr>
                            <tr><td>Adat 3</td><td>789</td></tr>
                            <tr><td>Adat 4</td><td>234</td></tr>
                            <tr><td>Adat 5</td><td>567</td></tr>
                            <tr><td>Adat 6</td><td>890</td></tr>
                        </tbody>
                </table>
            </div>

            <!-- Chart -->
            <div class="col-12 col-md-8">
                <div class="chart-container">
                    <canvas id="hufChart" width="400" height="200"></canvas>
                    <div class="chart-controls">
                        <button id="btn-month">Utolsó hónap</button>
                        <button id="btn-half-year">6 hónap</button>
                        <button id="btn-year" class="active">Éves</button>
                        <button id="btn-all">Teljes</button>
                    </div>
                </div>
            </div>
    </div>
    
    <script>

        console.log("NAVIGATOR HUF Chart plugin was loaded ✅");

        document.addEventListener("DOMContentLoaded", function () {
            const lastMonthData = <?php echo $lastMonthData; ?>;
            const labelsLastMonth = lastMonthData.map(row => row.Date);
            const openPricesLastMonth = lastMonthData.map(row => parseFloat(row.Close));
            const closePricesLastMonth = lastMonthData.map(row => parseFloat(row.Close));

            const dataFromBeginning = <?php echo $dataFromBeginning; ?>;
            const labelsAll = dataFromBeginning.map(row => row.Date);
            const openPricesAll = dataFromBeginning.map(row => parseFloat(row.Close));
            const closePricesAll = dataFromBeginning.map(row => parseFloat(row.Close));

            const YTDData = <?php echo $YTDData; ?>;
            const labelsYTD = YTDData.map(row => row.Date);
            const openPricesYTD = YTDData.map(row => parseFloat(row.Close));
            const closePricesYTD = YTDData.map(row => parseFloat(row.Close));

            const lastHalfYearData = <?php echo $lastHalfYearData; ?>;
            const labelsHalfYear = lastHalfYearData.map(row => row.Date);
            const openPricesHalfYear = lastHalfYearData.map(row => parseFloat(row.Close));
            const closePricesHalfYear = lastHalfYearData.map(row => parseFloat(row.Close));

            const lineColor = '#254867';

            const dataLastMonth = {
                    labels:  labelsLastMonth, //['2025-09-20', '2025-09-21', '2025-09-22', '2025-09-23'],
                    datasets: [
                        {
                            label: 'Záró ár (Close)',
                            data: closePricesLastMonth,
                            backgroundColor: lineColor,
                            borderColor: lineColor,
                            borderWidth: 1
                        }
                    ]
            };

            const dataAll = {
                    labels:  labelsAll,
                    datasets: [
                        {
                            label: 'Záró ár (Close)',
                            data: closePricesAll,
                            backgroundColor: lineColor,
                            borderColor: lineColor,
                            borderWidth: 1
                        }
                    ]
            };

            const dataYTD = {
                    labels:  labelsYTD,
                    datasets: [
                        {
                            label: 'Záró ár (Close)',
                            data: closePricesYTD,
                            backgroundColor: lineColor,
                            borderColor: lineColor,
                            borderWidth: 1
                        }
                    ]
            };

            const dataHalfYear = {
                    labels:  labelsHalfYear,
                    datasets: [
                        {
                            label: 'Záró ár (Close)',
                            data: closePricesHalfYear,
                            backgroundColor: lineColor,
                            borderColor: lineColor,
                            borderWidth: 1
                        }
                    ]
            };

            const ctx = document.getElementById('hufChart').getContext('2d');
            const priceChart = new Chart(ctx, {
                type: 'line',
                data: dataYTD,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'NAVIG árfolyam adatok (HUF)'
                        },
                    },
                    interaction: {
                        intersect: false,
                    },
                    scales: {
                        x: {
                            type: 'category',
                            ticks: {
                                font: {
                                    weight: 'bold'
                                },
                                autoSkip: true,
                                maxTicksLimit: 6 // show only ~10 labels at most
                            }
                        },
                        y: {
                            beginAtZero: false,
                            ticks: {
                                font: {
                                    weight: 'bold'
                                },
                                callback: function(value) {
                                    return value.toLocaleString("hu-HU") + " Ft";
                                }
                            }
                        }
                    }
                }
            });

            Chart.defaults.backgroundColor = 'rgba(49, 64, 64, 1)'; // '#314040ff';


            const formatFullDate = function(value) {
                return this.getLabelForValue(value); // full date
            };
            const formatMonthOnly = function(value, index, ticks) {
                // get the actual label value from the chart
                const label = this.getLabelForValue(value);
                if (typeof label === 'string' && label.includes('-')) {
                    const [year, month] = label.split('-');
                    return `${year}-${month}`;
                }
                return label;
            };+

            document.getElementById('btn-month').addEventListener('click', () => {
                priceChart.data = dataLastMonth;
                priceChart.options.scales.x.type = 'category'; // make sure still category
                priceChart.options.scales.x.ticks.callback = formatFullDate;
                priceChart.update('active'); // force re-render ticks
            });

            document.getElementById('btn-year').addEventListener('click', () => {
                priceChart.data = dataYTD;
                priceChart.options.scales.x.ticks.callback = formatMonthOnly;
                priceChart.options.scales.x.type = 'category';
                priceChart.update('active');
            });

               document.getElementById('btn-half-year').addEventListener('click', () => {
                priceChart.data = dataHalfYear;
                priceChart.options.scales.x.ticks.callback = formatMonthOnly;
                priceChart.options.scales.x.type = 'category';
                priceChart.update('active');
            });

               document.getElementById('btn-all').addEventListener('click', () => {
                priceChart.data = dataAll;
                priceChart.options.scales.x.ticks.callback = formatMonthOnly;
                priceChart.options.scales.x.type = 'category';
                priceChart.update('active');
            });
        });

        document.querySelectorAll('.chart-controls button').forEach(btn => {
            btn.addEventListener('click', e => {
                document.querySelectorAll('.chart-controls button').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
            });
        });


    </script>

    <?php
    return ob_get_clean();
}


function navigator_chart_styles() {
      echo '<style>

        .navigator-table {
            font-family: arial, sans-serif;
            font-weight: bold;
        }
        .table-main-data {
            display: flex;
            justify-content: center;
            padding: 10px;

        }
        
        .chart-controls {
            display: flex;
            justify-content: end;
            gap: 8px;
            margin-top: 12px;
        }   

        .chart-controls button {
            padding: 8px;
            border: none;
            border-radius: 2px;
            background: #828282;
            font-family: cardo;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.5s, transform 0.1s;
        }
        .chart-controls button:hover {
            background-color: #20304f; /* darker on hover */
            transform: translateY(-2px);
        }

        .chart-controls button:active {
            background-color: #004a70; /* even darker when clicked */
            transform: translateY(0);
        }

        .chart-controls button.active {
            background-color: #004a70; /* highlighted active state */
        }
    </style>';
}


add_action('wp_head', 'navigator_chart_styles');

add_shortcode( 'my_chart', 'my_huf_chart_shortcode' );