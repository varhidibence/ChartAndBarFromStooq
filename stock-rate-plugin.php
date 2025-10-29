<?php
/**
 * Plugin Name: NAVIGATOR Stock rate plugin
 * Description: Shows actual NAVIGATOR rates within a bar at the top of every page
 * Version: 1.0
 * Author: Bence Várhidi
 */

require_once WP_PLUGIN_DIR . '/chart-plugin/StockDataHelper.php';

use NavigatorChart\Helpers\StockDataHelper;

// Not to run directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function navigator_bar_render() {

    $lastPrices = StockDataHelper::getLastPriceWithDate('navigator.hu');
    $changePct = StockDataHelper::getChangeOfLastTwoDays();
    
    if ($lastPrices) {
        $lastClose = (float)($lastPrices['close'] ?? 0);
        $date = $lastPrices['date'] ?? null;
        $time = $lastPrices['time'] ?? null;

        $arrow = $changePct == 0 ? "-" : ($changePct > 0 ? "▲" : "▼");
        $class = $changePct == 0 ? "unchanged" : ($changePct > 0 ? "up" : "down");
        // Format with exactly one decimal place
        $formattedChange = number_format($changePct, 2);
        $signChange = $changePct == 0 ? "" : ($changePct > 0 ? "+" : "");

        echo "
        <a class='palyazat' href='https://www.navigatorinvest.com/palyazatok/' 
            style='position:fixed; right:0; bottom:-4px; z-index:150;	width:170px;'>
                <img src='' 
                    alt='Széchenyi 2020' 
                    style='max-width: 100%;height:auto;'>
            </a>
            <ul class='stock-box pull-left'>NAVIG
                <span class='price'>{$lastClose} HUF
                    <span class='navi-tooltiptext'>{$date} {$time}</span>
                </span>
                <span class='change {$class}'>{$arrow}</span>
                <span class='changepercent'> {$signChange}{$formattedChange}%</span>
            </ul>
                <header class='header-style1 default-bg'>
                <div class='pre-header secondary-bg-dark'>
                <div class='container'>
                    <ul class='contact-block pull-right white'>
                        <li id='language_list' class='dropdown'>
                            
                        </li>
                        <li class=''>
                            <i class='fa fa-envelope-o primary-color' aria-hidden='true'>
                            </i>info@navigatorinvest.com
                        </li>
                        <li class='topbar-button'>
                            <a href='https://www.navigatorinvest.com/kapcsolat/' class='button caps_normal btn_small btn-primary'>
                                Lépjen velünk kapcsolatba!
                            </a>
                        </li>	
                        
                    </ul>
                </div><!-- .container -->
            </div><!-- pre-header -->
            ";
    } else {
        echo "";
    }
}
 
// Styling
function navigator_bar_styles() {
      echo '<style>
        .stock-box {
            width: 20%;
            background: #20304f;
            color: #fff;
            text-align: left;
            padding: 10px;
            font-size: 13px;
            font-family: arial, sans-serif;
            font-weight: bold;
        }

        .stock-box .price {
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
            color: #B2FBA5;
        }
        .stock-box .change.down {
            color: #EA5C4E;
        }
        .stock-box .change.unchanged {
            color: #B4C7E8;
        }
    </style>';
}

add_action('wp_head', 'navigator_bar_styles');

// Megjelenítés a body tetején
add_action('wp_body_open', 'navigator_bar_render');