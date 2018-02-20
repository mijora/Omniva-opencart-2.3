<?php
/*
 * Class for automatic updates
 * public access
 *
 */
class ControllerExtensionModuleOmnivalt extends Controller
{

    public function index()
    {

        $this->fetchUpdates();

        return 'succes';
    }

    public function fetchUpdates()
    {

        $terminals = array();
        $csv = $this->fetchURL('https://www.omniva.ee/locations.csv');
        if (empty($csv)) {
            return;
        }

        $countries = array();
        $countries['LT'] = 1;
        $countries['LV'] = 2;
        $countries['EE'] = 3;
        $cabins = $this->parseCSV($csv, $countries);
        if ($cabins) {
            $terminals = $cabins;
        }

        $key = 'omnivalt_terminals_LT';
        $this->db->query("UPDATE " . DB_PREFIX . "setting
         SET `value` = '" . $this->db->escape(json_encode($terminals)) . "', serialized = '1'
         WHERE `key` = '" . $this->db->escape($key) . "'");

    }

    private function fetchURL($url)
    {

        $ch = curl_init(trim($url)) or die('cant create curl');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $out = curl_exec($ch) or die(curl_error($ch));
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            die('cannot fetch update from ' . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ': ' . curl_getinfo($ch, CURLINFO_HTTP_CODE));
        }

        curl_close($ch);
        return $out;
    }

    private function parseCSV($csv, $countries = array())
    {

        $cabins = array();
        if (empty($csv)) {
            return $cabins;
        }

        if (mb_detect_encoding($csv, 'UTF-8, ISO-8859-1') == 'ISO-8859-1') {
            $csv = utf8_encode($csv);
        }

        $rows = str_getcsv($csv, "\n"); #parse the rows, remove first
        $newformat = count(str_getcsv($rows[0], ';')) > 10 ? 1 : 0;
        array_shift($rows);

        foreach ($rows as $row) {
            $cabin = str_getcsv($row, ';'); //parse the items in rows
            # there are lines with all fields empty in estonian file, workaround
            if (count(array_filter($cabin))) {
                if ($newformat) {
                    if (!empty($countries[strtoupper(trim($cabin[3]))])) {
                        # closed ? exists on EE only
                        if (intval($cabin[2])) {
                            continue;
                        }

                        $cabin = array($cabin[1], $cabin[4], trim($cabin[5] . ' ' . ($cabin[8] != 'NULL' ? $cabin[8] : '') . ' ' . ($cabin[10] != 'NULL' ? $cabin[10] : '')), $cabin[0], $cabin[20], $cabin[3]);
                    } else {
                        $cabin = array();
                    }
                }
                if ($cabin) {
                    $cabins[] = $cabin;
                }

            }
        }
        return $cabins;
    }

    private function addHttps($url)
    {
        if (empty($_SERVER['HTTPS'])) {
            return $url;
        } elseif ($_SERVER['HTTPS'] == "on") {
            return str_replace('http://', 'https://', $url);
        } else {
            return $url;
        }
    }
}
