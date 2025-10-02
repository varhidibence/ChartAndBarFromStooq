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

}