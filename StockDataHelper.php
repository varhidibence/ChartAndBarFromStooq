<?php
namespace NavigatorChart\Helpers;
 
class StockDataHelper {
    const STOOQ_APIKEY = 'ZFdMJhSyi62Tljw81eDKNZuoBrxvcVm7';
    const STOOQ_TICKER = 'navigator.hu';
    const RETRY_AFTER_ERROR = 900; // 15 perc - hiba eseten ennyi ido utan probalkozik ujra
    const LOG_OPTION_KEY = 'navig_stooq_log';
    const LOG_MAX_ENTRIES = 50;

    public static function addLog(string $message, string $url = ''): void {
        $logs = get_option(self::LOG_OPTION_KEY, []);
        if (!is_array($logs)) $logs = [];
        array_unshift($logs, [
            'time' => time(),
            'message' => $message,
            'url' => $url,
        ]);
        $logs = array_slice($logs, 0, self::LOG_MAX_ENTRIES);
        update_option(self::LOG_OPTION_KEY, $logs);
        error_log('Stooq: ' . $message . ($url ? ' — URL: ' . $url : ''));
    }

    public static function clearLog(): void {
        delete_option(self::LOG_OPTION_KEY);
    }

    public static function getLog(): array {
        $logs = get_option(self::LOG_OPTION_KEY, []);
        return is_array($logs) ? $logs : [];
    }
    public static function fetchUrl($url): bool|string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            self::addLog('fetchUrl error: ' . curl_error($ch) . ' — URL: ' . $url);
            curl_close($ch);
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
 
        if (empty($csv_data)) {
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
            self::addLog("Fetch failed — URL: {$url}");
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
            self::addLog("Fetch failed — URL: {$url}");
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
            self::addLog("Fetch failed — URL: {$url}");
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
            self::addLog("Fetch failed — URL: {$url}");
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
            self::addLog("Fetch failed — URL: {$url}");
            return wp_json_encode([]);
        }
        $json_data = StockDataHelper::csv_to_json($csv_data);
 
        return $json_data;
    }
	
    /**
     * Csak cache-bol olvas, soha nem hiv API-t.
     * A frissites a WP cron feladata (refreshAllData).
     */
    private static function getCachedJsonData(string $cache_key): string {
        $cached_data = get_option($cache_key);
        return $cached_data ?: wp_json_encode([]);
    }

    /**
     * Cron altal hivott frissito logika egyetlen adattipushoz.
     * Siker eseten elmenti az adatot es a timestamp-et.
     * Hiba eseten RETRY_AFTER_ERROR mulva ujraprobalja.
     */
    public static function refreshCachedJsonData(string $cache_key, string $timestamp_key, int $ttl, callable $fetch_fn, string $label): void {
        $cached_data = get_option($cache_key);

        $response = $fetch_fn();

        if ($response === 'Exceeded the daily hits limit') {
            self::addLog('[' . $label . '] Exceeded the daily hits limit.');
            update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);
            return;
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::addLog('[' . $label . '] Invalid JSON returned.');
            update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);
            return;
        }

        if (empty($decoded)) {
            self::addLog('[' . $label . '] Empty response, keeping cached data.');
            update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);
            return;
        }

        update_option($cache_key, $response);
        update_option($timestamp_key, time() + $ttl);
        update_option($timestamp_key . '_last', time());
        self::addLog('[' . $label . '] OK — data refreshed.');
    }

    public static function GetCachedYTDData() {
        return self::getCachedJsonData('navig_stooq_ytd_data');
    }

    public static function GetCachedLastMonthData() {
        return self::getCachedJsonData('navig_stooq_last_month_data');
    }

    public static function GetCachedLastSixMonthData() {
        return self::getCachedJsonData('navig_stooq_last_half_year_data');
    }

    public static function GetCachedAllData() {
        return self::getCachedJsonData('navig_stooq_all_data');
    }

    public static function GetCachedLastPrice() {
        $cached_data = get_option('navig_stooq_last_price_data');
        if ($cached_data) {
            return json_decode($cached_data, true);
        }
        return [];
    }

    public static function refreshYTDData(): void {
        self::refreshCachedJsonData(
            'navig_stooq_ytd_data', 'navig_stooq_ytd_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchYTDData'], 'YTD'
        );
    }

    public static function refreshLastMonthData(): void {
        self::refreshCachedJsonData(
            'navig_stooq_last_month_data', 'navig_stooq_last_month_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchLastMonthData'], 'last_month'
        );
    }

    public static function refreshLastSixMonthData(): void {
        self::refreshCachedJsonData(
            'navig_stooq_last_half_year_data', 'navig_stooq_last_half_year_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchLastSixMonthData'], 'last_half_year'
        );
    }

    public static function refreshAllMonthlyData(): void {
        self::refreshCachedJsonData(
            'navig_stooq_all_data', 'navig_stooq_all_fetch', DAY_IN_SECONDS,
            [self::class, 'fetchAllMonthlyData'], 'all'
        );
    }

    /**
     * Osszes adat frissitese - ezt hivja a napi WP cron.
     * BSE.hu-rol tolt, egyetlen lekeressel minden cache-t feltolt.
     */
    public static function refreshAllData(): void {
        self::refreshAllDataFromBSE();
    }

    /**
     * Csak az utolso ar frissitese - ezt hivja a 15 perces cron.
     * BSE.hu-rol tolti az aktualis arat.
     * Hetvegen es tozsde nyitvatartason kivul kihagyja a lekerеs.
     */
    public static function refreshLastPrice(): void {
        if (!self::isTradingHours()) {
            return;
        }
        self::refreshLastPriceFromBSE();
    }

    // =========================================================================
    // BSE.hu adatforras
    // =========================================================================

    const BSE_SECURITY_ID = '15491'; // Navigator Investments Nyrt.
    const BSE_PROFILE_URL = 'https://bse.hu/pages/company_profile/$security/NAVIGATOR';

    /**
     * Ellenorzi, hogy Budapest idozona szerint tozsde nyitvatartasi idoben vagyunk-e.
     * H-P 8:45 - 18:00 kozott true, egyebkent false.
     * Kicsit bovebb mint a tényleges 9:00-17:20, hogy a nyitas elotti/utani adatokat is elkapjuk.
     */
    public static function isTradingHours(): bool {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Budapest'));
        $dayOfWeek = (int) $now->format('N'); // 1=hetfo, 7=vasarnap
        if ($dayOfWeek > 5) {
            return false; // hetvege
        }
        $hour = (int) $now->format('G');
        $minute = (int) $now->format('i');
        $timeMinutes = $hour * 60 + $minute;
        // 8:45 = 525 perc, 18:00 = 1080 perc
        return $timeMinutes >= 525 && $timeMinutes <= 1080;
    }

    /**
     * BSE.hu company profile oldal letoltese es a SecurityHistoricDataSource
     * values tomb kinyerese.
     * Visszaad: [[timestamp_ms, open, high, low, close, value, volume], ...] vagy ures tombot hiba eseten.
     */
    public static function fetchBSERawData(): array {
        $url = self::BSE_PROFILE_URL;
        $html = self::fetchUrl($url);
        if ($html === false || strlen($html) < 1000) {
            self::addLog('[BSE] Fetch failed or empty response.', $url);
            return [];
        }

        $needle = 'SecurityHistoricDataSource;securityId=' . self::BSE_SECURITY_ID;
        $pos = strpos($html, $needle);
        if ($pos === false) {
            self::addLog('[BSE] SecurityHistoricDataSource not found in HTML.', $url);
            return [];
        }

        // Megkeressuk a "values": [ ... ]] reszt
        $valuesPos = strpos($html, '"values":', $pos);
        if ($valuesPos === false) {
            self::addLog('[BSE] "values" key not found.', $url);
            return [];
        }

        // A values tomb kezdete es vege
        $arrayStart = strpos($html, '[[', $valuesPos);
        $arrayEnd = strpos($html, ']]', $arrayStart);
        if ($arrayStart === false || $arrayEnd === false) {
            self::addLog('[BSE] Could not find values array boundaries.', $url);
            return [];
        }

        $valuesJson = substr($html, $arrayStart, $arrayEnd - $arrayStart + 2);
        $values = json_decode($valuesJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($values)) {
            self::addLog('[BSE] JSON parse error: ' . json_last_error_msg(), $url);
            return [];
        }

        return $values;
    }

    /**
     * BSE raw values tombot alakit at a frontend altal vart formatumra.
     * Opcionalis $fromDate (Y-m-d) szures.
     * Visszaad JSON stringet: [{"Date":"2026-06-15","Open":"104","High":"104","Low":"104","Close":"104","Volume":"4500"}, ...]
     */
    public static function bseValuesToJson(array $values, ?string $fromDate = null): string {
        $budapest = new \DateTimeZone('Europe/Budapest');
        $data = [];
        foreach ($values as $row) {
            if (count($row) < 7) continue;

            $dt = (new \DateTime('@' . (int)($row[0] / 1000)))->setTimezone($budapest);
            $date = $dt->format('Y-m-d');

            if ($fromDate !== null && $date < $fromDate) {
                continue;
            }

            $data[] = [
                'Date'   => $date,
                'Open'   => (string)$row[1],
                'High'   => (string)$row[2],
                'Low'    => (string)$row[3],
                'Close'  => (string)$row[4],
                'Volume' => (string)round($row[6]),
            ];
        }

        return wp_json_encode($data);
    }

    /**
     * Egyetlen BSE.hu lekeresbol feltolti az osszes cache-t (last_month, half_year, ytd, all, last_price).
     */
    public static function refreshAllDataFromBSE(): void {
        $values = self::fetchBSERawData();
        $url = self::BSE_PROFILE_URL;

        if (empty($values)) {
            self::addLog('[BSE] refreshAllDataFromBSE — no data, skipping.', $url);
            return;
        }

        $now = time();

        // Datumhatarok
        $oneMonthAgo  = date('Y-m-d', strtotime('-1 month'));
        $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
        $oneYearAgo   = date('Y-m-d', strtotime('-1 year'));

        $sets = [
            ['navig_stooq_last_month_data',    'navig_stooq_last_month_fetch',    $oneMonthAgo,  'last_month'],
            ['navig_stooq_last_half_year_data', 'navig_stooq_last_half_year_fetch', $sixMonthsAgo, 'last_half_year'],
            ['navig_stooq_ytd_data',           'navig_stooq_ytd_fetch',           $oneYearAgo,   'YTD'],
            ['navig_stooq_all_data',           'navig_stooq_all_fetch',           null,          'all'],
        ];

        foreach ($sets as [$cacheKey, $tsKey, $fromDate, $label]) {
            $json = self::bseValuesToJson($values, $fromDate);
            $decoded = json_decode($json, true);
            if (!empty($decoded)) {
                update_option($cacheKey, $json);
                update_option($tsKey, $now + DAY_IN_SECONDS);
                update_option($tsKey . '_last', $now);
                self::addLog('[BSE][' . $label . '] OK — ' . count($decoded) . ' rows.', $url);
            } else {
                self::addLog('[BSE][' . $label . '] Empty after filtering.', $url);
            }
        }

        // Last price: IntraDayDataSource-bol (pontos aktualis ar)
        self::refreshLastPriceFromBSE();
    }

    /**
     * Csak az utolso ar frissitese BSE.hu-rol.
     * Az IntraDayDataSource-bol veszi az aktualis arat.
     */
    public static function refreshLastPriceFromBSE(): void {
        $url = self::BSE_PROFILE_URL;
        $html = self::fetchUrl($url);
        if ($html === false || strlen($html) < 1000) {
            self::addLog('[BSE][last_price] Fetch failed.', $url);
            update_option('navig_stooq_last_price_fetch', time() + self::RETRY_AFTER_ERROR);
            return;
        }

        $needle = 'SecurityIntraDayDataSource;infoBar=true;securityId=' . self::BSE_SECURITY_ID;
        $pos = strpos($html, $needle);
        if ($pos === false) {
            self::addLog('[BSE][last_price] IntraDayDataSource not found.', $url);
            update_option('navig_stooq_last_price_fetch', time() + self::RETRY_AFTER_ERROR);
            return;
        }

        // lastClosePrice, changValue kinyerese az IntraDayDataSource-bol
        $chunk = substr($html, $pos, 500);

        if (!preg_match('/"lastClosePrice":([\d.]+)/', $chunk, $priceMatch)) {
            self::addLog('[BSE][last_price] Could not parse price from IntraDayDataSource.', $url);
            update_option('navig_stooq_last_price_fetch', time() + self::RETRY_AFTER_ERROR);
            return;
        }

        $lastClose = (float)$priceMatch[1];
        $change = 0.0;
        if (preg_match('/"changValue":([-\d.]+)/', $chunk, $changeMatch)) {
            $change = (float)$changeMatch[1];
        }
        $price = $lastClose + $change;

        // Az utolso kereskedes pontos idopontjat a Security1IntradayHistoricalDataSource-bol vesszuk,
        // mert az IntraDayDataSource dateTime mezoje csak a nap ejfelet adja (00:00:00).
        $timestamp = null;
        $intradayNeedle = 'Security1IntradayHistoricalDataSource;securityId=' . self::BSE_SECURITY_ID;
        $intradayPos = strpos($html, $intradayNeedle);
        if ($intradayPos !== false) {
            // Csak a "values":[...] tombot keressuk ki, ne folyjunk at a kovetkezo adatforrasba
            $valuesStart = strpos($html, '"values":[', $intradayPos);
            if ($valuesStart !== false) {
                // A "flags" kulcs jelzi a values tomb veget ebben a JSON objektumban
                $valuesEnd = strpos($html, ',"flags"', $valuesStart);
                if ($valuesEnd !== false) {
                    $valuesStr = substr($html, $valuesStart, $valuesEnd - $valuesStart);
                    // Az utolso [timestamp, ...] bejegyzes timestampje
                    if (preg_match_all('/\[(\d{13,}),/', $valuesStr, $tsMatches)) {
                        $lastTs = end($tsMatches[1]);
                        $timestamp = (int)($lastTs / 1000);
                    }
                }
            }
        }

        // Fallback: IntraDayDataSource dateTime (ejfel, de jobb mint semmi)
        if ($timestamp === null) {
            if (preg_match('/"dateTime":(\d+)/', $chunk, $dateMatch)) {
                $timestamp = (int)($dateMatch[1] / 1000);
            } else {
                $timestamp = time();
            }
        }

        if ($price <= 0) {
            self::addLog('[BSE][last_price] Invalid price: ' . $price, $url);
            update_option('navig_stooq_last_price_fetch', time() + self::RETRY_AFTER_ERROR);
            return;
        }

        $budapest = new \DateTimeZone('Europe/Budapest');
        $dt = (new \DateTime('@' . $timestamp))->setTimezone($budapest);
        $lastPrice = [
            'date'  => $dt->format('Y-m-d'),
            'time'  => $dt->format('H:i:s'),
            'close' => $price,
        ];

        update_option('navig_stooq_last_price_data', wp_json_encode($lastPrice));
        update_option('navig_stooq_last_price_fetch', time() + 900);
        update_option('navig_stooq_last_price_fetch_last', time());
        self::addLog('[BSE][last_price] OK — price: ' . $price . ', date: ' . $lastPrice['date'] . ' ' . $lastPrice['time'], $url);
    }

    // =========================================================================
    // Stooq adatforras (regi, jelenleg nem hasznalt — bot-vedelem blokkolja)
    // =========================================================================

    public static function refreshLastPrice_Stooq(): void {
        $cache_key = 'navig_stooq_last_price_data';
        $timestamp_key = 'navig_stooq_last_price_fetch';
        $ttl = 900;

        $response = self::getLastPriceWithDate();

        if (!empty($response) && isset($response['close']) && $response['close'] > 0) {
            update_option($cache_key, wp_json_encode($response));
            update_option($timestamp_key, time() + $ttl);
            update_option($timestamp_key . '_last', time());
            self::addLog('[last_price] OK — price: ' . $response['close'] . ', date: ' . ($response['date'] ?? '?'));
            return;
        }

        self::addLog('[last_price] Failed — no valid price returned.');
        update_option($timestamp_key, time() + self::RETRY_AFTER_ERROR);
    }

    public static function refreshAllData_Stooq(): void {
        self::refreshYTDData();
        self::refreshLastMonthData();
        self::refreshLastSixMonthData();
        self::refreshAllMonthlyData();
        self::refreshLastPrice_Stooq();
    }

}