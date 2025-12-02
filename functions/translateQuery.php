<?php

/**
 * Функция-агент: Переводит поисковый запрос на язык целевой страны
 */
function translateQuery($text, $targetLangCode, $countryName, $apiKey)
{

    $safeText = str_replace('"', "'", $text); // Заменяем двойные кавычки на одинарные

    // Если язык целевой страны - русский, перевод не нужен
    if ($targetLangCode === 'ru' || empty($safeText)) {
        return $safeText;
    }

    $prompt = <<<EOT
    You are an expert international news editor and localization specialist. 
    Your task is to adapt a short search query (provided in ANY source language) into the specific local language and script used in {$countryName} for a Google News search.

    Context:
    - Target Country: {$countryName}
    - Target Language Code: {$targetLangCode}
    - Original Query: "{$safeText}"

    CRITICAL INSTRUCTIONS:

    1.  **Detect & Adapt:**
        - Automatically detect the source language of the "Original Query".
        - Translate the *intent* and *keywords* into the target language defined by {$targetLangCode}.

    2.  **Entity Recognition (Crucial):**
        - If the query contains Proper Names (Politicians, Cities, Companies), do NOT just transliterate. Identify the entity and use the **standard spelling used by major local media** in {$countryName}.
        - *Example (Target: Germany):* Input "Beijing" (En) -> Output "Peking". Input "Зеленский" (Ru) -> Output "Selenskyj".

    3.  **Script & Alphabet:**
        - **China (CN), Japan (JP), Korea (KR):** MUST use native script (Hanzi, Kanji, Hangul). No Latin characters unless it's a specific brand name.
        - **Arab Countries (EG, SA, AE) & Israel (IL):** MUST use Arabic/Hebrew script.
        - **Ukraine (UA):** Use Ukrainian spelling (e.g., "Єрмак" instead of "Yermak").
        - **Europe/Americas:** Use the standard Latin alphabet with local diacritics (e.g., "München", "François").

    4.  **Formatting:**
        - If the query is a common noun (e.g., "Elections", "War", "Inflation"), translate it to the local equivalent (e.g., "Wahlen", "Krieg", "Inflation").
        - If the query is a person, ensure the First Name is included if it helps ambiguity (e.g., "Donald Trump" instead of just "Trump").

    Output Format:
    Output ONLY the final translated/localized search string. No explanations. No quotes.
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
