<?php

/**
 * Функция-агент: Переводит поисковый запрос на язык целевой страны
 */
function translateQuery($text, $targetLangCode, $apiKey)
{

    $safeText = str_replace('"', "'", $text); // Заменяем двойные кавычки на одинарные

    // Если язык целевой страны - русский, перевод не нужен
    if ($targetLangCode === 'ru' || empty($safeText)) {
        return $safeText;
    }

    $prompt = <<<EOT
    You are an expert news editor and linguist. Your task is to translate a short search query from Russian into the target language specifically for a Google News search.

    Target Language Code: {$targetLangCode}
    Query: "{$safeText}"

    CRITICAL RULES:
    1.  **Proper Names & Surnames**: Do NOT translate them literally. Use the standard spelling used by major media outlets in the target country.
    2.  **Transliteration Nuances**:
        - If Target is German (de): Use "J" for "Y" sound (e.g., "Yermak" -> "Jermak", "Zelensky" -> "Selenskyj").
        - If Target is English (en): Use standard English transliteration.
        - If Target is French (fr): Use French phonetic spelling (e.g., "Putin" -> "Poutine").
    3.  **Context**: If the query is a famous political figure (like "Ермак"), use their most common spelling in that country (e.g., "Andrij Jermak").
    4.  **Output**: Output ONLY the translated search phrase. No explanations.

    Example for German (de):
    Input: "Ермак" -> Output: "Andrij Jermak"
    Input: "Зеленский" -> Output: "Wolodymyr Selenskyj"
    Input: "Танки" -> Output: "Panzer"

    Example for English (en):
    Input: "Ермак" -> Output: "Andriy Yermak"

    Your translation:
    EOT;

    $apiData = [
        "contents" => [["parts" => [["text" => $prompt]]]]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($json['candidates'][0]['content']['parts'][0]['text']);
    }

    return $safeText; // Если ошибка, возвращаем оригинал
}
