<?php

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


// echo '<pre>';
// echo "countries: ";
// print_r($countries);
// echo '<pre>';


// Опции количества ссылок
$limitOptions = [
    5  => '5 статей (Быстро)',
    10 => '10 статей (Средне)',
    20 => '20 статей (Подробно)'
];

// Список периодов времени
$periods = [
    '1h' => 'За последний час',
    '1d' => 'За 24 часа',
    '7d' => 'За неделю',
    ''   => 'По релевантности (все время)'
];

// Значения по умолчанию
$selectedCountry = 'ru';
$selectedPeriod = '7d';
$selectedLimit = 5;
$resultHtml = "";
$errorMsg = "";

// === ОБРАБОТКА ФОРМЫ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $queryTopic = trim($_POST['query'] ?? '');
    $selectedCountry = $_POST['country'] ?? 'ru';
    $selectedPeriod = $_POST['period'] ?? '1d';
    $selectedLimit = (int)($_POST['limit'] ?? 5);
    $selectedOutputLang = $_POST['output_lang'] ?? 'ru';

    // Получаем конфиг выбранной страны
    $geoConfig = $countries[$selectedCountry] ?? $countries['ru'];

    if (!empty($queryTopic)) {

        // 1. АВТО-ПЕРЕВОД ЗАПРОСА
        // Берем язык из настроек страны (первые 2 буквы hl, например 'de' из 'de-DE')
        $targetLang = substr($geoConfig['hl'], 0, 2);

        // Спрашиваем Gemini правильный перевод
        $searchQuery = translateQuery($queryTopic, $targetLang, $GEMINI_API_KEY);

        // echo '<pre>';
        // echo "Перевод запроса от Gemini: ";
        // print_r($searchQuery);
        // echo '<pre>';



        // 1. Ищем ссылки с новыми параметрами
        $links = getNewsLinks($searchQuery, $selectedPeriod, $geoConfig);



        $foundCount = count($links);

        // echo '<pre>';
        // echo "foundCount: ";
        // print_r($foundCount);
        // echo '<pre>';

        if ($foundCount === 0) {
            $errorMsg = "Ничего не найдено. Попробуйте сменить страну или время.";
        } else {
            // 2. Python
            $fullContext = "";
            $processedCount = 0;
            $linksToProcess = array_slice($links, 0, $selectedLimit);

            foreach ($linksToProcess as $link) {
                $cmd = "python3 news_fetcher.py " . escapeshellarg($link);
                $output = shell_exec($cmd);
                $data = json_decode($output, true);



                if ($data && isset($data['status']) && $data['status'] === 'success') {
                    $processedCount++;
                    $fullContext .= "\n\n=== СТАТЬЯ $processedCount: {$data['url']} ===\n";
                    $fullContext .= substr($data['text'], 0, 15000);
                }
            }

            // echo '<pre>';
            // echo "fullContext: ";
            // print_r($fullContext);
            // echo '<pre>';

            // 3. Анализ (Gemini)
            if ($fullContext) {
                // Добавляем в промпт информацию о языке и стране, чтобы Gemini отвечал в контексте
                $targetLangName = $outputLanguages[$selectedOutputLang] ?? 'Russian';
                $countryName = $geoConfig['name'];

                $prompt = "You are an international news analyst.
                News Source: $countryName.
                Query: '$queryTopic'.

                Analyze the texts and compile a report.

                Structure:
                1. Main Event.
                2. Details and Facts.

                IMPORTANT: Write the final response in language: $targetLangName.
                
                Articles:
                $fullContext";

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
                    curl_close($ch);

                    $jsonResp = json_decode($response, true);

                    if (isset($jsonResp['candidates'][0]['content']['parts'][0]['text'])) {
                        $md = htmlspecialchars($jsonResp['candidates'][0]['content']['parts'][0]['text']);
                        $md = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $md);
                        $md = preg_replace('/^# (.*)$/m', '<h2>$1</h2>', $md);
                        $md = preg_replace('/^## (.*)$/m', '<h3>$1</h3>', $md);
                        $resultHtml = nl2br($md);
                    } elseif (isset($jsonResp['error'])) {
                        $errorMsg = "Ошибка API Gemini: " . ($jsonResp['error']['message'] ?? 'Unknown');
                    } else {
                        $errorMsg = "Пустой ответ API.";
                    }
                }
            } else {
                $errorMsg = "Нашли ссылки ($foundCount), но не смогли прочитать текст.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global AI News</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>

    <div class="container">
        <h1>🌍 Global AI News</h1>

        <form method="POST" onsubmit="document.getElementById('loader').style.display='block'">

            <div class="filters">
                <div class="filter-group">
                    <label>Регион поиска:</label>
                    <select name="country">
                        <?php foreach ($countries as $code => $data): ?>
                            <option value="<?= $code ?>" <?= $selectedCountry === $code ? 'selected' : '' ?>>
                                <?= $data['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Период времени:</label>
                    <select name="period">
                        <?php foreach ($periods as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $selectedPeriod === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Глубина анализа:</label>
                    <select name="limit">
                        <?php foreach ($limitOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $selectedLimit === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Язык отчета:</label>
                    <select name="output_lang">
                        <?php

                        foreach ($outputLanguages as $code => $aiName):
                            // Если есть красивое название в $uiLabels, берем его, иначе берем английское
                            $label = $uiLabels[$code] ?? $aiName;
                        ?>
                            <option value="<?= $code ?>" <?= $selectedOutputLang === $code ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-group">



                <input type="text" name="query" placeholder="Введите тему (например: Выборы, Bitcoin, BMW)" value="<?= htmlspecialchars($queryTopic ?? '') ?>" required>
            </div>

            <button type="submit" class="search-btn">Найти и Анализировать</button>
        </form>

        <div id="loader" class="loader">
            🚀 Запускаем браузеры, читаем иностранную прессу...<br>
            Пожалуйста, подождите 20-30 секунд.
        </div>
        <?php if ($resultHtml): ?>
            <div class="result-box">
                <div style="background: #eef; padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 0.9em;">
                    🔎 <strong>Поиск:</strong> Вы искали «<?= htmlspecialchars($queryTopic) ?>».<br>
                    🤖 <strong>AI-Агент:</strong> Для региона <?= $geoConfig['name'] ?> запрос переведен как «<strong><?= htmlspecialchars($searchQuery) ?></strong>».
                </div>

                <?= $resultHtml ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="error">⚠️ <?= $errorMsg ?></div>
        <?php endif; ?>

        <!-- <?php if ($resultHtml): ?>
                    <div class="result-box">
                        <?= $resultHtml ?>
                    </div>
                <?php endif; ?> -->


        <button class="settings-btn" onclick="openSettings()" title="Настройки API">⚙️</button>

        <div id="settingsModal" class="modal-overlay">
            <div class="modal-content">
                <span class="modal-close" onclick="closeSettings()">&times;</span>
                <h2 style="margin-top: 0; color: #2c3e50;">Настройки API</h2>

                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Ваш Google Gemini API Key:</label>
                <input type="password" id="apiKeyInput" class="api-input" placeholder="AIzaSy..." value="<?= htmlspecialchars($userKey) ?>">

                <button class="save-key-btn" onclick="saveApiKey()">Сохранить и Перезагрузить</button>

                <?php if ($envKey): ?>
                    <div style="margin-top: 10px; font-size: 12px; color: green;">
                        ✅ Найден системный ключ в .env (используется, если поле выше пустое).
                    </div>
                <?php endif; ?>

                <div class="help-text">
                    <strong>Нет ключа? Это бесплатно.</strong><br>
                    1. Перейдите в <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.<br>
                    2. Нажмите "Create API Key".<br>
                    3. Скопируйте ключ и вставьте сюда.
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>

</html>