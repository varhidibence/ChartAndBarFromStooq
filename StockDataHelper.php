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
            return null; // could not fetch
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

}