<?php
/*
Plugin Name: NAVIGATOR stock rate chart and informational table plugin
Description: A plugin that shows a chart with Chart.js.
Version: 1.0
Author: Bence Várhidi
*/

require_once WP_PLUGIN_DIR . '/chart-plugin/StockDataHelper.php';
require_once plugin_dir_path(__FILE__) . 'stooq-admin-menu.php';

use NavigatorChart\Helpers\StockDataHelper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue Chart.js, Bootstrap
function my_huf_chart_enqueue_scripts() {
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        array(),
        null,
        true
    );

    wp_enqueue_style(
        'bootstrap-css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
    );
    wp_enqueue_script(
        'bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        array('jquery'),
        null,
        true
    );

      wp_enqueue_script(
        'navigator-fix',
        '/wp-content/plugins/chart-plugin/navigator-fix.js',
        array(), // no dependencies
        '1.0',
        true // load in footer
    );
    
}
add_action( 'wp_enqueue_scripts', 'my_huf_chart_enqueue_scripts' );

function getFormattedDate(array $lastFive, int $number): string {
    if (isset($lastFive[$number]['Date']) && !empty($lastFive[$number]['Date'])) {
        $timestamp = strtotime($lastFive[$number]['Date']);
        return $timestamp ? date('Y.m.d.', $timestamp) : '-';
    }
    return '-';
}

function getFormattedClose(array $lastFive, int $number): ?float {
    if (isset($lastFive[$number]['Close']) && is_numeric($lastFive[$number]['Close'])) {
        return round((float)$lastFive[$number]['Close'], 1);
    }
    return null;
}

// Shortcode function
function navigator_huf_chart_shortcode() {

    $lastMonthData = StockDataHelper::GetCachedLastMonthData();
    $lastMonthDataCSV = json_decode($lastMonthData, true);

    if ($lastMonthDataCSV === false){
        $lastMonthDataCSV = "";
    }
    $lastFive = StockDataHelper::get_last_rows_from_csv($lastMonthDataCSV, 5);
    wp_add_inline_script('chartjs', 'console.log("Fetching data (last 5):", ' . json_encode($lastFive) . ');');

    $dataFromBeginning = StockDataHelper::GetCachedAllData();
    $YTDData = StockDataHelper::GetCachedYTDData();
    $lastHalfYearData = StockDataHelper::GetCachedLastSixMonthData();

    $latestStockData = StockDataHelper::getLastPriceWithDate();
    $changePct = StockDataHelper::getChangeOfLastTwoDays();
    
    $latestPrice = $latestStockData["close"] ?? "-";
    $latestDate = $latestStockData["date"] ?? "-";
    $formattedDate = !empty($latestDate) && strtotime($latestDate)
        ? date('Y.m.d', strtotime($latestDate))
        : '-';
    $latestTime = $latestStockData["time"] ?? "-";

    $arrow = $changePct == 0 ? "-" : ($changePct > 0 ? "▲" : "▼");
    $class = $changePct == 0 ? "unchanged" : ($changePct > 0 ? "up" : "down");
    // Format with exactly one decimal place
    $formattedChange = number_format($changePct, 2);
    $signChange = $changePct == 0 ? "" : ($changePct > 0 ? "+" : "");

    ob_start(); ?>

    <div class="container my-4">
        <div class="navig-main-title display-3">NAVIGATOR árfolyam adatok</div>
        <div class="row align-items-start">
            <!-- Chart -->
            <div class="col-12 col-md-8 mt-3">
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

            <!-- Table -->
            <div class="navigator-table col-lg-4 mt-3">
                <div class="table-main card">
                    <div>
                        <div class='card-header pt-4 px-4 pb-0 border-0 d-flex align-items-center justify-content-between'>
                            <div>Aktuális árfolyam</div>    
                            <div>
                                <span class='change <?php echo $class; ?>'><?php echo esc_html($arrow); ?></span>
                                <?php echo esc_html($signChange);?>
                                <?php echo esc_html($formattedChange); ?> %
                            </div>
                        </div>
                        <div class='card-body d-flex justify-content-center'>
                            <div class='display-2 fw-bolder text-uppercase'>
                                <?php echo esc_html($latestPrice); ?> HUF
                            </div>
                        </div>
                        <div class="card-footer pb-4 px-4 pt-0 border-0 d-flex align-items-center justify-content-between">
                            <div>
                                <?php echo esc_html($formattedDate); ?> 
                                <?php echo esc_html($latestTime); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div>

                </div class="navig-rows">
                    <div class="navig-row card border-opacity-25 mt-2">
                        <div class="card-body py-3 d-flex justify-content-between rounded">
                            <div><?php echo getFormattedDate($lastFive, 4); ?> </div>
                            <div><?php echo getFormattedClose($lastFive, 4); ?> HUF</div>
                        </div>
                    </div>
                    <div class="navig-row card border-opacity-25 mt-2">
                        <div class="card-body py-3 d-flex justify-content-between">
                            <div><?php echo getFormattedDate($lastFive, 3); ?> </div>
                            <div><?php echo getFormattedClose($lastFive, 3); ?> HUF</div>
                        </div>   
                    </div>
                    <div class="navig-row card border-opacity-25 mt-2">
                        <div class="card-body py-3 d-flex justify-content-between">
                            <div><?php echo getFormattedDate($lastFive, 2); ?> </div>
                            <div><?php echo getFormattedClose($lastFive, 2); ?> HUF</div>
                        </div>   
                    </div>
                    <div class="navig-row card border-opacity-25 mt-2">
                        <div class="card-body py-3 d-flex justify-content-between">
                            <div><?php echo getFormattedDate($lastFive, 1); ?> </div>
                            <div><?php echo getFormattedClose($lastFive, 1); ?> HUF</div>
                        </div>   
                    </div>
                    <div class="navig-row card border-opacity-25 mt-2">
                        <div class="card-body py-3 d-flex justify-content-between">
                            <div><?php echo getFormattedDate($lastFive, 0); ?> </div>
                            <div><?php echo getFormattedClose($lastFive, 0); ?> HUF</div>
                        </div>   
                    </div>
                </div>
            </div>
    </div>
    
    <script>

        console.log("NAVIGATOR HUF Chart plugin by Bence Várhidi was loaded ✅");

        document.addEventListener("DOMContentLoaded", function () {

            const isMobile = window.matchMedia("(max-width: 768px)").matches;

            var pointRadius = 3;
            var pointHoverRadius = 4;
            if (isMobile) {
                pointRadius = 1;
                pointHoverRadius = 2;
            }

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
                            borderWidth: 1,
                            pointRadius: pointRadius,
                            pointHoverRadius: pointHoverRadius
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
                            borderWidth: 1,
                            pointRadius: pointRadius,
                            pointHoverRadius: pointHoverRadius
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
                            borderWidth: 1,
                            pointStyle: 'circle',
                            pointRadius: pointRadius,
                            pointHoverRadius: pointHoverRadius
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
                            borderWidth: 1,
                            pointRadius: pointRadius,
                            pointHoverRadius: pointHoverRadius
                        }
                    ]
            };

            const formatFullDate = function(value, index, ticks) {
                const label = this.getLabelForValue(value); // full date
                if (typeof label === 'string' && label.includes('-')) {
                    const [year, month, day] = label.split('-');
                    return `${year}.${month}.${day}.`;
                }
                return label;
            };
            const formatMonthOnly = function(value, index, ticks) {
                // get the actual label value from the chart
                const label = this.getLabelForValue(value);
                if (typeof label === 'string' && label.includes('-')) {
                    const [year, month] = label.split('-');
                    return `${year}.${month}.`;
                }
                return label;
            };

            const ctx = document.getElementById('hufChart').getContext('2d');
            const priceChart = new Chart(ctx, {
                type: 'line',
                data: dataYTD,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: false,
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
                                callback: formatMonthOnly,
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

            document.getElementById('btn-month').addEventListener('click', () => {
                priceChart.data = dataLastMonth;
                priceChart.options.scales.x.type = 'category'; // make sure still category
                priceChart.options.scales.x.ticks.callback = formatFullDate;

                if (isMobile) {
                    priceChart.data.datasets[0].pointRadius = 2;
                    priceChart.data.datasets[0].pointHoverRadius = 3;
                }
                else {
                    priceChart.data.datasets[0].pointRadius = 3;
                    priceChart.data.datasets[0].pointHoverRadius = 4;
                }
                priceChart.update('active'); // force re-render ticks
            });

            document.getElementById('btn-year').addEventListener('click', () => {
                priceChart.data = dataYTD;
                priceChart.options.scales.x.ticks.callback = formatMonthOnly;
                priceChart.options.scales.x.type = 'category';

                if (isMobile) {
                    priceChart.data.datasets[0].pointRadius = 2;
                    priceChart.data.datasets[0].pointHoverRadius = 3;
                }
                else {
                    priceChart.data.datasets[0].pointRadius = 3;
                    priceChart.data.datasets[0].pointHoverRadius = 4;
                }
                priceChart.update('active');
            });


            document.getElementById('btn-half-year').addEventListener('click', () => {
                priceChart.data = dataHalfYear;
                priceChart.options.scales.x.ticks.callback = formatMonthOnly;
                priceChart.options.scales.x.type = 'category';

                if (isMobile) {
                    priceChart.data.datasets[0].pointRadius = 2;
                    priceChart.data.datasets[0].pointHoverRadius = 3;

                    priceChart.options.scales.x.ticks.maxTicksLimit = 4;
                }
                else {
                    priceChart.data.datasets[0].pointRadius = 3;
                    priceChart.data.datasets[0].pointHoverRadius = 4;
                }
                priceChart.update('active');
            });

            document.getElementById('btn-all').addEventListener('click', () => {
                priceChart.data = dataAll;
                priceChart.options.scales.x.ticks.callback = formatMonthOnly;
                priceChart.options.scales.x.type = 'category';
                
                if (isMobile) {
                    priceChart.data.datasets[0].pointRadius = 2;
                    priceChart.data.datasets[0].pointHoverRadius = 3;
                }
                else {
                    priceChart.data.datasets[0].pointRadius = 3;
                    priceChart.data.datasets[0].pointHoverRadius = 4;
                }
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

        .navig-main-title {
            font-family: cardo;
        }

        .navigator-table {
            font-family: cardo;
            font-weight: bold;
        }

        .navig-row:hover {
            background-color: #B4C7E8;
            transition: background-color 0.6s ease;
        }
            
        .table-main {
            background: #20304F;
            color: #fff;
        }

        .table-main-data .price {
            font-weight: bold;
            font-size: 30px;
        }

        .table-main .change.up {
            color: #B2FBA5;
        }
        .table-main .change.down {
            color: #EA5C4E;
        }
        .table-main .change.unchanged {
            color: #B4C7E8;
        }
        
        .chart-controls {
            display: flex;
            justify-content: start;
            gap: 8px;
            margin-top: 12px;
        }   

        .chart-controls button {
            padding: 8px;
            border: none;
            border-radius: 2px;
            background: #828282;
            font-family: arial;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.5s, transform 0.1s;
            text-transform: uppercase;
            letter-spacing: 3.15px;
            font-size: 9px;
            line-height: 12px;
        }
        .chart-controls button:hover {
            background-color: #2a4068; /* darker on hover */
            transform: translateY(-2px);
        }

        .chart-controls button:active {
            background-color: #2a4068; /* even darker when clicked */
            transform: translateY(0);
        }

        .chart-controls button.active {
            background-color: #2a4068; /* highlighted active state */
        }
    </style>';
}


add_action('wp_head', 'navigator_chart_styles');

add_shortcode( 'navigator_chart', 'navigator_huf_chart_shortcode' );