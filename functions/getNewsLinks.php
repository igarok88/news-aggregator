<?php

/**
 * Получает ссылки из Google News RSS с учетом настроек.
 * * @param string $query Поисковый запрос
 * @param string $timePeriod Период (1h, 1d, 7d, 1y) или '' для релевантности
 * @param array  $geoConfig Массив настроек страны ['gl', 'hl', 'ceid']
 * @return array
 */
function getNewsLinks($query, $timePeriod, $geoConfig)
{
    // 1. Настройка времени
    // Если период указан (например "1d"), добавляем оператор "when:".
    // Если не указан — Google сам отсортирует по релевантности (лучшие совпадения за всё время).
    $searchString = $query;
    if (!empty($timePeriod)) {
        $searchString .= " when:" . $timePeriod;
    }

    $encodedQuery = urlencode($searchString);

    // 2. Настройка локации
    // Подставляем параметры из выбранной страны
    $gl = $geoConfig['gl'];
    $hl = $geoConfig['hl'];
    $ceid = $geoConfig['ceid'];

    $rssUrl = "https://news.google.com/rss/search?q={$encodedQuery}&hl={$hl}&gl={$gl}&ceid={$ceid}";

    // echo '<pre>';
    // echo "rssUrl: ";
    // print_r($rssUrl);
    // echo '<pre>';

    // 3. Скачивание через cURL (как браузер)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rssUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $xmlContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($xmlContent)) {
        return [];
    }

    libxml_use_internal_errors(true);
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
