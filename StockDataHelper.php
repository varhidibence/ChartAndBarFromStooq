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

        $url = "https://stooq.com/q/d/l/?s={$symbol}&d1={$oneMonthAgo}&d2={$today}&i=d";
        $csv_data = StockDataHelper::fetchUrl($url);
        
        if ($csv_data === false) {
            return null; // could not fetch
        }

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

    public static function get_actual_rates() {
        $ticker = 'navigator.hu';
        $start = date('Ymd', strtotime('-1 day'));
        $end = date('Ymd');  // pl. mai nap
        $interval = 'd';     // napi

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$start}&d2={$end}&i={$interval}";

        echo "<div>$url</div>";

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

    public static function get_last_rows_from_csv(?string $csvData, int $count = 5): array {
        if (empty($csvData)) {
            return [];
        }

        // Trim whitespace and split into lines
        $lines = explode("\n", trim($csvData));

        // Must have at least 2 lines: header + one row
        if (count($lines) < 2) {
            return [];
        }

        // Extract headers and parse them
        $headers = str_getcsv(array_shift($lines));

        // Parse each remaining line into an array
        $rows = array_map('str_getcsv', $lines);

        // Combine headers with row values
        $data = array_map(function($row) use ($headers) {
            return array_combine($headers, $row);
        }, $rows);

        // Return last $count rows
        return array_slice($data, -$count);
    }

    public static function fetchLastMonthData(){
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

    public static function fetchLastMonthDataCSV(){
        $ticker = 'navigator.hu';
        $today = date('Ymd');
        $oneMonthAgo = date('Ymd', strtotime('-1 month'));
        $interval = 'd';     // napi

        $url = "https://stooq.com/q/d/l/?s={$ticker}&d1={$oneMonthAgo}&d2={$today}&i={$interval}";

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

        wp_add_inline_script('chartjs', 'console.log("Fetching data (Half year)):", ' . json_encode($url) . ');');
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

        wp_add_inline_script('chartjs', 'console.log("Fetching data (All):", ' . json_encode($url) . ');');
        
        $csv_data = StockDataHelper::fetchUrl($url);
        if ($csv_data === false) {
            die("Nem sikerült lekérni az adatokat. url: {$url}");
        }
        $json_data = StockDataHelper::csv_to_json($csv_data);

        return $json_data;
    }

    public static function fetchYTDData(){
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


}