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

yourls_add_action('post_yourls_info_stats', 'ip_detail_page');

function count_distinct_categories(?string $category_name, array $counter) {
    $category_name ??= '';
    $category_name = $category_name === '' ? 'unknown' : $category_name;    
    if (!key_exists($category_name, $counter)) {
        $counter[$category_name] = 0;
    }
    $counter[$category_name]++;
    return $counter;
}

function generate_pre_html(string $chart_name) : string {
    $cardHtml = <<<HTML
        <dashboard-pie caption="$chart_name">
            <div class="metrics-headline">
                <h3 class="ml16">$chart_name</h3>
            </div>
    HTML;
    return $cardHtml;
}
function generate_post_html(array $dataseries): string {
    $cardFooterHtml = <<<HTML
            <ul class="no_bullet">
    HTML;
    foreach ($dataseries as $group_name => $count) {
        $cardFooterHtml .= <<<HTML
                <li class='sites_list'>$group_name: <strong>$count</strong></li>
        HTML;
        unset($dataseries[$group_name]);
    }
    $cardFooterHtml .= <<<HTML
            </ul>
        </dashboard-pie>
    HTML;
    return $cardFooterHtml;
}

function ip_detail_page($shorturl) {
    $nonce = yourls_create_nonce('ip');
    global $ydb;
    $base  = YOURLS_SITE;
    $table_url = YOURLS_DB_TABLE_URL;
    $table_log = YOURLS_DB_TABLE_LOG;
    $outdata   = '';

    $clicks_logs = $ydb->fetchObjects("SELECT * FROM `$table_log` WHERE shorturl='$shorturl[0]' ORDER BY click_id DESC LIMIT 1000");

    $DEVICE_DATASERIES = [];
    $BROWSER_DATASERIES = [];
    $PLATFORMS_DATASERIES = [];

    if ($clicks_logs) {
        foreach ($clicks_logs as $click_log) {
            // Parse user agent
            $ua = $click_log->user_agent;
            $wbresult = new Parser($ua);

            $DEVICE_DATASERIES = count_distinct_categories($wbresult->device->type, $DEVICE_DATASERIES);
            $BROWSER_DATASERIES = count_distinct_categories($wbresult->browser->name, $BROWSER_DATASERIES);
            $PLATFORMS_DATASERIES = count_distinct_categories($wbresult->os->name, $PLATFORMS_DATASERIES);
        }
        arsort($DEVICE_DATASERIES);
        arsort($BROWSER_DATASERIES);
        arsort($PLATFORMS_DATASERIES);

        echo generate_pre_html("Devices");
        yourls_stats_pie( $DEVICE_DATASERIES, 4, '340x220', 'devices_pie' );
        echo generate_post_html($DEVICE_DATASERIES);

        echo generate_pre_html("Browsers");
        yourls_stats_pie( $BROWSER_DATASERIES, 4, '340x220', 'browsers_pie' );
        echo generate_post_html($BROWSER_DATASERIES);

        echo generate_pre_html("Platforms");
        yourls_stats_pie( $PLATFORMS_DATASERIES, 4, '340x220', 'platforms_pie' );
        echo generate_post_html($PLATFORMS_DATASERIES);
    }
}