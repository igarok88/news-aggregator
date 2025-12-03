<?php

// === LOGGING FUNCTION (Formatted for SSE) ===
function logger($msg, $type = 'info')
{
    // Define colors for different log types
    $colors = ['info' => '#333', 'success' => 'green', 'error' => 'red', 'system' => '#007bff'];
    // Select color based on type
    $color = $colors[$type] ?? '#333';
    // Get current time
    $time = date('H:i:s');

    // Encode data to JSON for JavaScript client
    $data = json_encode([
        'time' => $time,
        'msg' => $msg, // Plain text, HTML will be handled in JS
        'color' => $color
    ], JSON_UNESCAPED_UNICODE);

    // Output in strict SSE format
    echo "data: $data\n\n";
    // Force push to client
    flush();
}
