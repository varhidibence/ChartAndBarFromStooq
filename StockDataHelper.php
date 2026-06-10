<?php
namespace NavigatorChart\Helpers;
 
class StockDataHelper {
    const STOOQ_APIKEY = 'ZFdMJhSyi62Tljw81eDKNZuoBrxvcVm7';
    const STOOQ_TICKER = 'navigator.hu';
    const RETRY_AFTER_ERROR = 900; // 10 perc - hiba eseten ennyi ido utan probalkozik ujra
    public static function fetchUrl($url): bool|string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("StockDataHelper fetchUrl error: " . curl_error($ch));
            return false;
        }
        curl_close($ch);
        return $result;
    }
 
    // Helper: CSV string → JSON
    public static function csv_to_json($csv_string) {
        // ✅ FIX: Guard against empty or non-CSV responses
        if (empty($csv_string) || !is_string($csv_string)) {
            return wp_json_encode([]);
        }
 
        $rows = array_map('str_getcsv', explode("\n", trim($csv_string)));
        $header = array_shift($rows);
 
        // ✅ FIX: Guard against missing or malformed header
        if (empty($header)) {
            return wp_json_encode([]);
        }
 
        $data = [];
        foreach ($rows as $row) {
            // ✅ FIX: Skip rows where column count doesn't match header
            if (!empty($row) && count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }
 
        return wp_json_encode($data);
    }
 
    public static function getLastPriceWithDate($symbol = 'navigator.hu') {
        $apikey = self::STOOQ_APIKEY;
        $url = "https://stooq.com/q/l/?s={$symbol}&f=sd2t2ohlcv&h&e=csv&apikey={$apikey}";
		
        $csv_data = StockDataHelper::fetchUrl($url);
 
		wp_add_inline_script('chartjs', 'console.log("getLastPriceWithDate:", ' . json_encode($csv_data) . ');');
        
        if ($csv_data === false) {
            return [];
        }
 
        $lines = explode("\n", trim($csv_data));
 
        // ✅ FIX: Guard against missing data lines
        if (count($lines) < 2) {
            return [];
        }
 
        $headers = str_getcsv($lines[0]);
        $dataRow = str_getcsv($lines[1]);
 
        // ✅ FIX: Guard against column mismatch
        if (count($headers) !== count($dataRow)) {
            return [];
        }
 
        $lastData = array_combine($headers, $dataRow);
        $lastClose = (float)($lastData['Close'] ?? 0);
 
        return [
            'date'       => $lastData['Date'] ?? null,
            'time'       => $lastData['Time'] ?? null,
            'close'      =>  $lastClose
        ];
    }
 
    public static function getChangeOfLastTwoDays($latestPrice, $yesterdayData) {
        // Validate input
        if (!is_array($latestPrice) || !is_array($yesterdayData)) {
            return null;
        }
 
        // Extract close prices
        $lastClose = isset($latestPrice['close']) ? (float)$latestPrice['close'] : null;
        $prevClose = isset($yesterdayData['Close']) ? (float)$yesterdayData['Close'] : null;
        
        // Ensure both are valid numbers
        if ($lastClose === null || $prevClose === null || $prevClose == 0) {
            return null;
        }
 
        // Calculate % change
        $changePct = (($lastClose - $prevClose) / $prevClose) * 100;
 
        return round($changePct, 2); // round to 2 decimals for readability
    }
 
    public static function get_actual_rates() {
        $ticker = 'navigator.hu';
        $start = date('Ymd', strtotime('-1 day'));
        $end = date('Ymd');  // pl. mai nap
        $interval = 'd';     // napi
 
        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$start}&d2={$end}&i={$interval}";
 
		return []; // TODO
 
        $csv_data = StockDataHelper::fetchUrl($url);
 
        if (empty($csvData)) {
            return [];
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
 
        $data = [];
        foreach ($rows as $row) {
            // ✅ FIX: Skip mismatched rows
            if (!empty($row) && count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
 
        return array_slice($data, -$count);
    }
 
    public static function fetchLastMonthData(){
        $ticker = self::STOOQ_TICKER;
        $apikey = self::STOOQ_APIKEY;
        $today = date('Ymd');
        $oneMonthAgo = date('Ymd', strtotime('-1 month'));
        $interval = 'd';
 
        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneMonthAgo}&d2={$today}&i={$interval}&apikey={$apikey}";
 
        $csv_data = StockDataHelper::fetchUrl($url);
        if ($csv_data === false) {
            error_log("StockDataHelper: Nem sikerült lekérni az adatokat. url: {$url}");
            return wp_json_encode([]);
        }
 
        $json_data = StockDataHelper::csv_to_json($csv_data);
 
        return $json_data;
    }
 
    public static function fetchLastMonthDataCSV(){
        $ticker = self::STOOQ_TICKER;
        $apikey = self::STOOQ_APIKEY;
        $today = date('Ymd');
        $oneMonthAgo = date('Ymd', strtotime('-1 month'));
        $interval = 'd';
 
        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneMonthAgo}&d2={$today}&i={$interval}&apikey={$apikey}";
 
        $csv_data = StockDataHelper::fetchUrl($url);
 
        if ($csv_data === false) {
            error_log("StockDataHelper: Nem sikerült lekérni az adatokat. url: {$url}");
            return '';
        }
        
        return $csv_data;
    }
 
    public static function fetchLastSixMonthData(){
        $ticker = self::STOOQ_TICKER;
        $apikey = self::STOOQ_APIKEY;
        $today = date('Ymd');
        $halfYearAgo = date('Ymd', strtotime('-6 month'));
        $interval = 'd';
 
        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$halfYearAgo}&d2={$today}&i={$interval}&apikey={$apikey}";
 
        $csv_data = StockDataHelper::fetchUrl($url);
        if ($csv_data === false) {
            error_log("StockDataHelper: Nem sikerült lekérni az adatokat. url: {$url}");
            return wp_json_encode([]);
        }
 
        $json_data = StockDataHelper::csv_to_json($csv_data);
 
        return $json_data;
    }
 
    public static function fetchAllMonthlyData(){
        $ticker = self::STOOQ_TICKER;
        $apikey = self::STOOQ_APIKEY;
        $end = date('Ymd');
        $interval = 'd';

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d2={$end}&i={$interval}&apikey={$apikey}";
        
        $csv_data = StockDataHelper::fetchUrl($url);
        if ($csv_data === false) {
            error_log("StockDataHelper: Nem sikerült lekérni az adatokat. url: {$url}");
            return wp_json_encode([]);
        }
        $json_data = StockDataHelper::csv_to_json($csv_data);
 
        return $json_data;
    }
 
    public static function fetchYTDData(){
        $ticker = self::STOOQ_TICKER;
        $apikey = self::STOOQ_APIKEY;
        $oneYearAgo = date('Ymd', strtotime('-1 year'));
        $end = date('Ymd');
        $interval = 'd';
 
        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneYearAgo}&d2={$end}&i={$interval}&apikey={$apikey}";
        
        $csv_data = StockDataHelper::fetchUrl($url);
        if ($csv_data === false) {
            error_log("StockDataHelper: Nem sikerült lekérni az adatokat. url: {$url}");
            return wp_json_encode([]);
        }
        $json_data = StockDataHelper::csv_to_json($csv_data);
 
        return $json_data;
    }
	
    /**
     * Kozos cache logika a JSON-t visszaado fetch fuggvenyekhez.
     * Siker eseten elmenti az adatot es a timestamp-et (kovetkezo frissites TTL mulva).
     * Hiba eseten RETRY_AFTER_ERROR mulva ujraprobalja, addig a regi cache-t hasznalja.
     */
    private static function getCachedJsonData(string $cache_key, string $timestamp_key, int $ttl, callable $fetch_fn, string $label): string {
        $cached_data = get_option($cache_key);
        $expires_at = get_option($timestamp_key);

        $should_refresh = !$cached_data || !$expires_at || time() > $expires_at;

        if (!$should_refresh) {
            wp_add_inline_script('chartjs', 'console.log("Returning cached ' . $label . ' data");');
            return $cached_data;
        }

        wp_add_inline_script('chartjs', 'console.log("No cached ' . $label . ' data, fetching...");');
        $response = $fetch_fn();

        if ($response === 'Exceeded the daily hits limit') {
            error_log('Stooq [' . $label . ']: exceeded the daily hits limit, using cached data if available.');
            update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);
            return $cached_data ?: wp_json_encode([]);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Stooq [' . $label . ']: invalid JSON returned.');
            update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);
            return $cached_data ?: wp_json_encode([]);
        }

        if (empty($decoded)) {
            error_log('Stooq [' . $label . ']: decoded response is empty, keeping cached data.');
            update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);
            return $cached_data ?: wp_json_encode([]);
        }

        // Sikeres fetch - cache frissitese
        update_option($cache_key, $response);
        update_option($timestamp_key, time() + $ttl);
        update_option($timestamp_key . '_last', time());

        return $response;
    }

    public static function GetCachedYTDData() {
        return self::getCachedJsonData(
            'navig_stooq_ytd_data', 'navig_stooq_ytd_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchYTDData'], 'YTD'
        );
    }

    public static function GetCachedLastMonthData() {
        return self::getCachedJsonData(
            'navig_stooq_last_month_data', 'navig_stooq_last_month_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchLastMonthData'], 'last_month'
        );
    }

    public static function GetCachedLastSixMonthData() {
        return self::getCachedJsonData(
            'navig_stooq_last_half_year_data', 'navig_stooq_last_half_year_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchLastSixMonthData'], 'last_half_year'
        );
    }

    public static function GetCachedAllData() {
        return self::getCachedJsonData(
            'navig_stooq_all_data', 'navig_stooq_all_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchAllMonthlyData'], 'all'
        );
    }

    public static function GetCachedLastPrice() {
        $cache_key = 'navig_stooq_last_price_data';
        $timestamp_key = 'navig_stooq_last_price_fetch';
        $ttl = 900; // 15 perc

        $cached_data = get_option($cache_key);
        $expires_at = get_option($timestamp_key);

        $should_refresh = !$cached_data || !$expires_at || time() > $expires_at;

        if (!$should_refresh) {
            wp_add_inline_script('chartjs', 'console.log("GetCachedLastPrice: returning cached data", ' . $cached_data . ');');
            return json_decode($cached_data, true);
        }

        $response = StockDataHelper::getLastPriceWithDate();

        if (!empty($response) && isset($response['close']) && $response['close'] > 0) {
            update_option($cache_key, wp_json_encode($response));
            update_option($timestamp_key, time() + $ttl);
            update_option($timestamp_key . '_last', time());
            wp_add_inline_script('chartjs', 'console.log("GetCachedLastPrice: fresh data fetched", ' . wp_json_encode($response) . ');');
            return $response;
        }

        // Hiba - RETRY_AFTER_ERROR mulva ujraprobal
        update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);

        if ($cached_data) {
            error_log('Stooq: getLastPriceWithDate() failed, using cached data.');
            wp_add_inline_script('chartjs', 'console.log("GetCachedLastPrice: fetch failed, using cached data", ' . $cached_data . ');');
            return json_decode($cached_data, true);
        }

        wp_add_inline_script('chartjs', 'console.log("GetCachedLastPrice: no data available");');
        return [];
    }
 
}