<?php
/**
 * Plugin Name: NAVIGATOR Stock rate plugin
 * Description: Shows actual NAVIGATOR rates within a bar at the top of every page
 * Version: 1.4
 * Author: Bence Várhidi
 */

require_once WP_PLUGIN_DIR . '/chart-plugin/StockDataHelper.php';

use NavigatorChart\Helpers\StockDataHelper;

// Not to run directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function navigator_bar_render() {
  
    $lastPrices = StockDataHelper::GetCachedLastPrice();
    
    $lastMonthData = StockDataHelper::GetCachedLastMonthData();
    $lastMonthDataCSV = json_decode($lastMonthData, true);

    $lastFive = StockDataHelper::get_last_rows_from_csv($lastMonthDataCSV, 5);
    $yesterdayData = !empty($lastFive) ? end($lastFive) : [];
    $changePct = StockDataHelper::getChangeOfLastTwoDays($lastPrices, $yesterdayData);
    
    if ($lastPrices) {
        $lastClose = (float)($lastPrices['close'] ?? 0);
        $date = $lastPrices['date'] ?? null;
        $time = $lastPrices['time'] ?? null;
		
		$changePct = $changePct ?? 0;
        $arrow = $changePct == 0 ? "-" : ($changePct > 0 ? "▲" : "▼");
        $class = $changePct == 0 ? "unchanged" : ($changePct > 0 ? "up" : "down");
        // Format with exactly one decimal place
        $formattedChange = number_format($changePct, 2);
        $signChange = $changePct == 0 ? "" : ($changePct > 0 ? "+" : "");

        echo "
            <ul class='stock-box pull-left'>NAVIG
                <span class='price'>{$lastClose} HUF
                    <span class='navi-tooltiptext'>{$date} {$time}</span>
                </span>
                <span class='change {$class}'>{$arrow}</span>
                <span class='changepercent'> {$signChange}{$formattedChange}%</span>
            </ul>
        ";
    } else {
        echo "";
    }
}

function navigator_bar_display_fix() {
	?>
     <script>
    	document.addEventListener('DOMContentLoaded', () => {
			const stockBox = document.querySelector('.stock-box');
			const containerDiv = document.querySelector('.pre-header .container');
			const emailLink = document.querySelector('.pre-header li .fa-envelope-o')?.parentElement;
			const locationLink = document.querySelector('.pre-header li .fa-map-marker')?.parentElement;

			if (!stockBox || !containerDiv || !emailLink) return;
			
			function adjustStockBoxHeight() {
				const containerHeight = containerDiv.offsetHeight;
				if (containerHeight > 0) {
					stockBox.style.height = containerHeight + 'px';
					stockBox.style.display = 'flex';
					stockBox.style.alignItems = 'center';
				}
			}

			function moveStockBox() {
				// Handle 1200px and below
				if (window.innerWidth <= 1200) {
					if (locationLink) locationLink.style.display = 'none';
				} else {
					if (locationLink) locationLink.style.display = ''; // show it again
				}
				if (window.innerWidth <= 768) {
					if (emailLink) emailLink.style.display = 'none';
					stockBox.style.display = 'flex';
					stockBox.style.alignItems = 'center';
					stockBox.style.width = '50%';
					stockBox.style.position = 'fixed';
					stockBox.style.top = '0';
					stockBox.style.left = '50%'; // move to the middle
					stockBox.style.transform = 'translateX(-50%)'; // actually center it

					//stockBox.style.left = '100px';
					
					// Find and hide the empty div/element above stock-box
					const above = stockBox.parentElement?.previousElementSibling;
					if (above) {
						above.style.display = 'none';
						console.log('Navigator fix: hiding empty element above stock-box');
					}
					
				} else {
					if (emailLink) emailLink.style.display = '';
					
					// Restore stock-box to container on desktop
					const firstUl = containerDiv.querySelector('ul');
					if (firstUl && !containerDiv.contains(stockBox)) {
						containerDiv.insertBefore(stockBox, firstUl);
						stockBox.style.display = 'flex';
						stockBox.style.alignItems = 'center';
						stockBox.style.margin = '0';
						stockBox.style.width = '20%';
						stockBox.style.height = '30px';
						stockBox.style.position = '';
						stockBox.style.top = '';
					}
				}
				
				adjustStockBoxHeight();
			}
			// Initial placement
			moveStockBox();

			// Re-run on resize / orientation change
			window.addEventListener('resize', moveStockBox);
    	});
    	</script>
    <?php
}
 
// Styling
function navigator_bar_styles() {
      echo '<style>
        .stock-box {
            background: #20304f;
            color: #fff;
            text-align: left;
            padding: 10px;
            font-size: 13px;
            font-family: arial, sans-serif;
            font-weight: bold;
			display: flex;
			align-items: center;
			gap: 6px; /* optional spacing between spans */
        }
		.stock-box span {
		  display: inline-block;
		  white-space: nowrap;
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

add_action('wp_footer', 'navigator_bar_display_fix');