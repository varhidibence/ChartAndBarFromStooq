<?php
namespace NavigatorChart\Helpers;

class StockDataHelper {
    public static function fetchUrl($url): bool|string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "Hiba: " . curl_error($ch);
            return false;
        }
        curl_close($ch);
        return $result;
    }

    // Helper: CSV string → JSON
    public static function csv_to_json($csv_string) {
        $rows = array_map('str_getcsv', explode("\n", trim($csv_string)));
        $header = array_shift($rows);

        $data = [];
        foreach ($rows as $row) {
            $data[] = array_combine($header, $row);
        }

        return wp_json_encode($data);
    }

    public static function getLastPriceWithDate($symbol = 'navigator.hu') {
        $url = "https://stooq.com/q/l/?s={$symbol}&f=sd2t2ohlcv&h&e=csv";

        //
        $csv_data = StockDataHelper::fetchUrl($url);

        if ($csv_data === false) {
            return [];
        }

        $lines = explode("\n", trim($csv_data));

        // First line = headers, last non-empty line = most recent row
        $dataRow = str_getcsv($lines[1]);
        $headers = str_getcsv($lines[0]);

        $lastData = array_combine($headers, $dataRow);
        $lastClose = (float)($lastData['Close'] ?? 0);

        return [
            'date'       => $lastData['Date'] ?? null,
            'time'       => $lastData['Time'] ?? null,
            'close'      =>  $lastClose
        ];
    }

    public static function getChangeOfLastTwoDays($symbol = 'navigator.hu') {
        $oneMonthAgo = date('Ymd', strtotime('-1 month'));
        $today = date('Ymd');
		
		//return null; // TODO

        $url = "https://stooq.com/q/d/l/?s={$symbol}&d1={$oneMonthAgo}&d2={$today}&i=d";
        $csv_data = StockDataHelper::fetchUrl($url);
        
        if ($csv_data === false) {
            return null; // could not fetch
        }
		wp_add_inline_script('chartjs', 'console.log("getChangeOfLastTwoDays:", ' . json_encode($csv_data) . ');');
        $lines = explode("\n", trim($csv_data));

        // First line = headers, last non-empty line = most recent row
        $lastRow = str_getcsv($lines[count($lines) - 1]);
        $headers = str_getcsv($lines[0]);

        // Need at least 2 rows to calculate change
        if (count($lines) < 2) {
            return null;
        }

        // Last two rows
        $lastRow     = str_getcsv($lines[count($lines) - 1]);
        $prevRow     = str_getcsv($lines[count($lines) - 2]);

        $lastData = array_combine($headers, $lastRow);
        $prevData = array_combine($headers, $prevRow);

        $lastClose = (float)($lastData['Close'] ?? 0);
        $prevClose = (float)($prevData['Close'] ?? 0);

        if ($lastClose && $prevClose) {
            $changePct = (($lastClose - $prevClose) / $prevClose) * 100;
        } else {
            $changePct = null;
        }

        return $changePct;
    }

    public static function get_last_rows_from_csv($csvData, int $count = 5): array {
        if (empty($csvData)) {
            return [];
        }

        // 🔹 If it's already an array (JSON-decoded data)
        if (is_array($csvData)) {
            return array_slice($csvData, -$count);
        }

        // 🔹 Otherwise, assume it's a CSV string
        $lines = explode("\n", trim($csvData));
        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $rows = array_map('str_getcsv', $lines);

        $data = array_map(function($row) use ($headers) {
            return array_combine($headers, $row);
        }, $rows);

        return array_slice($data, -$count);
    }

    public static function fetchLastMonthData(){
        $ticker = 'navigator.hu';
        $today = date('Ymd');
        $oneMonthAgo = date('Ymd', strtotime('-1 month'));
        $interval = 'd';     // napi
		
		//return wp_json_encode([]); // TODO

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneMonthAgo}&d2={$today}&i={$interval}";

        //wp_add_inline_script('chartjs', 'console.log("Fetching data (Last month):", ' . json_encode($url) . ');');

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

    public static function fetchLastMonthDataCSV(){
        $ticker = 'navigator.hu';
        $today = date('Ymd');
        $oneMonthAgo = date('Ymd', strtotime('-1 month'));
        $interval = 'd';     // napi

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneMonthAgo}&d2={$today}&i={$interval}";

        ///eturn wp_json_encode([]); // TODO
        //wp_add_inline_script('chartjs', 'console.log("Fetching data (Last month):", ' . json_encode($url) . ');');

        // Lekérjük a CSV adatot
        $csv_data = StockDataHelper::fetchUrl($url);

        if ($csv_data === false) {
            die("Nem sikerült lekérni az adatokat. url: {$url}");
        }
        
        return $csv_data;
    }

    public static function fetchLastSixMonthData(){
        $ticker = 'navigator.hu';
        $today = date('Ymd');
        $halfYearAgo = date('Ymd', strtotime('-6 month'));
        $interval = 'd';     // napi

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$halfYearAgo}&d2={$today}&i={$interval}";

        return wp_json_encode([]); // TODO
        //wp_add_inline_script('chartjs', 'console.log("Fetching data (Half year)):", ' . json_encode($url) . ');');
        $csv_data = StockDataHelper::fetchUrl($url);
        if ($csv_data === false) {
            die("Nem sikerült lekérni az adatokat. url: {$url}");
        }

        $json_data = StockDataHelper::csv_to_json($csv_data);

        return $json_data;
    }

    public static function fetchAllMonthlyData(){
        $ticker = 'navigator.hu';
        $end = date('Ymd');
        $interval = 'w';     // weekly

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d2={$end}&i={$interval}";

        return wp_json_encode([]); // TODO
        //wp_add_inline_script('chartjs', 'console.log("Fetching data (All):", ' . json_encode($url) . ');');
        
        $csv_data = StockDataHelper::fetchUrl($url);
        if ($csv_data === false) {
            die("Nem sikerült lekérni az adatokat. url: {$url}");
        }
        $json_data = StockDataHelper::csv_to_json($csv_data);

        return $json_data;
    }

    public static function fetchYTDData(){
        $ticker = 'navigator.hu';
        $oneYearAgo = date('Ymd', strtotime('-1 year'));
        $end = date('Ymd');  // pl. mai nap
        $interval = 'd';     // havi

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneYearAgo}&d2={$end}&i={$interval}";
        wp_add_inline_script('chartjs', 'console.log("Fetching data (YTD):", ' . json_encode($url) . ');');
        
        $csv_data = StockDataHelper::fetchUrl($url);

        if ($csv_data === false) {
            die("Nem sikerült lekérni az adatokat. url: {$url}");
        }
        $json_data = StockDataHelper::csv_to_json($csv_data);
		//wp_add_inline_script('chartjs', 'console.log("YTD json:", ' . json_encode($json_data) . ');');

        return $json_data;
    }

    public static function GetCachedYTDData() {
        $cache_key = 'navig_stooq_ytd_data';
        $timestamp_key = 'navig_stooq_ytd_fetch';

        // Try to get cached data
        $cached_data = get_option($cache_key);
        $last_fetch = get_option($timestamp_key);

        // Check if we should fetch fresh data (no cache or older than 24h)
        $should_refresh = !$cached_data || !$last_fetch || (time() - $last_fetch) > DAY_IN_SECONDS;

        if ($should_refresh) {
            $response = StockDataHelper::fetchYTDData();

            wp_add_inline_script('chartjs', 'console.log("No cached YTD data, fetching...   ");');

            if ($response === 'Exceeded the daily hits limit') {
                error_log('Stooq: exceeded the daily hits limit, using cached data if available.');
                // Fallback to cached data
                return $cached_data ?: [];
            }

            // Optionally: verify it's valid JSON before saving
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Stooq: invalid JSON returned from fetchYTDData().');
                return $cached_data ?: [];
            }

            // Save new data + timestamp
            update_option($cache_key, $response);
            update_option($timestamp_key, time());

            return $response;
        }

        wp_add_inline_script('chartjs', 'console.log("Returning cached YTD data");');       
        // ✅ Return cached data
        return $cached_data;
    }

    public static function GetCachedLastMonthData() {
        $cache_key = 'navig_stooq_last_month_data';
        $timestamp_key = 'navig_stooq_last_month_fetch';

        // Try to get cached data
        $cached_data = get_option($cache_key);
        $last_fetch = get_option($timestamp_key);

        // Check if we should fetch fresh data (no cache or older than 24h)
        $should_refresh = !$cached_data || !$last_fetch || (time() - $last_fetch) > DAY_IN_SECONDS;

        if ($should_refresh) {
            $response = StockDataHelper::fetchLastMonthData();

            if ($response === 'Exceeded the daily hits limit') {
                error_log('Stooq: exceeded the daily hits limit, using cached data if available.');
                // Fallback to cached data
                return $cached_data ?: [];
            }

            // Optionally: verify it's valid JSON before saving
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Stooq: invalid JSON returned from fetchYTDData().');
                return $cached_data ?: [];
            }

            // Save new data + timestamp
            update_option($cache_key, $response);
            update_option($timestamp_key, time());

            return $response;
        }

        wp_add_inline_script('chartjs', 'console.log("Returning cached YTD data");');       
        // ✅ Return cached data
        return $cached_data;
    }

    public static function GetCachedLastSixMonthData() {
        $cache_key = 'navig_stooq_last_half_year_data';
        $timestamp_key = 'navig_stooq_last_half_year_fetch';

        // Try to get cached data
        $cached_data = get_option($cache_key);
        $last_fetch = get_option($timestamp_key);

        // Check if we should fetch fresh data (no cache or older than 24h)
        $should_refresh = !$cached_data || !$last_fetch || (time() - $last_fetch) > DAY_IN_SECONDS;

        if ($should_refresh) {
            $response = StockDataHelper::fetchLastSixMonthData();
            
            if ($response === 'Exceeded the daily hits limit') {
                error_log('Stooq: exceeded the daily hits limit, using cached data if available.');
                // Fallback to cached data
                return $cached_data ?: [];
            }

            // Optionally: verify it's valid JSON before saving
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Stooq: invalid JSON returned from fetchYTDData().');
                return $cached_data ?: [];
            }

            // Save new data + timestamp
            update_option($cache_key, $response);
            update_option($timestamp_key, time());

            return $response;
        }

        wp_add_inline_script('chartjs', 'console.log("Returning cached YTD data");');       
        // ✅ Return cached data
        return $cached_data;
    }

    public static function GetCachedAllData() {
        $cache_key = 'navig_stooq_all_data';
        $timestamp_key = 'navig_stooq_all_fetch';

        // Try to get cached data
        $cached_data = get_option($cache_key);
        $last_fetch = get_option($timestamp_key);

        // Check if we should fetch fresh data (no cache or older than 24h)
        $should_refresh = !$cached_data || !$last_fetch || (time() - $last_fetch) > DAY_IN_SECONDS;

        if ($should_refresh) {
            $response = StockDataHelper::fetchAllMonthlyData();
            
            if ($response === 'Exceeded the daily hits limit') {
                error_log('Stooq: exceeded the daily hits limit, using cached data if available.');
                // Fallback to cached data
                return $cached_data ?: [];
            }

            // Optionally: verify it's valid JSON before saving
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Stooq: invalid JSON returned from fetchYTDData().');
                return $cached_data ?: [];
            }

            // Save new data + timestamp
            update_option($cache_key, $response);
            update_option($timestamp_key, time());

            return $response;
        }

        wp_add_inline_script('chartjs', 'console.log("Returning cached YTD data");');       
        // ✅ Return cached data
        return $cached_data;
    }


}