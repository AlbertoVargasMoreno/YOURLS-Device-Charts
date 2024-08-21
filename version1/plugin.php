<?php
/*
Plugin Name: Device Details
Plugin URI: https://github.com/SachinSAgrawal/YOURLS-Device-Details
Description: Parses user-agent using a custom library to display charts about devices
Version: 1.0
Author: Alberto Vargas
Author URI: https://github.com/AlbertoVargasMoreno
*/

// Load the user-agent parsing library WhichBrowser
// require 'vendor/autoload.php';
require __DIR__ . '/../../../includes/vendor/autoload.php';
use WhichBrowser\Parser;
require_once(dirname(__FILE__) . '/../../../includes/load-yourls.php');

yourls_add_action('post_yourls_info_stats', 'ip_detail_page');

function get_ip_info($ip) {
    $url = "https://ipinfo.io/{$ip}/json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function get_timezone_offset($timezone) {
    $timezone_object = new DateTimeZone($timezone);
    $datetime = new DateTime("now", $timezone_object);
    $offset = $timezone_object->getOffset($datetime);
    return $offset / 60; // Convert seconds to minutes
}

function timezone_offset_to_gmt_offset($timezone_offset) {
    $timezone_offset = intval($timezone_offset);
    $hours = floor($timezone_offset / 60);
    $offset = ($timezone_offset < 0 ? '-' : '+') . abs($hours);
    return 'GMT' . $offset;
}

function count_distinct_categories(?string $category_name, array $counter) {
    $category_name ??= '';
    $category_name = $category_name === '' ? 'unknown' : $category_name;    
    if (!key_exists($category_name, $counter)) {
        $counter[$category_name] = 0;
    }
    $counter[$category_name]++;
    return $counter;
}

function ip_detail_page($shorturl) {
    $nonce = yourls_create_nonce('ip');
    global $ydb;
    $base  = YOURLS_SITE;
    $table_url = YOURLS_DB_TABLE_URL;
    $table_log = YOURLS_DB_TABLE_LOG;
    $outdata   = '';

    $query = $ydb->fetchObjects("SELECT * FROM `$table_log` WHERE shorturl='$shorturl[0]' ORDER BY click_id DESC LIMIT 1000");

    $DEVICE_DATASERIES = [];
    $BROWSER_DATASERIES = [];
    $PLATFORMS_DATASERIES = [];

    if ($query) {
        foreach ($query as $query_result) {
            $me = "";
            $me2 = "";
            if ($query_result->ip_address == $_SERVER['REMOTE_ADDR']) {
                $me = " bgcolor='#d4eeff'";
                $me2 = "<br><i>this is your ip</i>";
            }

            // Parse user agent
            $ua = $query_result->user_agent;
            $wbresult = new Parser($ua);

            // Get additional IP information from ipinfo.io
            $ip_info = get_ip_info($query_result->ip_address);

            // Calculate local time
            $click_time_utc = new DateTime($query_result->click_time, new DateTimeZone('UTC'));
            $timezone_offset = isset($ip_info['timezone']) ? get_timezone_offset($ip_info['timezone']) : 0;
            $click_time_utc->modify($timezone_offset . ' minutes');
            $local_time = $click_time_utc->format('Y-m-d H:i:s');

            // Convert timezone offset to GMT offset
            $gmt_offset = timezone_offset_to_gmt_offset($timezone_offset);
            
            // Debugging: Print raw data to browser console
            // echo '<script>';
            // echo 'console.log(' . json_encode($wbresult) . ');';
            // echo '</script>';

            $DEVICE_DATASERIES = count_distinct_categories($wbresult->device->type, $DEVICE_DATASERIES);
            $BROWSER_DATASERIES = count_distinct_categories($wbresult->browser->name, $BROWSER_DATASERIES);
            $PLATFORMS_DATASERIES = count_distinct_categories($wbresult->os->name, $PLATFORMS_DATASERIES);

            $outdata .= '<tr'.$me.'><td>'.$query_result->click_time.'</td>
                        <td>'.$local_time.'</td>
                        <td>'.$gmt_offset.'</td>
						<td>'.$query_result->country_code.'</td>
						<td>'.$ip_info['city'].'</td>
						<td><a href="https://who.is/whois-ip/ip-address/'.$query_result->ip_address.'" target="blank">'.$query_result->ip_address.'</a>'.$me2.'</td>
						<td>'.$ua.'</td>
						<td>'.$wbresult->browser->name . ' ' . $wbresult->browser->version->value.'</td>
						<td>'.$wbresult->os->name. ' ' . $wbresult->os->version->value.'</td>
						<td>'.$wbresult->device->model.'</td>
						<td>'.$wbresult->device->manufacturer.'</td>
						<td>'.$wbresult->device->type.'</td>
						<td>'.$wbresult->engine->name.'</td>
						<td>'.$query_result->referrer.'</td>
						</tr>';
        }

        $appendedHtml = '<table  border="1" cellpadding="5" style="margin-top:25px;"><tr><td width="80">Timestamp</td><td>Local Time</td><td>Timezone</td><td>Country</td><td>City</td>
				<td>IP Address</td><td>User Agent</td><td>Browser Version</td><td>OS Version</td><td>Device Model</td>
				<td>Device Vendor</td><td>Device Type</td><td>Engine</td><td>Referrer</td></tr>' . $outdata . "</table><br>\n\r";
        yourls_stats_pie( $DEVICE_DATASERIES, 10, '340x220', 'devices_pie' );
        yourls_stats_pie( $BROWSER_DATASERIES, 10, '340x220', 'browsers_pie' );
        yourls_stats_pie( $PLATFORMS_DATASERIES, 10, '340x220', 'platforms_pie' );
        // echo $appendedHtml;
    }
}