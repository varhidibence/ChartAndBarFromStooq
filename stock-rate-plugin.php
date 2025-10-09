<?php
/**
 * Plugin Name: NAVIGATOR Stock rate plugin
 * Description: Shows actual NAVIGATOR rates within a bar at the top of every page
 * Version: 1.0
 * Author: Bence Várhidi
 */

require_once WP_PLUGIN_DIR . '/chart-plugin/StockDataHelper.php';

use NavigatorChart\Helpers\StockDataHelper;

 // Biztonság kedvéért ne fusson közvetlenül
 if ( ! defined( 'ABSPATH' ) ) {
     exit;
 }

 // Itt jön a te saját árfolyam-lekérő kódod
function get_actual_rates() {
    $ticker = 'navigator.hu';
    $start = date('Ymd', strtotime('-1 day'));
    $end = date('Ymd');  // pl. mai nap
    $interval = 'd';     // napi

    $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$start}&d2={$end}&i={$interval}";

    echo "<div>$url</div>";

    $csv_data = StockDataHelper::fetchUrl($url);
    if ($csv_data === false) {
        die("Nem sikerült lekérni az adatokat. url: {$url}");
    }


    $rows = array_map('str_getcsv', explode("\n", trim($csv_data)));

    if (empty($rows) || count($rows) < 1){
        return [];
    }
    else {
        $firstRow = $rows[1];
    }
    
    return $firstRow;
 }



function navigator_bar_render() {
  
    $lastPrices = StockDataHelper::getLastPriceWithDate('navigator.hu');
    $changePct = StockDataHelper::getChangeOfLastTwoDays();
    
    if ($lastPrices) {
        $lastClose = (float)($lastPrices['close'] ?? 0);
        $date = $lastPrices['date'] ?? null;
        $time = $lastPrices['time'] ?? null;

        if (true) {
            echo "<div class='stock-box error'>NAVIG</div>";
        }
        $arrow = $changePct == 0 ? "-" : ($changePct > 0 ? "▲" : "▼");
        $class = $changePct == 0 ? "unchanged" : ($changePct > 0 ? "up" : "down");
        // Format with exactly one decimal place
        $formattedChange = number_format($changePct, 2);
        $signChange = $changePct == 0 ? "" : ($changePct > 0 ? "+" : "");

        echo "
            <div class='stock-box'>NAVIG
                <span class='price'>{$lastClose} HUF
                    <span class='navi-tooltiptext'>{$date} {$time}</span>
                </span>
                <span class='change {$class}'>{$arrow}</span>
                <span class='changepercent'> {$signChange}{$formattedChange}%</span>
            </div>
        ";
    } else {
        echo "<div class='stock-box error'>NAVIG</div>";
    }
}
 
// Stílus hozzáadása
function navigator_bar_styles() {
      echo '<style>
        .stock-box {
            width: 100%;
            background: #20304f;
            color: #fff;
            text-align: left;
            padding: 10px;
            font-size: 13px;
            font-family: arial, sans-serif;
            font-weight: bold;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 9000;
        }

        .stock-box .price {
            margin-left: 5px;
            position: relative; /* a tooltip igazításához kell */
            cursor: pointer;
            font-weight: normal;
        }

        /* Tooltip szöveg */
        .stock-box .price .navi-tooltiptext {
            visibility: hidden;
            background-color: rgba(0,0,0,0.85);
            color: #fff;
            text-align: center;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 13px;
            white-space: nowrap;

            position: absolute;
            bottom: -35px;  /* ár alatt jelenjen meg */
            left: 50%;
            transform: translateX(-50%);

            opacity: 0;
            transition: opacity 0.3s;
            z-index: 9999; /* mindig a top bar fölött legyen */
        }

        /* Hover állapot */
        .stock-box .price:hover > .navi-tooltiptext {
            visibility: visible !important;
            opacity: 1 !important;
        }

        .stock-box .changepercent {
            font-weight: normal;
        }

        .stock-box .change.up {
            color: green;
        }
        .stock-box .change.down {
            color: red;
        }
        .stock-box .change.unchanged {
            color: #B4C7E8;
        }

        // .stock-box.error {
        //     background: #ffe0e0;
        //     color: red;
        // }

        body {
            padding-top: 30px; /* hogy ne takarja ki a tartalmat */
        }
    </style>';
}

add_action('wp_head', 'navigator_bar_styles');

// Megjelenítés a body tetején
add_action('wp_body_open', 'navigator_bar_render');