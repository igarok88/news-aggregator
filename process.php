<?php
// === НАСТРОЙКА ЗАГОЛОВКОВ ДЛЯ SSE ===
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Для Nginx


// Отключаем буферизацию для Real-time логов
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) {
    ob_end_flush();
}
ob_implicit_flush(1);

// Убираем вывод ошибок в поток, чтобы не сломать JSON формат
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions/_loader.php';
require_once __DIR__ . '/config/_loader.php';


// === КОНФИГУРАЦИЯ API КЛЮЧЕЙ ===
// Пытаемся получить из .env, если нет — используем жестко заданный (резерв)
if (class_exists('Dotenv\Dotenv')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    } catch (Exception $e) {
        // Игнорируем, если .env нет
    }
}

// ЛОГИКА ПРИОРИТЕТА: 
// 1. Ключ из браузера (Cookie) - самый главный
// 2. Ключ из файла .env
// 3. Заглушка
$userKey = $_COOKIE['gemini_user_key'] ?? null;
$envKey = $_ENV['GEMINI_API_KEY'] ?? null;

$GEMINI_API_KEY = $userKey ?: ($envKey ?: 'ВСТАВЬ_СЮДА_СВОЙ_КЛЮЧ_GEMINI');


// === ФУНКЦИЯ ЛОГИРОВАНИЯ (Под формат SSE) ===
function logger($msg, $type = 'info')
{
    $colors = ['info' => '#333', 'success' => 'green', 'error' => 'red', 'system' => '#007bff'];
    $color = $colors[$type] ?? '#333';
    $time = date('H:i:s');

    // Формируем JSON для JS
    $data = json_encode([
        'time' => $time,
        'msg' => $msg, // Чистый текст, HTML добавим в JS
        'color' => $color
    ], JSON_UNESCAPED_UNICODE);

    echo "data: $data\n\n"; // Строгий формат SSE
    flush();
}

// === ФУНКЦИЯ ОТПРАВКИ РЕЗУЛЬТАТА ===
function sendResult($html)
{
    $data = json_encode(['html' => $html], JSON_UNESCAPED_UNICODE);
    echo "event: result\n"; // Имя события
    echo "data: $data\n\n";
    flush();
}

$queryTopic = trim($_GET['query'] ?? '');

$selectedCountry = $_GET['country'] ?? 'ru';
$selectedPeriod = $_GET['period'] ?? '1d';
$selectedLimit = (int)($_GET['limit'] ?? 5);
$selectedOutputLang = $_GET['output_lang'] ?? 'ru';

// Получаем конфиг выбранной страны
$geoConfig = $countries[$selectedCountry] ?? $countries['ru'];

// 1. АВТО-ПЕРЕВОД ЗАПРОСА
// Берем язык из настроек страны (первые 2 буквы hl, например 'de' из 'de-DE')
$targetLang = substr($geoConfig['hl'], 0, 2);
$countryName = $geoConfig['name'];

$resultHtml = "";
// $errorMsg = "";

if (!empty($queryTopic)) {

    logger("🚀 Старт обработки запроса: '$queryTopic'", 'system');

    // Спрашиваем Gemini правильный перевод
    $searchQuery = translateQuery($queryTopic, $targetLang, $countryName, $GEMINI_API_KEY);

    logger("🌍 Запрос переведен как: '$searchQuery', для региона $countryName", 'info');

    // 1. Ищем ссылки с новыми параметрами
    $links = getNewsLinks($searchQuery, $selectedPeriod, $geoConfig);

    $foundCount = count($links);

    if ($foundCount === 0) {

        logger("❌ Ничего не найдено по запросу.", 'error');

        sendResult("<p>К сожалению, новостей по этому запросу не найдено.</p>");

        exit();
    }

    logger("✅ Найдено ссылок: $foundCount. Обрабатываем первые $selectedLimit...", 'info');

    // 3. PYTHON (Скачивание)
    $fullContext = "";
    $processedCount = 0;

    $linksToProcess = array_slice($links, 0, $selectedLimit);


    foreach ($linksToProcess as $link) {

        $processedCount++;
        logger("⏳ [$processedCount/$selectedLimit] Скачиваем статью: <a href='{$link}' target='_blank'> {$link}", 'info');

        // exit();


        $cmd = "python3 news_fetcher.py " . escapeshellarg($link);
        $output = shell_exec($cmd);
        $data = json_decode($output, true);


        if ($data && isset($data['status']) && $data['status'] === 'success') {

            // logger("📄 Ссылка: <a href='{$data['url']}' target='_blank'>{$data['url']}</a>", 'success');


            logger("📄 Статья успешно скачана (" . mb_strlen($data['text']) . " симв.) Ссылка на оригинальную статью: <a href='{$data['url']}' target='_blank'>{$data['url']}</a>", 'success');

            $fullContext .= "\n\n=== СТАТЬЯ $processedCount: {$data['url']} ===\n";
            $fullContext .= mb_substr($data['text'], 0, 15000);
        } else {
            logger("⚠️ Ошибка скачивания: " . ($data['error'] ?? 'Unknown error'), 'error');
        }
    }


    // 4. Анализ (Gemini)
    if ($fullContext) {

        logger("🧠 Отправляем данные в Gemini для анализа...", 'system');

        // Добавляем в промпт информацию о языке и стране, чтобы Gemini отвечал в контексте
        $targetLangName = $outputLanguages[$selectedOutputLang]['name'] ?? 'Russian';


        $prompt = <<<EOT
        You are a strict, automated intelligence briefing system.
        Your task is to analyze news articles and generate a structured report.

        Context:
        - Source Region: $countryName
        - Search Query: "$queryTopic"
        - Target Language: $targetLangName

        STRICT OUTPUT RULES (CRITICAL):
        1.  **NO conversational fillers.** Do NOT say "Here is the report", "Based on the text", or "In summary".
        2.  **NO meta-descriptions.** Do not describe what you are doing. Just do it.
        3.  **Start IMMEDIATELY** with the main headline (Format: # Headline).
        4.  **Language:** The ENTIRE report must be in $targetLangName.

        Report Structure:
        # 🌍 [Main Analytical Headline of the Event]

        ## ⚡ Key Takeaways
        [Bullet points of the most important facts]

        ## 🔍 In-Depth Analysis
        [Detailed summary of the situation based on the articles]

        ---
        Input Articles:
        $fullContext
        EOT;

        // Очистка кодировки
        $prompt = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');

        $apiData = [
            "contents" => [["parts" => [["text" => $prompt]]]]
        ];

        // ЗАЩИТА JSON
        $jsonPayload = json_encode($apiData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        if ($jsonPayload === false) {
            $errorMsg = "Ошибка кодирования JSON: " . json_last_error_msg();
        } else {

            $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $GEMINI_API_KEY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                logger("Curl error: " . curl_error($ch), 'error');
            }

            curl_close($ch);

            $jsonResp = json_decode($response, true);

            if (isset($jsonResp['candidates'][0]['content']['parts'][0]['text'])) {
                $md = htmlspecialchars($jsonResp['candidates'][0]['content']['parts'][0]['text']);

                // парсинг Markdown
                $md = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $md);
                $md = preg_replace('/^# (.*)$/m', '<h2>$1</h2>', $md);
                $md = preg_replace('/^## (.*)$/m', '<h3>$1</h3>', $md);
                $resultHtml = nl2br($md);

                logger("✨ Анализ завершен!", 'success');
                // ОТПРАВЛЯЕМ ФИНАЛЬНЫЙ РЕЗУЛЬТАТ
                sendResult($resultHtml);
            } elseif (isset($jsonResp['error'])) {

                $err = $jsonResp['error']['message'] ?? 'Unknown';

                logger("Ошибка API Gemini: $err", 'error');

                sendResult("<p style='color:red'>Ошибка API: $err</p>");
            } else {
                logger("Пустой ответ API", 'error');
                sendResult("<p>Получен пустой ответ от нейросети.</p>");
            }
        }
    } else {
        logger("Не удалось получить текст статей.", 'error');
        sendResult("<p>Не удалось прочитать содержимое найденных статей.</p>");
    }
}
