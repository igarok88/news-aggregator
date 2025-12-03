<?php

/**
 * Fetches links from Google News RSS based on settings.
 * @param string $query      Search query
 * @param string $timePeriod Time period (1h, 1d, 7d, 1y) or '' for relevance
 * @param array  $geoConfig  Country settings array ['gl', 'hl', 'ceid']
 * @return array
 */

function getNewsLinks($query, $timePeriod, $geoConfig)
{
    // 1. Time Configuration
    // If a period is specified (e.g., "1d"), append the "when:" operator.
    // If not specified, Google sorts by relevance (best matches of all time).
    $searchString = $query;
    if (!empty($timePeriod)) {
        $searchString .= " when:" . $timePeriod;
    }

    // Encode the search string to be safe for use in a URL (e.g., spaces to %20)
    $encodedQuery = urlencode($searchString);

    // 2. Location Configuration extraction
    // Extract the 'gl' (Geographic Location) parameter from the config array
    $gl = $geoConfig['gl'];
    // Extract the 'hl' (Host Language) parameter from the config array
    $hl = $geoConfig['hl'];
    // Extract the 'ceid' (Country/Language ID) parameter from the config array
    $ceid = $geoConfig['ceid'];

    // Construct the full Google News RSS URL with all parameters injected
    $rssUrl = "https://news.google.com/rss/search?q={$encodedQuery}&hl={$hl}&gl={$gl}&ceid={$ceid}";

    // 3. Download via cURL (simulating a browser request)
    // Initialize a new cURL session
    $ch = curl_init();
    // Set the target URL for the request
    curl_setopt($ch, CURLOPT_URL, $rssUrl);
    // Return the response as a string instead of outputting it directly
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Follow any redirects (HTTP 301/302)
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Set a maximum timeout of 15 seconds to prevent hanging
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    // Set a User-Agent header to mimic a real Chrome browser (avoids blocking)
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    // Disable SSL certificate verification (fixes issues on some local setups)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Execute the cURL request and store the result
    $xmlContent = curl_exec($ch);
    // Retrieve the HTTP status code (e.g., 200, 404, 500)
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // Close the cURL session to free up resources
    curl_close($ch);

    if ($httpCode !== 200 || empty($xmlContent)) {
        return [];
    }

    // Enable internal XML error handling to prevent script warnings
    libxml_use_internal_errors(true);
    // Parse the returned XML string into a SimpleXMLElement object
    $xml = simplexml_load_string($xmlContent);

    $links = [];
    if ($xml && isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $link = (string)$item->link;
            if (!empty($link)) {
                $links[] = $link;
            }
        }
    }

    return $links;
}
