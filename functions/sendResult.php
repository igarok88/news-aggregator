<?php

// === RESULT SENDING FUNCTION ===
function sendResult($html)
{
    // Encode HTML content to JSON
    $data = json_encode(['html' => $html], JSON_UNESCAPED_UNICODE);
    // Specify event name
    echo "event: result\n";
    // Output data
    echo "data: $data\n\n";
    // Force push to client
    flush();
}
