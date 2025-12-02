<?php
require_once __DIR__ . '/functions/_loader.php';
require_once __DIR__ . '/config/_loader.php';
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

        <button class="settings-btn" onclick="openSettings()" title="Настройки API">⚙️</button>

        <h1>🌍 Global AI News</h1>

        <form id="searchForm">
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
                        <?php foreach ($outputLanguages as $code => $info): ?>
                            <option value="<?= $code ?>" <?= $selectedOutputLang === $code ? 'selected' : '' ?>>
                                <?= $info['label'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="filter-group">

                <input type="text" name="query" placeholder="Введите тему (например: Выборы, Bitcoin, BMW)" value="<?= htmlspecialchars($queryTopic ?? '') ?>" required>

            </div>

            <button type="submit" id="btnSubmit" class="search-btn">Найти и Анализировать</button>

        </form>



        <div id="logWrapper" class="log-wrapper" style="display:none;">
            <div class="log-header" onclick="toggleLog()">
                <span id="logTitle">📜 Лог операций</span>
                <span id="logIcon">▼</span>
            </div>

            <div id="logContent"></div>
        </div>

        <div id="resultWrapper"></div>


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

    <!-- <script src="js/script.js"></script> -->
    <script src="js/script.js"></script>

</body>

</html>