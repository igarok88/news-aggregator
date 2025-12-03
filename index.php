<?php
require_once __DIR__ . '/config/config.php';

// === ERROR PROTECTION ===
// Initialize variables with default values if they were not loaded from config.
// This prevents the page from crashing with "Undefined variable" errors.
$countries       = $countries ?? [];
$periods         = $periods ?? [];
$limitOptions    = $limitOptions ?? [];
$outputLanguages = $outputLanguages ?? [];

$selectedCountry    = $selectedCountry ?? '';
$selectedPeriod     = $selectedPeriod ?? '';
$selectedLimit      = $selectedLimit ?? '';
$selectedOutputLang = $selectedOutputLang ?? '';

$queryTopic  = $queryTopic ?? '';
$resultHtml  = $resultHtml ?? '';
$errorMsg    = $errorMsg ?? '';
$userKey     = $userKey ?? '';
$envKey      = $envKey ?? false;
$geoConfig   = $geoConfig ?? ['name' => 'Unknown Region'];
$searchQuery = $searchQuery ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global AI News</title>

    <meta property="og:title" content="Global AI News">
    <meta property="og:description" content="Global AI-powered news analytics">

    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <div class="container">
        <header class="app-header">
            <div class="brand-block" onclick="location.reload()" title="Сбросить и обновить">
                <h1 class="company-name"><span class="highlight">GAIN</span></h1>
                <span class="company-tagline">Global AI News</span>
            </div>
            <div class="header-actions">
                <button class="settings-btn-header" onclick="openSettings()" title="API Settings">⚙️</button>
            </div>
        </header>
        <form id="searchForm" method="GET" action="">
            <div class="filters">
                <div class="filter-group">
                    <label for="countrySelect">Search Region:</label>
                    <select name="country" id="countrySelect">
                        <?php foreach ($countries as $code => $data): ?>
                            <option value="<?= $code ?>" <?= $selectedCountry === $code ? 'selected' : '' ?>>
                                <?= $data['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="periodSelect">Time Period:</label>
                    <select name="period" id="periodSelect">
                        <?php foreach ($periods as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $selectedPeriod === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="limitSelect">Analysis Depth:</label>
                    <select name="limit" id="limitSelect">
                        <?php foreach ($limitOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $selectedLimit === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="langSelect">Report Language:</label>
                    <select name="output_lang" id="langSelect">
                        <?php foreach ($outputLanguages as $code => $info): ?>
                            <option value="<?= $code ?>" <?= $selectedOutputLang === $code ? 'selected' : '' ?>>
                                <?= $info['label'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-group">
                <input type="text"
                    name="query"
                    id="queryInput"
                    placeholder="Enter topic (e.g., Elections, Bitcoin, BMW)"
                    value="<?= htmlspecialchars($queryTopic) ?>"
                    required
                    autofocus>
            </div>
            <button type="submit" id="btnSubmit" class="search-btn">Find and Analyze</button>
        </form>
        <div id="logWrapper" class="log-wrapper" style="display:none;">
            <div class="log-header" onclick="toggleLog()">
                <span id="logTitle">📜 Operation Log</span>
                <span id="logIcon">▼</span>
            </div>
            <div id="logContent"></div>
        </div>
        <div id="resultWrapper"></div>
        <?php if ($resultHtml): ?>
            <div class="result-box">
                <div style="background: #eef; padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 0.9em;">
                    🔎 <strong>Search:</strong> You searched for "<?= htmlspecialchars($queryTopic) ?>".<br>
                    🤖 <strong>AI Agent:</strong> For region <?= htmlspecialchars($geoConfig['name'] ?? 'World') ?>, the query was translated as "<strong><?= htmlspecialchars($searchQuery) ?></strong>".
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
                <h2 style="margin-top: 0; color: #2c3e50;">API Settings</h2>
                <label for="apiKeyInput" style="font-weight: bold; display: block; margin-bottom: 5px;">Your Google Gemini API Key:</label>
                <input type="password"
                    id="apiKeyInput"
                    class="api-input"
                    placeholder="AIzaSy..."
                    value="<?= htmlspecialchars($userKey) ?>"
                    autocomplete="new-password">
                <button class="save-key-btn" onclick="saveApiKey()">Save and Reload</button>
                <?php if ($envKey): ?>
                    <div style="margin-top: 10px; font-size: 12px; color: green;">
                        ✅ System key found in .env (used if the field above is empty).
                    </div>
                <?php endif; ?>
                <div class="help-text">
                    <strong>No key? It's free.</strong><br>
                    1. Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.<br>
                    2. Click "Create API Key".<br>
                    3. Copy the key and paste it here.
                </div>
            </div>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>

</html>