<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions/_functions.php';

// Загружаем переменные из .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$GEMINI_API_KEY = $_ENV['GEMINI_API_KEY'];
$SERP_API_KEY = $_ENV['SERP_API_KEY'];


// === ЛОГИКА ===
$resultHtml = "";
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') { //Код внутри этого блока выполняется только тогда, когда пользователь нажал кнопку submit (отправил форму).
    $query = trim($_POST['query'] ?? ''); // Удаляет случайные пробелы до и после текста запроса.

    if (empty($query)) {
        $errorMsg = "Пожалуйста, введите тему новостей.";
    } elseif (strpos($GEMINI_API_KEY, 'ВСТАВЬ') !== false) {
        $errorMsg = "Ошибка: Вы не вставили API ключ в код index.php!";
    } else {


        // echo "Ищу новости по теме: $query ...\n";

        // Передаем клиента внутрь функции
        $freshLinks = getNewsLinks($query, $SERP_API_KEY);

        $freshLinks = array_slice($freshLinks, 0, 5); ///обрезка для теста 

        //Вывод результатов (делаем это СНАРУЖИ функции)
        echo '<pre>';
        echo "Найдено " . count($freshLinks) . " свежих статей:\n";
        print_r($freshLinks);
        echo '<pre>';


        // 2. Сбор контента (ETL Pipeline). Мост между PHP и Python
        $fullContext = "";
        $articlesCount = 0;

        foreach ($freshLinks as $link) {
            // Экранируем аргумент для безопасности командной строки
            $cmd = "python3 news_fetcher.py " . escapeshellarg($link);

            // Запускаем Python и ловим вывод
            $output = shell_exec($cmd);
            $data = json_decode($output, true);

            // echo '<pre>';
            // echo "Ответ от Python-скрипта";
            // print_r($data);
            // echo '<pre>';



            if ($data && isset($data['status']) && $data['status'] === 'success') {
                $articlesCount++;
                // Добавляем текст статьи в общий контекст для нейросети
                $fullContext .= "\n\n=== СТАТЬЯ {$articlesCount}: {$data['url']} ===\n";

                // Ограничиваем длину (чтобы не порвать лимиты). Мы обрезаем статью, если она гигантская. У Gemini большое "окно памяти", но оно не бесконечное, плюс большие запросы могут дольше обрабатываться.
                $fullContext .= substr($data['text'], 0, 15000);
            }
        }

        // echo '<pre>';
        // echo "Готовый текст статей для ИИ:";
        // print_r($fullContext);
        // echo '<pre>';

        if ($articlesCount > 0) {
            // 3. Запрос к Gemini
            $prompt = "Ты — профессиональный новостной аналитик. 
            Твоя задача: Прочитай предоставленные ниже статьи и составь краткий аналитический отчет на русском языке.
            
            Запрос пользователя: '$query' (Используй это как контекст того, что искать).
            
            Структура ответа:
            1. Заголовок (Главная новость)
            2. Ключевые факты (буллиты)
            3. Аналитический вывод
            
            Текст статей:
            $fullContext";

            // echo '<pre>';
            // echo "Готовый промт для ИИ:";
            // print_r($prompt);
            // echo '<pre>';

            $apiData = [
                "contents" => [
                    ["parts" => [["text" => $prompt]]]
                ]
            ];

            // --- ИСПРАВЛЕНИЕ: Безопасное кодирование JSON ---

            // 1. Пытаемся закодировать
            $jsonPayload = json_encode($apiData, JSON_UNESCAPED_UNICODE);

            // 2. Проверяем ошибку кодирования
            if ($jsonPayload === false) {
                // Если ошибка, скорее всего дело в кодировке. Пытаемся "починить" текст
                $apiData['contents'][0]['parts'][0]['text'] = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');
                $jsonPayload = json_encode($apiData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

                // Если все равно не вышло
                if ($jsonPayload === false) {
                    die("Ошибка JSON Encode: " . json_last_error_msg());
                }
            }

            $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $GEMINI_API_KEY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));

            // Отправляем проверенный payload
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

            $response = curl_exec($ch);
            curl_close($ch);

            $jsonResp = json_decode($response, true);
            // Достаем текст из глубокой структуры JSON ответа Google
            if (isset($jsonResp['candidates'][0]['content']['parts'][0]['text'])) {
                // Преобразуем Markdown в простой HTML (nl2br)
                $md = htmlspecialchars($jsonResp['candidates'][0]['content']['parts'][0]['text']);
                // Простая подсветка заголовков и жирного шрифта
                $md = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $md);
                $md = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $md);
                $md = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $md);
                $resultHtml = nl2br($md);
            } else {
                $errorMsg = "Ошибка API Gemini: " . htmlspecialchars($response);
            }
        } else {
            $errorMsg = "Не удалось скачать ни одну статью. Проверьте логи Python.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>AI News Aggregator</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <div class="container">
        <h1>📰 AI Новостной Агрегатор</h1>
        <p>Введите тему, и ИИ проанализирует свежие статьи.</p>

        <form method="POST" onsubmit="document.querySelector('.loading').style.display='block'">
            <input type="text" name="query" placeholder="Например: Скандал с BBC или Курс Биткоина" required>
            <button type="submit">Найти и проанализировать</button>
        </form>

        <div class="loading">⏳ Читаем статьи и думаем... (это займет 10-20 секунд)</div>

        <?php if ($errorMsg): ?>
            <div class="error"><?= $errorMsg ?></div>
        <?php endif; ?>

        <?php if ($resultHtml): ?>
            <div class="result">
                <?= $resultHtml ?>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>