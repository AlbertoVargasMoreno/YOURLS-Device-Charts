<?php
/*
Plugin Name: Device Details Charts
Plugin URI: https://github.com/AlbertoVargasMoreno/YOURLS-Device-Details
Description: Parses user-agent using a custom library to display charts about devices, browsers and platforms
Version: 1.0
Author: Alberto Vargas
Author URI: https://github.com/AlbertoVargasMoreno
*/

/*
This script requires the function `yourls_stats_pie()`
This function is declared in `includes\functions-infos.php`
Which is already included by `yourls-infos.php` in `require_once( dirname( __FILE__ ).'/includes/load-yourls.php' )`
*/

// Load the user-agent parsing library WhichBrowser
use WhichBrowser\Parser;
require __DIR__ . '/../../../includes/vendor/autoload.php';

yourls_add_action('post_yourls_info_stats', 'device_charts_page');

function count_distinct_categories(?string $category_name, array $counter) {
    $category_name ??= '';
    $category_name = $category_name === '' ? 'Unknown' : ucfirst($category_name);
    if (!key_exists($category_name, $counter)) {
        $counter[$category_name] = 0;
    }
    $counter[$category_name]++;
    return $counter;
}

function generate_open_table_html() : string {
    $tableHtml = <<<HTML
    <table border="0" cellspacing="2">
        <tbody>
            <tr>
    HTML;
    return $tableHtml;
}
function generate_close_table_html() : string {
    $tableHtml = <<<HTML
                </tr>
        </tbody>
    </table> 
    HTML;
    return $tableHtml;
}

function generate_open_chartContainer_html(string $chart_name) : string {
    $cardHtml = <<<HTML
    <td valign="top">
        <dashboard-pie caption="$chart_name">
            <div class="metrics-headline">
                <h3 class="ml16">$chart_name</h3>
            </div>
    HTML;
    return $cardHtml;
}
function generate_close_chartContainer_html(array $dataseries): string {
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
    </td>
    HTML;
    return $cardFooterHtml;
}

function device_charts_page($shorturl) {
    global $ydb;
    $table_log = YOURLS_DB_TABLE_LOG;

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

        echo "
        <br>
        <br>
        ";
        echo generate_open_table_html();
            echo generate_open_chartContainer_html("Devices");
            yourls_stats_pie( $DEVICE_DATASERIES, 4, '340x220', 'devices_pie' );
            echo generate_close_chartContainer_html($DEVICE_DATASERIES);            

            echo generate_open_chartContainer_html("Browsers");
            yourls_stats_pie( $BROWSER_DATASERIES, 4, '340x220', 'browsers_pie' );
            echo generate_close_chartContainer_html($BROWSER_DATASERIES);            

            echo generate_open_chartContainer_html("Platforms");
            yourls_stats_pie( $PLATFORMS_DATASERIES, 4, '340x220', 'platforms_pie' );
            echo generate_close_chartContainer_html($PLATFORMS_DATASERIES);
        echo generate_close_table_html();
    }
}